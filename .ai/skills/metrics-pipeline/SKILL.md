---
name: metrics-pipeline
description: MetricsService, RedisMetricsCollector, ProcessPhotoMetrics, metrics table, Redis stats, leaderboards, XP processing, and photo processing state (processed_at/fp/tags/xp).
---

# Metrics Pipeline

MetricsService is the **single writer** for all metrics — MySQL time-series and Redis aggregates. Nothing else touches metric counters. This is the golden rule.

## Key Files

- `app/Services/Metrics/MetricsService.php` — Single writer for MySQL + Redis
- `app/Services/Redis/RedisMetricsCollector.php` — Redis operations (stats, HLL, rankings, tags)
- `app/Services/Redis/RedisKeys.php` — All Redis key builders (single source of truth for naming)
- `app/Listeners/Metrics/ProcessPhotoMetrics.php` — Queued listener on `TagsVerifiedByAdmin`
- `app/Events/TagsVerifiedByAdmin.php` — Trigger event for metrics processing
- `app/Enums/LocationType.php` — Global(0), Country(1), State(2), City(3) with scope prefixes

## Invariants

1. **Single writer rule.** Only `MetricsService` writes to the `metrics` table and Redis metric keys. No other code may increment/decrement counters.
2. **Processing state is four columns:** `processed_at`, `processed_fp`, `processed_tags`, `processed_xp`. A photo with `processed_at = null` has never affected aggregates.
3. **Fingerprint-based idempotency.** MetricsService diffs old `processed_tags` JSON against new summary and writes only non-zero deltas. Safe to call repeatedly on any photo.
4. **Summary must exist before metrics fire.** `GeneratePhotoSummaryService::run()` MUST be called before `TagsVerifiedByAdmin` dispatches. MetricsService reads from `photo.summary`.
5. **Redis is a derived cache.** Rebuildable from the `metrics` table. `RedisKeys::*` is single source of truth for key naming.
6. **`processed_xp` must be INT UNSIGNED**, not TINYINT. Overflow bug documented in migration `2026_02_23_182605`.
7. **Tags count excludes categories** to avoid double-counting: `tags_count = objects + materials + brands + custom_tags`.
8. **`ProcessPhotoMetrics` logs a warning when photo not found.** If a photo is soft-deleted before the queued listener runs, `ProcessPhotoMetrics::handle()` logs a warning and returns gracefully. It does NOT throw or retry.

## Patterns

### How MetricsService processes a photo

```php
// MetricsService::processPhoto() — called by ProcessPhotoMetrics listener
DB::transaction(function () use ($photo) {
    $photo = Photo::whereKey($photo->id)->lockForUpdate()->first();
    $metrics = $this->extractMetricsFromPhoto($photo);  // reads photo.summary
    $fingerprint = $this->computeFingerprint($metrics['tags']);

    // Skip if nothing changed (fingerprint + XP both match)
    if ($photo->processed_fp === $fingerprint &&
        (int)$photo->processed_xp === (int)$metrics['xp']) {
        return;
    }

    // Route to create (first time) or update (re-tag)
    if ($photo->processed_at !== null) {
        $this->doUpdate($photo, $metrics, $fingerprint);
    } else {
        $this->doCreate($photo, $metrics, $fingerprint);
    }
});
```

### MySQL upsert across timescales and locations

Each photo writes up to **40 rows**: 5 timescales (all-time, daily, weekly, monthly, yearly) x 4 location scopes (global, country, state, city) x 2 (aggregate user_id=0 + per-user user_id>0).

```php
DB::table('metrics')->upsert($rows,
    ['timescale', 'location_type', 'location_id', 'user_id', 'year', 'month', 'week', 'bucket_date'],
    [
        'uploads' => DB::raw('GREATEST(uploads + VALUES(uploads), 0)'),
        'tags'    => DB::raw('GREATEST(tags + VALUES(tags), 0)'),
        // ... same for brands, materials, custom_tags, litter, xp
    ]
);
```

Uploads delta: `+1` for create, `0` for update, `-1` for delete. `GREATEST(..., 0)` prevents negative counters.

### Redis operations happen after MySQL commit

```php
private function updateRedis(Photo $photo, array $payload, string $operation): void
{
    DB::afterCommit(function () use ($photo, $payload, $operation) {
        RedisMetricsCollector::processPhoto($photo, $payload, $operation);
    });
}
```

### Redis key patterns (cluster-safe with hash tags)

```php
RedisKeys::global()           // {g}
RedisKeys::country($id)       // {c:$id}
RedisKeys::state($id)         // {s:$id}
RedisKeys::city($id)          // {ci:$id}
RedisKeys::user($userId)      // {u:$userId}

RedisKeys::stats($scope)             // $scope:stats (HASH: uploads, tags, litter, xp, ...)
RedisKeys::hll($scope)               // $scope:hll (HyperLogLog for contributor count)
RedisKeys::objects($scope)            // $scope:obj (HASH: object_id => count)
RedisKeys::ranking($scope, $dim)      // $scope:rank:$dim (ZSET)
RedisKeys::xpRanking($scope)         // $scope:lb:xp (ZSET: user_id => xp, for leaderboards)
RedisKeys::userBitmap($userId)        // {u:$userId}:bitmap (activity bitmap)
```

### Where TagsVerifiedByAdmin fires

1. **Non-school users tag a photo:** `AddTagsToPhotoAction::updateVerification()` — dispatches immediately after summary + XP (both web and mobile use this path).
2. **Teacher approves school photos:** `TeamPhotosController::approve()` — dispatches per photo after atomic `is_public = true` update.

### Delete flow (metrics reversal)

```php
// MetricsService::deletePhoto() — called synchronously in controllers before soft-delete
// Reads processed_tags JSON, applies negative deltas, clears processed_* columns
$photo->update([
    'processed_at' => null,
    'processed_fp' => null,
    'processed_tags' => null,
    'processed_xp' => null,
]);
```

In Redis, the delete operation also prunes zero-XP members from leaderboard ZSETs:
```php
$pipe->zIncrBy(RedisKeys::xpRanking($scope), -abs($metrics['xp']), (string)$userId);
$pipe->zRemRangeByScore(RedisKeys::xpRanking($scope), '-inf', '0');
```
This keeps Redis consistent with MySQL (which filters `xp > 0`).

## Common Mistakes

- **Writing metrics outside MetricsService.** Never `DB::table('metrics')->increment(...)` or `Redis::hincrby(...)` directly.
- **Dispatching `TagsVerifiedByAdmin` before summary generation.** MetricsService reads `photo.summary` — null summary = zero metrics.
- **Comparing `processed_xp` as TINYINT.** Values above 127 overflow. Column must be UNSIGNED INT.
- **Forgetting row locking.** Always use `Photo::whereKey($id)->lockForUpdate()->first()` inside the transaction.
- **Assuming Redis is source of truth.** Redis is a cache. The `metrics` table is authoritative.
- **Including categories in `tags_count`.** Categories are groupings, not countable items. Only objects + materials + brands + custom_tags.
- **Not logging when `ProcessPhotoMetrics` can't find the photo.** `ProcessPhotoMetrics` logs a warning (`Log::warning(...)`) when the photo is not found (soft-deleted or missing). It does not throw — the job completes successfully to avoid retries.
- **Using `is_public` as the upload metrics gate.** `UploadPhotoController` gates `recordUploadMetrics()` on `$team->isSchool()`, NOT on `$photo->is_public`. Private-by-choice photos (non-school) still get immediate upload XP. Only school photos defer metrics to teacher approval.
