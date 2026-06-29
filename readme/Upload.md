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

`resolveErrorCode()` maps validation messages to typed `error` codes: `no_exif`, `no_gps`, `no_datetime`, `invalid_coordinates`, `validation_error`. This lets mobile clients handle specific failure modes programmatically. (Duplicates are no longer an error — see below.)

### Idempotent Upload (duplicate handling)

Duplicate detection (`user_id + datetime`) runs in `UploadPhotoController::__invoke()`, **not** validation. A duplicate returns the **existing** `photo_id` with `{ success: true, photo_id, already_uploaded: true, tagged: <bool>, xp_awarded: 0 }` so a lost-response retry can recover with no app update — and with no side effects (no second `Photo`, no S3 write, no XP/metrics). `tagged` reflects whether the existing photo has a `summary`. The lookup is skipped for participant uploads (students share the facilitator's `user_id`).

### EXIF Validation (web upload)

`UploadPhotoRequest::after()` validates EXIF before the controller runs:

1. **EXIF must exist** — `exif_read_data()` must return non-empty array
2. **DateTime must exist** — `getDateTimeForPhoto()` checks `DateTimeOriginal` → `DateTime` → `FileDateTime`. If all missing → 422 rejection. Controller has `?? Carbon::now()` as belt-and-suspenders fallback.
3. **GPS must exist** — `GPSLatitudeRef`, `GPSLatitude`, `GPSLongitudeRef`, `GPSLongitude` must all be non-empty
4. **GPS conversion must succeed** — `dmsToDec()` guards against zero denominators in all 6 DMS components (degrees/minutes/seconds for lat and lon). Returns `null` on malformed data → validation rejects.
5. **0,0 coordinates are accepted** — photos at 0,0 latitude/longitude are allowed. Future feature: manual coordinate reassignment for mislocated photos.

Duplicate detection happens in the controller (idempotent — see "Idempotent Upload" above), not in `after()`.

### EXIF Validation (mobile upload — explicit coordinates)

`UploadPhotoController` accepts optional `lat`, `lon`, `date` fields. When all three are present, EXIF GPS/datetime validation is skipped:

1. **(0, 0) coordinates rejected** — `lat == 0 && lon == 0` → 422 (Null Island guard)
2. **Date parsed via Carbon** — Unix timestamps (seconds) or ISO 8601 strings accepted
3. **Duplicate handling** — same user + same explicit `date` → idempotent: returns the existing `photo_id` (see "Idempotent Upload" above), not a 422
4. **Platform set to `'mobile'`** — distinguishes from web (EXIF-based) uploads

### HEIC / HEIF handling

iPhones (and newer Android cameras) upload HEIC. Two things make this work:

1. **Validation skip.** Laravel's `image` rule excludes HEIC and `dimensions` uses `getimagesize()` (returns `false` for HEIC), so both would reject genuine HEIC. `UploadPhotoRequest::rules()` detects HEIC via `MakeImageAction::isHeic()` (extension/MIME **and** ftyp magic bytes — catches iOS HEIC sent as `.jpg`) and **drops `image` + `dimensions` for HEIC only**. `mimes` (content-sniffed) and `max:20480` stay on. `UploadPhotoRequest::after()` **also skips the raw `exif_read_data()` GPS/datetime checks for HEIC** — PHP can't read HEIC EXIF pre-conversion (returns `false`), so for web HEIC that validation is deferred to the controller (see "web-mode HEIC" below). Using the same `isHeic()` the converter uses keeps the validation-skip in lockstep with conversion.
2. **Conversion.** `MakeImageAction::convertViaHeifConvert()` runs **`heif-convert -q 92 {input} {output}`** via Laravel's `Process` facade (array command, 60s timeout) to convert HEIC → JPEG before storage, since the Intervention GD driver can't decode HEIC. `heif-convert` ships in the **`libheif-examples`** package and decodes directly through libheif.

**Why `heif-convert` and not ImageMagick `convert`.** The old path shelled out to ImageMagick 6 `convert`, whose HEIC delegate is unreliable across HEIC variants. Two production failure signatures (Sentry, Jun 2026):

```
# 2024-era iPhone HEIC on an outdated delegate:
convert-im6.q16: Invalid input: Unspecified: Metadata not correctly assigned to image (2.0) ... @ error/heic.c/IsHEIFSuccess/139.

# 2026 Xiaomi HEIC (brands mif1, MiHE, MiPr, miaf, MiHB, heic):
convert: no encode delegate for this image format 'HEIC'
```

Diagnosed on production (Ubuntu 24.04, libheif 1.17.6-1ubuntu4.3, ImageMagick 6): `heif-convert` succeeds on both samples where `convert` fails. libheif's own CLI sidesteps the flaky IM HEIC delegate entirely. **Do not reintroduce `convert`/`magick`** — there is a single conversion path, no ImageMagick fallback.

**Metadata & orientation (verified against libheif v1.17.6 `examples/encoder_jpeg.cc`, `examples/heif_convert.cc`):**
- **EXIF (incl. GPS), XMP and ICC are embedded in the output JPEG by default.** (`--with-exif` only controls a *sidecar dump*, not embedding — we don't pass it.) So our downstream EXIF read still finds GPS/datetime.
- **Orientation is baked**: pixels are rotated upright and EXIF Orientation is rewritten to `1`, so the existing `Image::make($converted)->orientate()` is a harmless no-op.
- **Output path is deterministic** for single-image HEICs (exact filename passed, no `-1` suffix). Multi-image HEICs (bursts/Live Photos) would not produce the expected file → caught by the `!file_exists()` guard → fails safe.
- Default `heif-convert` JPEG quality is 90; we pass `-q 92` to match the old ImageMagick output quality.

**Failure preservation.** On process failure (non-zero exit) **or** a missing output file, `MakeImageAction`:
- **moves** (not copies) the temp input to `storage/app/heic_failed/{hash}.heic` — each file there is a diagnostic sample of a HEIC variant the server could not decode,
- `Log::error()`s with `original_name`, `exit_code`, combined stdout+stderr, and `preserved_path` (captured by Sentry),
- throws `HeicConversionException`. The `finally{}` cleanup skips the preserved file (it deletes the leftover JPEG and only the *unpreserved* temp input).

Preservation applies to **any** shell-conversion failure, including non-HEIC files that reach the converter via the GD-fallback path — acceptable noise; every file in `heic_failed/` is a sample worth keeping.

**Current behaviour on failure: typed 422 (INTERIM).** `UploadPhotoController` catches `HeicConversionException` and returns `{ success: false, error: 'heic_conversion_failed', message }` with HTTP **422** (was a hard 500). Mobile clients read `error` to show a friendly message. Verified by `HeicUploadTest::test_heic_conversion_failure_returns_graceful_422` and `test_heic_conversion_process_failure_preserves_sample_and_returns_422` (the latter drives the real `heif-convert` path with `Process::fake()` and pins the `heic_failed/` preservation + `heic_images/` cleanup).

> **⚠️ INTERIM — failing the upload is not the target behaviour.** The standing product decision is that **HEIC uploads must never fail**. Target: *accept-on-failure* — store the original HEIC, create the Photo record, flag it, and reprocess later via a future `olm:reprocess-failed-heic` command. The diagnostic samples in `heic_failed/` are the raw material for designing that reprocessor. The 422 above is the placeholder until accept-on-failure ships as a follow-up.

**Web-mode HEIC (resolved 2026-06-29, v5.12.13).** Web HEIC (no explicit `lat`/`lon`/`date`) previously failed: `after()` ran `exif_read_data()`/GPS checks on the raw HEIC, which PHP can't read (returns `false`) → false `no_exif` 422 even on a photo with valid GPS. Now `after()` skips those for HEIC, and `UploadPhotoController`'s web branch extracts GPS/datetime from the **post-conversion** EXIF (`heif-convert` embeds GPS/datetime into the JPEG), returning a typed `no_gps` 422 only if coordinates are genuinely missing. The client-side `FilePond` gate was also fixed (`acceptedFileTypes` MIME entries + a `fileValidateTypeDetectType` resolver for the empty `file.type` browsers report for HEIC) — previously HEIC was rejected before any request fired. **Both web and mobile HEIC paths are now supported.**

#### Server dependencies

HEIC conversion requires **`heif-convert`** (from `libheif-examples`). **Verified on production (2026-06-29):** Ubuntu **24.04.4 LTS**, libheif **1.17.6-1ubuntu4.4**, binary at **`/usr/bin/heif-convert`** (on the web/queue user's PATH). Laravel's `Process` facade — the exact mechanism `MakeImageAction` uses — runs it successfully from the app (`Process::run('heif-convert --version')` → OK).

**Ubuntu 24.04 uses libheif's plugin architecture** — `heif-convert` decodes nothing on its own; codec plugins must be installed too. iPhone HEICs are **HEVC**, so **`libheif-plugin-libde265` is required** (without it: `no decoding plugin installed for this compression format`). Production has it, plus the AOM (AVIF) plugins:

```bash
sudo apt-get update
sudo apt-get install libheif-examples            # provides /usr/bin/heif-convert
sudo apt-get install libheif-plugin-libde265     # HEVC decode — REQUIRED for iPhone HEIC
sudo apt-get install libheif-plugin-aomdec       # AVIF/AV1 decode (newer Android)
which heif-convert                               # confirm it's on PATH for the web/queue user

# Smoke test against a real preserved sample:
heif-convert -q 92 storage/app/heic_failed/<hash>.heic /tmp/out.jpg && file /tmp/out.jpg
```

If `heif-convert` (or the libde265 plugin) is ever missing, conversion fails and HEIC uploads land in `heic_failed/` — treat their presence as a hard deploy/provisioning requirement.

**Binary path is configurable** via `config('services.heif_convert.path')` (env `HEIF_CONVERT_PATH`, default `heif-convert` resolved via PATH). **Production needs no override** — `/usr/bin` is on php-fpm's PATH. **Local Valet does**: its php-fpm runs under launchd with a restricted PATH that omits `/opt/homebrew/bin`, so after `brew install libheif` set `HEIF_CONVERT_PATH=/opt/homebrew/bin/heif-convert` in local `.env` (not `.env.testing`).

#### Ops notes — `heic_images/` and `heic_failed/`

- `storage/app/heic_images/` — transient working dir for in-flight conversions (input copy + output JPEG). Files here are deleted in the `finally{}` of each conversion.
- `storage/app/heic_failed/` — preserved unconvertible HEICs, one per failed conversion. **Each is a diagnostic sample — do not bulk-delete without inspecting first.** They are the inputs the future `olm:reprocess-failed-heic` command will replay.
- Both dirs are **per-release local storage, not shared/persistent storage** — temp files do not survive a deploy. That's acceptable for in-flight conversions, but means `heic_failed/` samples should be pulled off the box (or the dir symlinked to shared storage) if you want them to outlive a release. They are created on demand by `File::makeDirectory()`.

**Longer-term follow-up (mobile app — separate repo):** the most reliable fix removes the server's HEIC decode dependency entirely — have the iOS app export/convert HEIC → JPEG before upload (or capture in "Most Compatible" format). Tracked as a follow-up; not done here.

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

**Idempotent POST guard:** `POST /api/v3/tags` **appends** tags (it does not replace). Since ordinary non-trusted users stay at `verified=0` after tagging, the `verified >= 1` authorize gate does not catch them, so a retried POST (lost response) would double-tag. `PhotoTagsController::store()` therefore checks `summary` first: if the photo is already tagged (`summary !== null`) it returns an idempotent no-op `{ success: true, already_tagged: true, photoTags }` without re-adding. To re-tag/edit an already-tagged photo, use `PUT /api/v3/tags` (replace — see Phase 2b).

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

`update()` also stamps `onboarding_completed_at` on the first non-empty tag submission (parity with `store()`), so the mobile auto-upload flow can tag exclusively via PUT. On a never-tagged photo, PUT's reset is a no-op and it runs the same `AddTagsToPhotoAction::run(..., skipVerification=false)` as POST — identical `verified`/XP/metrics for trusted, school, and ordinary users.

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
