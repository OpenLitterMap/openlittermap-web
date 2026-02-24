# OpenLitterMap v5 — MetricsService

## Overview

`MetricsService` is the **single writer** for all metrics in OpenLitterMap v5. It writes to both MySQL (source of truth) and Redis (derived cache) and handles creates, updates, and deletes with full idempotency.

For when and how MetricsService gets called, see **Upload.md**.
This document covers **how it works internally**.

---

## Three Operations

### `processPhoto($photo)` — Create or Update

Called when tags are verified. Determines whether this is a first-time process or a re-process based on `processed_at`.

```
processPhoto($photo)
├── Lock row (SELECT FOR UPDATE)
├── Extract metrics from photo.summary JSON
├── Compute fingerprint (xxh128 of normalized tags)
├── Compare fingerprint + XP to stored values
│   └── If identical → return (nothing changed)
├── If processed_at is NULL → doCreate()
└── If processed_at exists → doUpdate()
```

### `deletePhoto($photo)` — Reverse

Called before a photo is soft-deleted. Calculates negative deltas from stored `processed_tags` and reverses all metrics.

```
deletePhoto($photo)
├── Lock row
├── Read stored processed_tags + processed_xp
├── Calculate negative metrics
├── Upsert negative deltas (GREATEST prevents going below 0)
├── Clear processed_at/fp/tags/xp on photo
└── Update Redis (decrements)
```

**Status:** Active. The `Photo` model uses `SoftDeletes`. Controllers call `MetricsService::deletePhoto()` before `$photo->delete()`, which preserves the row for metric reversal. The `Photo::public()` scope automatically excludes soft-deleted records.

---

## Fingerprinting & Idempotency

Every time MetricsService processes a photo, it computes a fingerprint from the normalized tag data:

```php
// Tags sorted for consistency, then hashed
$json = json_encode($tags, JSON_NUMERIC_CHECK);
$fingerprint = substr(hash('xxh128', $json), 0, 16);
```

The fingerprint is stored in `photos.processed_fp`. On subsequent calls:

- **Same fingerprint + same XP** → skip entirely (no work)
- **Different fingerprint or XP** → route to `doUpdate()` which calculates deltas

This means `processPhoto()` is safe to call multiple times on the same photo. The migration script relies on this — if a photo is re-processed, only the differences are applied.

### Stored processing state on photos

| Column | Type | Purpose |
|---|---|---|
| `processed_at` | TIMESTAMP | When metrics were last written. NULL = never processed. |
| `processed_fp` | VARCHAR(32) | xxh128 fingerprint of normalized tags at last processing. |
| `processed_tags` | TEXT | JSON snapshot of tags at last processing. Used for delta calculation. |
| `processed_xp` | INT UNSIGNED | XP value at last processing. Compared alongside fingerprint. |

---

## Metrics Extraction

`extractMetricsFromPhoto()` reads the photo's `summary` JSON and flattens it into countable dimensions:

```php
// Input: photo.summary JSON
{
    "tags": {
        "2": {                          // category_id
            "15": {                     // object_id
                "quantity": 5,
                "materials": {"3": 5},  // material_id: count
                "brands": {},           // empty (brands deferred)
                "custom_tags": {}
            }
        }
    }
}

// Output:
[
    'tags' => [
        'categories' => [2 => 5],
        'objects' => [15 => 5],
        'materials' => [3 => 5],
        'brands' => [],
        'custom_tags' => [],
    ],
    'tags_count' => 10,      // objects + materials + brands + custom (NOT categories)
    'brands_count' => 0,
    'materials_count' => 5,
    'custom_tags_count' => 0,
    'litter' => 5,           // sum of object quantities
    'xp' => 15,              // from photo.xp (set by GeneratePhotoSummaryService)
]
```

**Important:** `tags_count` excludes categories to avoid double-counting. An object like `butts: 5` is counted once in objects, not again in the smoking category total.

---

## Delta Calculation (Updates)

When a photo is re-processed (tags changed), MetricsService computes deltas between old and new:

```php
// Old stored: {objects: {15: 5}, materials: {3: 5}}
// New current: {objects: {15: 3, 20: 2}, materials: {3: 3}}

// Deltas:
{
    objects: {15: -2, 20: +2},    // 3 butts instead of 5, +2 new object
    materials: {3: -2},            // 3 plastic instead of 5
}
```

Only non-zero deltas are written. This means updates are efficient — changing one tag on a photo with 50 tags only writes the differences.

---

## MySQL: Time-Series Upserts

### `metrics` table schema

```sql
-- Composite unique key
(timescale, location_type, location_id, user_id, year, month, week, bucket_date)

-- Additive counters
uploads, tags, brands, materials, custom_tags, litter, xp
```

### Timescales

| Value | Meaning | bucket_date | year/month/week |
|---|---|---|---|
| 0 | All-time | `1970-01-01` | `0/0/0` |
| 1 | Daily | `2024-03-15` | year/month/ISO week |
| 2 | Weekly (ISO) | Monday of week | ISO year/month/ISO week |
| 3 | Monthly | 1st of month | year/month/0 |
| 4 | Yearly | Jan 1st | year/0/0 |

### Location hierarchy

Every photo writes to up to 4 location scopes:

| LocationType | ID |
|---|---|
| `Global` (0) | 0 |
| `Country` (1) | `photo.country_id` |
| `State` (2) | `photo.state_id` |
| `City` (3) | `photo.city_id` |

### Rows per photo

5 timescales × 4 location scopes × 2 (aggregate + per-user) = **up to 40 rows** per `processPhoto()` call.

### Upload delta logic

| Operation | `uploads` delta |
|---|---|
| Create (first process) | +1 |
| Update (re-process) | 0 |
| Delete | -1 |

### GREATEST prevents negatives

```sql
uploads = GREATEST(uploads + VALUES(uploads), 0)
```

If a delete pushes a counter below 0 (e.g., due to a bug or race condition), `GREATEST` clamps it to 0.

---

## Redis: Derived Aggregates

After the MySQL transaction commits, `RedisMetricsCollector::processPhoto()` is called via `DB::afterCommit()`. This ensures Redis is only updated if MySQL succeeded.

```php
private function updateRedis(Photo $photo, array $payload, string $operation): void
{
    DB::afterCommit(function() use ($photo, $payload, $operation) {
        RedisMetricsCollector::processPhoto($photo, $payload, $operation);
    });
}
```

The `$operation` parameter (`create`, `update`, `delete`) tells `RedisMetricsCollector` how to handle the payload:

- **create** — increment stats, add to HLL, increment tag hashes and rankings
- **update** — apply deltas (can be positive or negative)
- **delete** — decrement stats, decrement tag hashes and rankings

Redis is a derived cache — rebuildable from the `metrics` table at any time via the `metrics:rebuild-redis` ops command (not required for go-live).

### Redis keys written per scope

| Key pattern | Redis type | Operation |
|---|---|---|
| `{scope}:stats` | HASH | HINCRBY `photos`, `litter`, `xp` |
| `{scope}:hll` | HyperLogLog | PFADD user_id |
| `{scope}:contributor_ranking` | ZSET | ZINCRBY user_id by XP |
| `{scope}:categories` | HASH | HINCRBY category_id by count |
| `{scope}:objects` | HASH | HINCRBY object_id by count |
| `{scope}:materials` | HASH | HINCRBY material_id by count |
| `{scope}:brands` | HASH | HINCRBY brand_id by count |
| `{scope}:custom_tags` | HASH | HINCRBY custom_tag_id by count |
| `{scope}:rank:objects` | ZSET | ZINCRBY object_id by count |
| `{scope}:rank:materials` | ZSET | ZINCRBY material_id by count |
| `{scope}:rank:brands` | ZSET | ZINCRBY brand_id by count |
| `{scope}:lb:xp` | ZSET | ZINCRBY user_id by XP (leaderboard ranking) |
| `user:{id}:stats` | HASH | HINCRBY uploads, xp, litter |
| `user:{id}:tags` | HASH | HINCRBY per-tag breakdown |
| `user:{id}:bitmap` | BITMAP | SETBIT for streak tracking |

### Scope prefixes

```
global              → LocationType::Global
country:{id}        → LocationType::Country
state:{id}          → LocationType::State
city:{id}           → LocationType::City
```

---

## Row Locking

Both `processPhoto()` and `deletePhoto()` use `lockForUpdate()` within a DB transaction:

```php
$photo = Photo::whereKey($photo->id)->lockForUpdate()->first();
```

This prevents two concurrent requests (e.g., admin verify + queue retry) from both reading the same `processed_tags`, computing the same delta, and double-counting.

---

## Code Review Notes

1. **`processed_xp` is INT UNSIGNED** — fixed. Migration and MigrationScript both use `INT UNSIGNED` (0–4,294,967,295). No overflow risk.

2. **Per-user metrics rows** — `buildTimeSeriesRows()` produces TWO rows per timescale × location: aggregate (`user_id=0`) and per-user (`user_id=$photo->user_id`). Per-user rows power time-filtered leaderboards (see `readme/Leaderboards.md`).

3. **`getRedisScopes()` method exists but is unused** — the `updateRedis()` method passes the photo to `RedisMetricsCollector` which computes scopes internally. Dead code, can be removed.

4. **Weekly ISO year** — uses `$timestamp->format('o')` for ISO year (correct) and `$timestamp->format('W')` for ISO week (correct). Edge case: week 1 of January may belong to the previous ISO year. The code handles this correctly.

5. **`GREATEST` on upsert** — prevents negative counters on deletes, which is defensive. Trade-off: if a bug causes over-counting, deletes won't fully reverse it. Acceptable — better than negative counts on a public dashboard.

6. **`extractMetricsFromPhoto` reads `$photo->summary`** — this means the summary JSON must be populated BEFORE `processPhoto()` is called. The migration script does `updateTags($photo)` then `$photo->refresh()` then `processPhoto($photo)` — the refresh ensures the summary is loaded. For the live tagging flow, `AddTagsToPhotoAction` must write the summary before `TagsVerifiedByAdmin` fires.

---

## Related Docs

| Document | Covers |
|---|---|
| **Upload.md** | When MetricsService runs (pipeline), EventServiceProvider, Redis key alignment, location model |
| **Tags.md** | Summary JSON structure, XP calculation, tag hierarchy |
| **MigrationScript.md** | How the migration script calls MetricsService per photo |
| **Leaderboards.md** | Redis ZSETs for all-time rankings, MySQL per-user metrics for time-filtered |
| **Strategy.md** | Overall status, post-deploy monitoring |
