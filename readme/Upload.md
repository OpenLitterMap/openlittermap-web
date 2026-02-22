# OpenLitterMap v5 — Upload & Tagging Architecture

## Overview

Two distinct phases, one metrics writer:

1. **Upload** — photo + GPS → S3 + location resolution → `Photo::create()` → broadcast
2. **Tag finalization** — user/admin adds tags → `MetricsService::processPhoto()` → all metrics
3. **Delete** — `MetricsService::deletePhoto()` → reverses everything (flow deferred — see below)

**Golden rule:** `MetricsService` is the **single writer** for all metrics (MySQL + Redis). Nothing else touches metric counters.

---

## Phase 1: Upload

```
POST /v5/photos  (route not yet wired)

UploadPhotoController::__invoke()
├── MakeImageAction::run($file)              → image + EXIF
├── UploadPhotoAction::run() × 2             → S3 full + bbox
├── getCoordinatesFromPhoto($exif)           → lat, lon
├── ResolveLocationAction::run($lat, $lon)   → LocationResult DTO
├── Photo::create()                          → FKs only, no tags, no XP
├── event(ImageUploaded)                     → broadcast to real-time map
└── event(NewCountry/State/CityAdded)        → Slack + Twitter notifications
```

**Upload creates an observation.** No metrics, no XP, no leaderboards. The photo exists but has zero tags.

### `ImageUploaded` listeners (v5)

All location listeners removed (wrote to dead Redis keys). `ImageUploaded` now has **zero listeners** — broadcast is handled by the event itself via `ShouldBroadcast`.

Note: `ImageUploaded` still has `ShouldQueue` on the event, which is technically incorrect (should be on listeners, not events). Low risk but should be fixed.

---

## Phase 2: Tag Finalization

**Trigger:** `TagsVerifiedByAdmin` event (or self-verification for trusted users)

This is where `MetricsService::processPhoto()` runs via the `ProcessPhotoMetrics` listener:

```
TagsVerifiedByAdmin
  → ProcessPhotoMetrics::handle()
    → MetricsService::processPhoto()
      ├── MySQL metrics upsert (all timescales × all scopes)
      ├── Photo::update (processed_at, processed_fp, processed_tags, processed_xp)
      └── DB::afterCommit → RedisMetricsCollector::processPhoto()
```

### MySQL (`metrics` table)
- Upserts across all timescales (all-time, daily, weekly, monthly, yearly)
- Across all location scopes (global, country, state, city)
- Counters: `uploads`, `tags`, `brands`, `materials`, `custom_tags`, `litter`, `xp`
- Fingerprint-based idempotency (`processed_fp` + `processed_xp`)

### Redis (via `RedisMetricsCollector`)
- `{prefix}:stats` → `photos`, `litter`, `xp` (HINCRBY)
- `{prefix}:hll` → contributor HyperLogLog (PFADD)
- `{prefix}:contributor_ranking` → contributor ZSET (ZINCRBY)
- `{prefix}:categories` / `objects` / `materials` / `brands` / `custom_tags` → tag hashes (HINCRBY)
- `{prefix}:rank:{dimension}` → tag ranking ZSETs (ZINCRBY)
- `user:{id}:stats` → per-user uploads, xp, litter
- `user:{id}:tags` → per-user tag breakdown
- `user:{id}:bitmap` → streak tracking

### `TagsVerifiedByAdmin` listeners (v5 — current state)

| Listener | Status |
|---|---|
| `ProcessPhotoMetrics` | **Active** — calls MetricsService::processPhoto() |
| `RewardLittercoin` | **Active** — separate domain concern |
| `CompileResultsString` | **Removed** |
| `IncrementLocation` | **Removed** — replaced by MetricsService |
| `IncreaseTeamTotalLitter` | **Removed** — team metrics dropped |
| `UpdateUserCategories` | **Removed** — replaced by RedisMetricsCollector |
| `UpdateUserTimeSeries` | **Removed** — replaced by metrics table |
| `UpdateUserIdLastUpdatedLocation` | **Removed** — column dropped |

**Open issue:** `TagsVerifiedByAdmin` constructor changed to `($photo_id, $user_id, $country_id, $state_id, $city_id, $team_id)`. The caller(s) that dispatch this event haven't been updated yet.

---

## Phase 3: Delete (deferred)

```
MetricsService::deletePhoto()
├── MySQL: negative deltas across all timescales + locations
├── Redis: decrements stats, tags, rankings
├── Clears processed_at/fp/tags/xp on photo
└── (S3 cleanup via queued job)
```

`DeletePhotoMetrics` listener exists but is **parked**. Problem: `ImageDeleted` doesn't carry `photo_id`, and `MetricsService::deletePhoto()` needs the photo row to still exist. The current `ApiPhotosController::deleteImage()` hard-deletes the photo before dispatching the event.

Options: call MetricsService directly in the controller before delete, or implement soft-deletes.

### `ImageDeleted` listeners (v5 — current state)

All location listeners removed. `ImageDeleted` now has **zero listeners**. Delete flow needs redesign before `DeletePhotoMetrics` can be activated.

---

## Redis Key Alignment — ✅ Resolved

The Location model reads all keys via `RedisKeys::*`, eliminating mismatches with `RedisMetricsCollector`.

### Real-time stats (read directly from Redis)

| Accessor | Reads from | Written by |
|---|---|---|
| `total_litter_redis` | `RedisKeys::stats($scope)` → `litter` | `RedisMetricsCollector` |
| `total_photos_redis` | `RedisKeys::stats($scope)` → `photos` | `RedisMetricsCollector` |
| `total_xp` | `RedisKeys::stats($scope)` → `xp` | `RedisMetricsCollector` |
| `total_contributors_redis` | `PFCOUNT` on `RedisKeys::hll($scope)` | `RedisMetricsCollector` |
| `litter_data` | `RedisKeys::categories($scope)` | `RedisMetricsCollector` |
| `objects_data` | `RedisKeys::objects($scope)` | `RedisMetricsCollector` |
| `materials_data` | `RedisKeys::materials($scope)` | `RedisMetricsCollector` |
| `brands_data` | `RedisKeys::brands($scope)` | `RedisMetricsCollector` |
| top tags | `RedisKeys::ranking($scope, $dim)` | `RedisMetricsCollector` |

### Time-series (metrics table → cached in Redis with TTL)

| Accessor | Source of truth | Cache key | TTL |
|---|---|---|---|
| `ppm` | `metrics` table (timescale=3, monthly) | `{scope}:cache:ppm` | 15 min |
| `recent_activity` | `metrics` table (timescale=1, daily) | `{scope}:cache:recent` | 5 min |

### Contributors: HyperLogLog

Contributors use `PFCOUNT` on the HLL key instead of `SCARD` on a SET. Trade-off: ~0.81% error margin, but O(1) reads, no memory growth, and cannot go negative on deletes (HLL is append-only). For a citizen science platform this is the right call.

---

## Location Model Cleanup — ✅ Done

| Issue | Resolution |
|---|---|
| `lastUploader()` relationship | Deleted — column dropped |
| `scopeVerified()` | Deleted — column dropped |
| `scopeActive()` | Deleted — use `hasRecentActivity()` from metrics instead |
| `getTopContributors()` | Deleted — was N+1 SQL. Use `contributorRanking` ZSET instead |
| `getTotalContributorsRedisAttribute()` | Fixed — uses `PFCOUNT` on HLL key |
| `getTotalXpAttribute()` | Fixed — reads `{scope}:stats` → `xp` directly |
| `getPpmAttribute()` | Fixed — queries metrics table, cached 15 min |
| `getRecentActivityAttribute()` | Fixed — queries metrics table, cached 5 min |
| All tag/key references | Fixed — uses `RedisKeys::*` as single source of truth |

---

## EventServiceProvider (v5 — current state)

```php
protected $listen = [
    Registered::class => [
        SendEmailVerificationNotification::class,
    ],

    // ImageUploaded: zero listeners (broadcast via ShouldBroadcast on event)
    // ImageDeleted: zero listeners (delete flow deferred)

    TagsVerifiedByAdmin::class => [
        ProcessPhotoMetrics::class,
        RewardLittercoin::class,
    ],

    NewCountryAdded::class => [
        TweetNewCountry::class,
    ],

    NewStateAdded::class => [
        TweetNewState::class,
    ],

    NewCityAdded::class => [
        TweetNewCity::class,
    ],

    UserSignedUp::class => [
        SendNewUserEmail::class,
    ],

    BadgeCreated::class => [
        TweetBadgeCreated::class,
    ],
];
```

---

## Related Docs

- **PostMigrationCleanup.md** — full list of files to delete, tables to drop, Redis keys to flush
- **Locations.md** — `ResolveLocationAction`, location schema, upload controller code
- **Strategy.md** — overall status, blockers, and what's next
