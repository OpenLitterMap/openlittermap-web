# OpenLitterMap v5 — Upload & Tagging Architecture

## Overview

Two distinct phases, one metrics writer:

1. **Upload** — photo + GPS → S3 + location resolution → `Photo::create()` → broadcast
2. **Tag finalization** — user/admin adds tags → `MetricsService::processPhoto()` → all metrics
3. **Delete** — `MetricsService::deletePhoto()` → reverses everything

**Golden rule:** `MetricsService` is the **single writer** for all metrics (MySQL + Redis). Nothing else touches metric counters.

---

## Phase 1: Upload

```
POST /api/photos

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

| Listener | v5 status | Reason |
|---|---|---|
| `AddLocationContributor` | **Delete** | Writes to dead keys (`country:{id}:user_ids`). `RedisMetricsCollector` handles contributors at tag time. |
| `IncreaseLocationTotalPhotos` | **Delete** | Writes to dead keys (`country:{id}` → `total_photos`). `RedisMetricsCollector` increments `{prefix}:stats` → `photos` at tag time. |
| `IncreaseTeamTotalPhotos` | **Keep for now** | Team metrics not yet in MetricsService. Migrate later. |

After cleanup, `ImageUploaded` only triggers:
- Real-time map broadcast (WebSocket)
- `IncreaseTeamTotalPhotos` (temporary, until teams migrate to MetricsService)

---

## Phase 2: Tag Finalization

**Trigger:** `TagsVerifiedByAdmin` event (or self-verification for trusted users)

This is where `MetricsService::processPhoto()` must run. It is the **single writer** for:

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

### `TagsVerifiedByAdmin` listeners (v5)

| Listener | v5 status | Reason |
|---|---|---|
| `CompileResultsString` | **Review** | Need to check if still consumed by frontend |
| `IncrementLocation` | **Delete** | Replaced by `MetricsService` + `RedisMetricsCollector` |
| `IncreaseTeamTotalLitter` | **Keep for now** | Team metrics not yet in MetricsService |
| `RewardLittercoin` | **Keep** | Separate domain concern |
| `UpdateUserCategories` | **Delete** | Replaced by `RedisMetricsCollector::updateUserMetrics()` |
| `UpdateUserTimeSeries` | **Delete** | Replaced by `metrics` table timescales |
| `UpdateUserIdLastUpdatedLocation` | **Delete** | Column dropped in migration |

After cleanup, `TagsVerifiedByAdmin` triggers:
- `MetricsService::processPhoto()` ← **add this**
- `CompileResultsString` (if still needed)
- `IncreaseTeamTotalLitter` (temporary)
- `RewardLittercoin`

---

## Phase 3: Delete

```
MetricsService::deletePhoto()
├── MySQL: negative deltas across all timescales + locations
├── Redis: decrements stats, tags, rankings
├── Clears processed_at/fp/tags/xp on photo
└── (S3 cleanup via queued job)
```

### `ImageDeleted` listeners (v5)

| Listener | v5 status | Reason |
|---|---|---|
| `RemoveLocationContributor` | **Delete** | Writes to dead keys. MetricsService handles it. |
| `DecreaseLocationTotalPhotos` | **Delete** | Writes to dead keys. MetricsService handles it. |
| `DecreaseTeamTotalPhotos` | **Keep for now** | Team metrics not yet in MetricsService |

---

## Redis Key Alignment: Location Model ↔ RedisMetricsCollector — ✅ Resolved

The Location model has been rewritten to read all keys via `RedisKeys::*`, eliminating mismatches.

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

### Time-series (Option C: metrics table → cached in Redis with TTL)

| Accessor | Source of truth | Cache key | TTL |
|---|---|---|---|
| `ppm` | `metrics` table (timescale=3, monthly) | `{scope}:cache:ppm` | 15 min |
| `recent_activity` | `metrics` table (timescale=1, daily) | `{scope}:cache:recent` | 5 min |

### Contributors: HyperLogLog

Contributors now use `PFCOUNT` on the HLL key instead of `SCARD` on a SET. Trade-off: ~0.81% error margin, but O(1) reads, no memory growth, and cannot go negative on deletes (HLL is append-only). For a citizen science platform this is the right call — exact contributor counts matter less than tag/litter accuracy.

---

## Files to delete

| File | Reason |
|---|---|
| `App\Helpers\Post\UploadHelper` | Replaced by `ResolveLocationAction` |
| `App\Actions\Locations\UpdateLeaderboardsForLocationAction` | Replaced by `MetricsService` |
| `App\Actions\Locations\UpdateLeaderboardsXpAction` | Called only by above |
| `App\Actions\Locations\AddContributorForLocationAction` | Writes to dead keys |
| `App\Actions\Locations\UpdateTotalPhotosForLocationAction` | Writes to dead keys |
| `App\Listeners\Locations\AddLocationContributor` | Uses dead action above |
| `App\Listeners\Locations\IncreaseLocationTotalPhotos` | Uses dead action above |
| `App\Listeners\Locations\DecreaseLocationTotalPhotos` | Uses dead action above |
| `App\Listeners\Locations\RemoveLocationContributor` | Uses dead action above |
| `App\Listeners\Locations\User\UpdateUserIdLastUpdatedLocation` | Column dropped |
| `App\Listeners\AddTags\IncrementLocation` | Replaced by MetricsService |
| `App\Listeners\User\UpdateUserCategories` | Replaced by RedisMetricsCollector |
| `App\Listeners\User\UpdateUserTimeSeries` | Replaced by metrics table |
| `App\Listeners\UpdateTimes\IncrementCountryMonth` | Replaced by metrics table |
| `App\Listeners\UpdateTimes\IncrementStateMonth` | Replaced by metrics table |
| `App\Listeners\UpdateTimes\IncrementCityMonth` | Replaced by metrics table |
| `App\Events\Photo\IncrementPhotoMonth` | Event with no remaining listeners |

## Location model cleanup — ✅ Done

| Issue | Resolution |
|---|---|
| `lastUploader()` relationship | Deleted — `user_id_last_uploaded` column dropped |
| `scopeVerified()` | Deleted — `manual_verify` column dropped |
| `scopeActive()` | Deleted — use `hasRecentActivity()` from metrics instead |
| `getTopContributors()` | Deleted — was doing N+1 SQL queries per user. Use `contributorRanking` ZSET instead |
| `getTotalContributorsRedisAttribute()` | Fixed — now uses `PFCOUNT` on HLL key |
| `getTotalXpAttribute()` | Fixed — reads `{scope}:stats` → `xp` directly instead of looping 24 monthly hashes |
| `getPpmAttribute()` | Fixed — Option C: queries metrics table (timescale=3), cached 15 min |
| `getRecentActivityAttribute()` | Fixed — Option C: queries metrics table (timescale=1), cached 5 min |
| All tag accessors | Fixed — reads via `RedisKeys::categories/objects/materials/brands()` |
| All key references | Fixed — uses `RedisKeys::*` as single source of truth for key naming |

---

## Updated EventServiceProvider (v5 target)

```php
protected $listen = [
    Registered::class => [
        SendEmailVerificationNotification::class,
    ],

    ImageUploaded::class => [
        // Real-time map broadcast (handled by event itself)
        // Team photo count (temporary — migrate to MetricsService later)
        IncreaseTeamTotalPhotos::class,
    ],

    ImageDeleted::class => [
        // MetricsService::deletePhoto() called directly, not via listener
        DecreaseTeamTotalPhotos::class,
    ],

    TagsVerifiedByAdmin::class => [
        // THE metrics moment — single writer
        // MetricsService::processPhoto() — wire this in
        CompileResultsString::class,  // review if still needed
        IncreaseTeamTotalLitter::class,  // temporary
        RewardLittercoin::class,
    ],

    NewCountryAdded::class => [
        NotifySlackOfNewCountry::class,
        TweetNewCountry::class,
    ],

    NewStateAdded::class => [
        NotifySlackOfNewState::class,
        TweetNewState::class,
    ],

    NewCityAdded::class => [
        NotifySlackOfNewCity::class,
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

## What's next

1. **Wire `MetricsService::processPhoto()` into `TagsVerifiedByAdmin`** — need to see the tagging controller/listener that persists tags and fires this event
2. **Clean up `EventServiceProvider`** — remove dead listeners
3. **Delete 17 files** listed above
4. **Flush old Redis keys** — one-off artisan command to clear all `country:*`, `state:*`, `city:*` legacy patterns
5. **Rebuild Redis from metrics table** — artisan command to repopulate `RedisMetricsCollector` keys from MySQL
