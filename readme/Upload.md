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
UploadPhotoController::__invoke()
├── MakeImageAction::run($file)              → image + EXIF
├── UploadPhotoAction::run() × 2             → S3 full + bbox
├── getCoordinatesFromPhoto($exif)           → lat, lon
├── ResolveLocationAction::run($lat, $lon)   → LocationResult DTO
├── Photo::create()                          → FKs only, no tags, no XP
├── IF NOT school team photo:                → school photos skip this block
│   ├── user.increment('xp', 5)             → MySQL users.xp += 5
│   └── recordUploadMetrics($photo, 5)      → metrics table, Redis, processed_at
├── event(ImageUploaded)                     → broadcast to real-time map
└── event(NewCountry/State/CityAdded)        → Slack + Twitter notifications
```

**Upload creates an observation.** For non-school photos, 5 XP is awarded immediately — the user appears on leaderboards after upload. `recordUploadMetrics()` writes metrics rows at all scopes (xp=5, litter=0, uploads=1), updates Redis stats/leaderboards, and sets `processed_at` + `processed_xp=5` on the photo. When tags are added later, MetricsService routes to `doUpdate()` (delta from upload baseline).

**Private-by-choice photos** (user sets `public_photos=false`) still get immediate upload XP. The photo just doesn't appear in `Photo::public()` queries (global map, points API, admin queue). The metrics gate checks school team membership, NOT `is_public`.

**School photos** (school team → `is_public=false` enforced by PhotoObserver) skip XP and metrics entirely at upload time. Both are deferred until teacher approval, when `processPhoto()` → `doCreate()` handles the full XP (upload + tag) in one pass.

### `ImageUploaded` listeners (v5)

All location listeners removed (wrote to dead Redis keys). `ImageUploaded` now has **zero listeners** — broadcast is handled by the event itself via `ShouldBroadcast`.

~~Note: `ImageUploaded` still has `ShouldQueue` on the event.~~ **Fixed** — `ShouldQueue` removed from event class. Only `ShouldBroadcast` remains.

### Upload Validation Error Contract

`UploadPhotoRequest::failedValidation()` returns a structured error envelope instead of Laravel's default format:

```json
{ "success": false, "error": "no_gps", "message": "Sorry, no GPS on this one.", "errors": { ... } }
```

`resolveErrorCode()` maps validation messages to typed `error` codes: `no_exif`, `no_gps`, `no_datetime`, `duplicate`, `invalid_coordinates`, `validation_error`. This lets mobile clients handle specific failure modes programmatically.

### EXIF Validation (web upload)

`UploadPhotoRequest::after()` validates EXIF before the controller runs:

1. **EXIF must exist** — `exif_read_data()` must return non-empty array
2. **DateTime must exist** — `getDateTimeForPhoto()` checks `DateTimeOriginal` → `DateTime` → `FileDateTime`. If all missing → 422 rejection. Controller has `?? Carbon::now()` as belt-and-suspenders fallback.
3. **GPS must exist** — `GPSLatitudeRef`, `GPSLatitude`, `GPSLongitudeRef`, `GPSLongitude` must all be non-empty
4. **GPS conversion must succeed** — `dmsToDec()` guards against zero denominators in all 6 DMS components (degrees/minutes/seconds for lat and lon). Returns `null` on malformed data → validation rejects.
5. **0,0 coordinates are accepted** — photos at 0,0 latitude/longitude are allowed. Future feature: manual coordinate reassignment for mislocated photos.
6. **Duplicate check** — same user + same EXIF datetime → 422 rejection

### EXIF Validation (mobile upload — explicit coordinates)

`UploadPhotoController` accepts optional `lat`, `lon`, `date` fields. When all three are present, EXIF GPS/datetime validation is skipped:

1. **(0, 0) coordinates rejected** — `lat == 0 && lon == 0` → 422 (Null Island guard)
2. **Date parsed via Carbon** — Unix timestamps (seconds) or ISO 8601 strings accepted
3. **Duplicate check** — same user + same explicit `date` → 422 rejection
4. **Platform set to `'mobile'`** — distinguishes from web (EXIF-based) uploads

### `picked_up` semantics

**`photos.remaining` is deprecated** — will be dropped post-migration. The mobile app no longer sends `picked_up` at photo level.

**`photo_tags.picked_up` is the source of truth:**
- Set per-tag when tags are submitted via `POST /api/v3/tags`
- Nullable: `true` (collected), `false` (left behind), `null` (not specified)
- Controls XP bonus: `+5 × quantity` per tag where `picked_up=true` AND tag has an object (`litter_object_id`)
- Brand-only, material-only, and custom-only tags (no `litter_object_id`) do not get the bonus
- The v5 migration script sets `photo_tags.picked_up = !$photo->remaining` on each migrated tag

### Helper functions (`app/Helpers/helpers.php`)

| Function | Input | Output | Edge cases |
|---|---|---|---|
| `getDateTimeForPhoto($exif)` | EXIF array | `Carbon` or `null` | Falls back through 3 fields, returns null if none found |
| `getCoordinatesFromPhoto($exif)` | EXIF array | `[lat, lon]` or `null` | Delegates to `dmsToDec()` |
| `dmsToDec($lat, $lon, $lat_ref, $long_ref)` | DMS arrays + refs | `[lat, lon]` or `null` | Guards against zero denominators in all 6 components |

---

## Phase 2: Tag Finalization

**Trigger:** `TagsVerifiedByAdmin` event (or self-verification for trusted users)

**Note:** PhotoTags can be object-based (with a CLO) or extra-tag-only (brand-only, material-only, custom-only with null CLO). Extra-tag-only tags earn their own XP (brand=3, material=2, custom=1) but do not count toward `totalLitter` or receive object XP. See `AddTagsToPhotoAction::createExtraTagOnly()`.

**Transaction:** `AddTagsToPhotoAction::run()` wraps all tag creation + summary generation + verification update in a single `DB::transaction()`. This ensures photo tags, summary JSON, XP, and `TagsVerifiedByAdmin` dispatch are all atomic — a partial failure cannot leave the photo in an inconsistent state.

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
- `{prefix}:lb:xp` → leaderboard ranking ZSET (ZINCRBY user_id by XP)
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

**Note:** `TagsVerifiedByAdmin` constructor takes `($photo_id, $user_id, $country_id, $state_id, $city_id, $team_id)`. It is dispatched from:
1. `AddTagsToPhotoAction::updateVerification()` — for trusted users/teams (immediate verification)
2. `TeamPhotosController::approve()` — for school teams (teacher approval triggers metrics)
3. `AdminController::verify()` — manual admin approval of a tagged photo
4. `AdminController::updateDelete()` — admin re-tags and approves (first-time approval only)

See **SchoolPipeline.md** for the full school approval flow.

---

## Phase 2b: Replace Tags (edit mode — active)

```
PUT /api/v3/tags  (auth:sanctum)

PhotoTagsController::update()
├── Delete all existing PhotoTags + PhotoTagExtraTags
├── Reset: summary=null, xp=0, verified=0
├── AddTagsToPhotoAction::run()           → regenerates summary, XP, fires event
└── MetricsService::processPhoto()        → doUpdate() calculates deltas vs old processed_tags
```

Frontend: `/tag?photo=<id>` loads a specific photo. If it has tags, enters edit mode (PUT). If untagged, uses normal tagging (POST). Security: `ReplacePhotoTagsRequest` enforces ownership. `GET_SINGLE_PHOTO` calls `/api/v3/user/photos` which filters by `Auth::user()->id`.

---

## Phase 3: Delete (active)

```
MetricsService::deletePhoto()
├── MySQL: negative deltas across all timescales + locations
├── Redis: decrements stats, tags, rankings, leaderboard ZSETs
├── Clears processed_at/fp/tags/xp on photo
└── Photo::delete() soft-deletes (row preserved, excluded by Photo::public() scope)
```

The `Photo` model uses `SoftDeletes`. Controllers call `MetricsService::deletePhoto()` **before** `$photo->delete()`. This preserves the row for metric reversal (reads `processed_tags` JSON, applies negative deltas), then soft-deletes.

### `ImageDeleted` listeners (v5 — current state)

All location listeners removed. `ImageDeleted` now has **zero listeners**. Metric reversal happens synchronously in the controller via `MetricsService::deletePhoto()`, not through event listeners.

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
    // ImageDeleted: zero listeners (metrics reversal is synchronous in controller)

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

- **Leaderboards.md** — Leaderboard system (Redis ZSETs + MySQL per-user metrics)
- **Metrics.md** — MetricsService internals, fingerprinting, Redis key patterns
- **PostMigrationCleanup.md** — full list of files to delete, tables to drop, Redis keys to flush
- **Locations.md** — `ResolveLocationAction`, location schema, upload controller code
