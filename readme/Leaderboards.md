# OpenLitterMap v5 — Leaderboard System

## Overview

The leaderboard system provides ranked user lists by XP across two dimensions:

- **Time:** all-time, today, yesterday, this-month, last-month, this-year, last-year
- **Scope:** global, country, state, city

Two backends serve these queries:

| Query Type | Backend | Complexity |
|------------|---------|------------|
| All-time | Redis Sorted Sets (ZSETs) | O(log N + M) |
| Time-filtered | MySQL `metrics` table (per-user rows) | O(N log N) with index |

---

## Architecture

```
MetricsService::processPhoto()
├── MySQL: upsert aggregate (user_id=0) + per-user (user_id>0) rows
└── Redis: RedisMetricsCollector → ZINCRBY {scope}:lb:xp

LeaderboardController
├── All-time → Redis ZREVRANGE {scope}:lb:xp
└── Time-filtered → MySQL metrics WHERE user_id > 0 ORDER BY xp DESC
```

### When users first appear on leaderboards

- **Public (non-school) photos:** User appears at **upload time** via `recordUploadMetrics()` with 5 XP. Leaderboard score is updated with tag XP when tags are added.
- **School photos:** User does NOT appear until **teacher approval**. `processPhoto()` → `doCreate()` writes the full XP (upload + tag) in one pass. No leaderboard entry exists before approval.

---

## API Endpoint

```
GET /api/leaderboard
```

**Auth:** `auth:sanctum` (required)

### Query Parameters

| Param | Default | Values |
|-------|---------|--------|
| `timeFilter` | `all-time` | `all-time`, `today`, `yesterday`, `this-month`, `last-month`, `this-year`, `last-year` |
| `locationType` | (none) | `country`, `state`, `city` |
| `locationId` | (none) | Integer ID (required if locationType is set) |
| `page` | `1` | Pagination (100 per page) |

### Response

```json
{
    "success": true,
    "users": [
        {
            "user_id": 42,
            "public_profile": true,
            "name": "John",
            "username": "@johnny",
            "xp": 1234,
            "global_flag": "ie",
            "social": null,
            "team": "Cork Cleanup Crew",
            "rank": 1
        }
    ],
    "hasNextPage": true,
    "total": 500,
    "activeUsers": 350,
    "totalUsers": 12000,
    "currentUserRank": 42
}
```

Privacy is respected via two levels:
- **Team-level pivot (takes precedence):** If the user has an active team, `show_name_leaderboards` and `show_username_leaderboards` on the `team_user` pivot **override** the user's global `show_name` / `show_username` settings. The settings toggle endpoints (`ApiSettingsController@leaderboardName/leaderboardUsername`) sync both the global column and the active team's pivot column, keeping them in lock-step.
- **Global fallback:** If no active team (or the pivot value is null), the user's global `show_name` / `show_username` settings apply.
- **Safeguarding override:** School teams with safeguarding enabled always null out name/username/social/flag regardless of pivot settings.
- Team name shows only if the user has leaderboard visibility enabled on their team pivot.

`user_id` and `public_profile` are included so the frontend can link to public profiles (`/profile/{user_id}`). Users with `public_profile=true` are clickable in the leaderboard list.

---

## Redis: All-Time Rankings

### Key Pattern

```
{scope}:lb:xp    →  Sorted Set (ZSET), score = XP, member = user_id
```

### Scope Prefixes

| Scope | Redis Key | Example |
|-------|-----------|---------|
| Global | `{g}:lb:xp` | All users globally |
| Country | `{c:ID}:lb:xp` | `{c:105}:lb:xp` (Ireland) |
| State | `{s:ID}:lb:xp` | `{s:42}:lb:xp` |
| City | `{ci:ID}:lb:xp` | `{ci:789}:lb:xp` |

### Operations

| Event | Redis Command |
|-------|---------------|
| Photo created | `ZINCRBY {scope}:lb:xp $xp $userId` |
| Photo updated | `ZINCRBY {scope}:lb:xp $xpDelta $userId` |
| Photo deleted | `ZINCRBY {scope}:lb:xp -$xp $userId` then `ZREMRANGEBYSCORE {scope}:lb:xp -inf 0` |

**Zero-XP pruning:** After a delete, `ZREMRANGEBYSCORE` removes all members with score ≤ 0. This keeps Redis consistent with MySQL (which filters `xp > 0`). Without pruning, deleted users would remain as ghost entries in the ZSET.

### Reads

```
ZREVRANGE {scope}:lb:xp $start $end WITHSCORES   → page of ranked users
ZCARD {scope}:lb:xp                                → total users in ranking
ZREVRANK {scope}:lb:xp $userId                     → current user's rank (0-indexed)
```

**Rank is 1-indexed in the API response.** `ZREVRANK` returns 0-indexed, so `getCurrentUserRank()` adds 1 before returning.

These are managed in `RedisMetricsCollector::processPhoto()` alongside other Redis operations.

---

## MySQL: Time-Filtered Rankings

Per-user rows in the `metrics` table (where `user_id > 0`) serve time-filtered leaderboards.

### How Rows Are Written

`MetricsService::buildTimeSeriesRows()` produces **two rows** per timescale × location:
1. `user_id = 0` — aggregate row (existing behavior)
2. `user_id = $photo->user_id` — per-user row (for leaderboard queries)

This doubles rows from ~20 to ~40 per photo processing.

### Query Pattern

```sql
SELECT user_id, xp FROM metrics
WHERE timescale = ?
  AND location_type = ?
  AND location_id = ?
  AND user_id > 0
  AND year = ?
  AND month = ?
  AND bucket_date = ?    -- daily only (timescale=1)
  AND xp > 0
ORDER BY xp DESC, user_id ASC
LIMIT 100 OFFSET ?
```

**Daily bucket_date filtering:** For `today` and `yesterday`, the query MUST include `WHERE bucket_date = ?`. Without it, daily queries return all daily rows for the entire month. Monthly/yearly queries omit `bucket_date`.

**Deterministic tie-breaking:** Secondary sort by `user_id ASC` ensures consistent pagination when multiple users have the same XP.

### Time Filter → Query Params

| Filter | Timescale | Year | Month | bucket_date |
|--------|-----------|------|-------|-------------|
| `today` | 1 (daily) | current | current | today's date |
| `yesterday` | 1 (daily) | yesterday's | yesterday's | yesterday's date |
| `this-month` | 3 (monthly) | current | current | — |
| `last-month` | 3 (monthly) | prev month's | prev month's | — |
| `this-year` | 4 (yearly) | current | 0 | — |
| `last-year` | 4 (yearly) | prev year | 0 | — |

### Index

```sql
CREATE INDEX idx_leaderboard ON metrics (timescale, location_type, location_id, year, month, bucket_date, xp DESC)
```

Includes `bucket_date` so daily queries (timescale=1) can use the index fully. Created by migration `2026_02_24_150115` and updated by `2026_02_24_160843`.

---

## Constraint Migration

The original `metrics` table had a CHECK constraint:

```sql
CHECK (user_id = 0 OR (location_type = 0 AND location_id = 0))
```

This restricted per-user rows to global scope only. The migration drops this constraint to allow per-user rows at country/state/city scopes (required for location-filtered leaderboards).

---

## rewardXpToAdmin()

The `rewardXpToAdmin()` helper (in `app/Helpers/helpers.php`) gives XP to admin users for verification work. It:

1. Increments `users.xp` in MySQL
2. Increments the user's score in `{g}:lb:xp` ZSET
3. Increments `{u:ID}:stats` hash `xp` field

## RewardLittercoin

The `RewardLittercoin` listener fires on `TagsVerifiedByAdmin`. It uses cluster-compatible Redis keys via `RedisKeys::user($id)` and `RedisKeys::stats()` (not bare `"user:$id"` strings), wrapped in a try-catch so a Redis failure doesn't break the metrics pipeline.

---

## Frontend

### Pinia Store

| File | Purpose |
|------|---------|
| `resources/js/stores/leaderboard/index.js` | State: `leaderboard`, `currentPage`, `hasNextPage`, `total`, `currentUserRank`, `loading`, `error`, `currentFilters`, `countries` |
| `resources/js/stores/leaderboard/requests.js` | `FETCH_LEADERBOARD()` (unified), `FETCH_COUNTRIES()`, backward-compat wrappers |

`FETCH_LEADERBOARD({ timeFilter, locationType, locationId, page })` is the single entry point. Sets `loading`/`error` state, stores `total` and `currentUserRank` from response.

`FETCH_COUNTRIES()` loads country list from `/api/v1/locations` for the location filter dropdown. Cached after first load.

### Vue Components

| File | Purpose |
|------|---------|
| `resources/js/views/General/Leaderboards/Leaderboard.vue` | Page wrapper — dark gradient bg, auth gate, stats bar, filters, list, pagination |
| `resources/js/views/General/Leaderboards/components/LeaderboardFilters.vue` | Time filter pills (desktop) / select (mobile) + country dropdown |
| `resources/js/views/General/Leaderboards/components/LeaderboardList.vue` | Responsive dark glass user cards with medal/flag/name/xp |

### Design System

Matches the Locations page dark glass theme:

```
Background: bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900
Cards:      bg-white/5 border border-white/10 rounded-xl
Hover:      hover:bg-white/[0.08] hover:-translate-y-0.5 hover:shadow-lg hover:shadow-black/20
Labels:     text-white/50 text-[11px] font-semibold uppercase tracking-widest
Values:     text-white text-2xl font-bold tabular-nums tracking-tight
Skeleton:   inline-block w-16 h-7 bg-white/10 rounded animate-pulse
Spinner:    animate-spin rounded-full h-10 w-10 border-2 border-white/20 border-t-emerald-400
Active pill: bg-emerald-500/20 text-emerald-400 border border-emerald-500/30
```

### Features

- **Auth gate:** Unauthenticated users see signup prompt instead of leaderboard
- **Stats bar:** 2-col grid showing "Your Rank" (ordinal) and "Total Users" (formatted) with skeleton loading
- **Time filters:** 7 options (all-time, today, yesterday, this-month, last-month, this-year, last-year). Desktop = emerald pill buttons, mobile = styled `<select>`
- **Country filter:** Dropdown populated from `/api/v1/locations`. Selecting a country passes `locationType=country` + `locationId` to `FETCH_LEADERBOARD`
- **Pagination:** Previous/Next buttons with page number, scroll-to-top on page change
- **Error state:** Red text with retry button
- **Mobile responsive:** Social icons hidden, time filter becomes dropdown, cards scale properly

---

## Key Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Leaderboard/LeaderboardController.php` | API controller |
| `app/Services/Redis/RedisKeys.php` | `xpRanking()` key builder |
| `app/Services/Redis/RedisMetricsCollector.php` | ZSET writes in pipeline |
| `app/Services/Metrics/MetricsService.php` | Per-user row generation |
| `app/Helpers/helpers.php` | `rewardXpToAdmin()` |
| `database/migrations/2026_02_24_150115_*` | Constraint drop + index |
| `tests/Feature/Leaderboard/LeaderboardTest.php` | 12 tests |
| `resources/js/stores/leaderboard/index.js` | Pinia store state |
| `resources/js/stores/leaderboard/requests.js` | API request actions |
| `resources/js/views/General/Leaderboards/Leaderboard.vue` | Page wrapper |
| `resources/js/views/General/Leaderboards/components/LeaderboardFilters.vue` | Time + location filters |
| `resources/js/views/General/Leaderboards/components/LeaderboardList.vue` | User cards (public profiles are clickable links) |

---

## Related Docs

| Document | Covers |
|----------|--------|
| **Metrics.md** | MetricsService internals, MySQL upserts, Redis key patterns |
| **Teams.md** | Team leaderboard visibility, privacy settings |
