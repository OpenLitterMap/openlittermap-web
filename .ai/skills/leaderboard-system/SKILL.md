---
name: leaderboard-system
description: LeaderboardController, Redis sorted sets for all-time XP rankings, per-user metrics rows for time-filtered rankings, rewardXpToAdmin, and leaderboard privacy.
---

# Leaderboard System

Rankings by XP across time and location scopes. Two backends: Redis ZSETs for all-time, MySQL per-user metrics rows for time-filtered.

## Key Files

- `app/Http/Controllers/Leaderboard/LeaderboardController.php` — API controller (GET /api/leaderboard)
- `app/Services/Redis/RedisKeys.php` — `xpRanking($scope)` returns `{scope}:lb:xp`
- `app/Services/Redis/RedisMetricsCollector.php` — ZINCRBY in pipeline for create/update/delete
- `app/Services/Metrics/MetricsService.php` — Builds per-user rows (user_id > 0) alongside aggregates
- `app/Helpers/helpers.php` — `rewardXpToAdmin()` increments ZSET + user stats hash
- `tests/Feature/Leaderboard/LeaderboardTest.php` — 14 tests covering all paths (global, country, state, city scopes)

## Invariants

1. **All-time leaderboards come from Redis ZSETs.** Key pattern: `{scope}:lb:xp`. Never query MySQL for all-time rankings.
2. **Time-filtered leaderboards come from MySQL.** Query `metrics` table WHERE `user_id > 0` ORDER BY `xp DESC, user_id ASC`. Secondary sort by `user_id` ensures deterministic pagination when XP is tied.
3. **Per-user rows are written by MetricsService.** `buildTimeSeriesRows()` produces two rows per timescale × location: aggregate (user_id=0) and per-user (user_id>0).
4. **ZSET scores are maintained by RedisMetricsCollector.** ZINCRBY for create/update, negative ZINCRBY for delete. Runs inside the Redis pipeline after MySQL commit.
5. **Zero-XP pruning on delete.** After decrementing a ZSET score, `ZREMRANGEBYSCORE {scope}:lb:xp -inf 0` removes members with score ≤ 0. Without this, deleted users remain as ghost entries.
6. **Daily queries MUST include `bucket_date`.** For `today`/`yesterday` (timescale=1), the query includes `WHERE bucket_date = ?`. Without it, all daily rows for the month are returned. Index `idx_leaderboard` includes `bucket_date` for this.
7. **Privacy is enforced in `formatUserData()`.** Respects `show_name`, `show_username`, and team pivot `show_name_leaderboards`/`show_username_leaderboards`. The pivot values correctly override the global user flags — a user who shows their name globally but opted out on a specific team will be hidden on that team's leaderboard entries.
8. **Route uses `auth:sanctum`** — not `auth:api`. Use `actingAs($user)` in tests (no guard argument).
9. **`rewardXpToAdmin()` must update both** MySQL (`users.xp`) and Redis (`{g}:lb:xp` ZSET + `{u:ID}:stats` hash).
10. **Rank is 1-indexed in API response.** `ZREVRANK` returns 0-indexed; `getCurrentUserRank()` adds 1.
11. **`RewardLittercoin` uses cluster-compatible Redis keys.** All Littercoin Redis operations use `RedisKeys` pattern with hash tags for cluster compatibility. The job wraps Redis commands in a try-catch to prevent failures from blocking the queue.

## Patterns

### Redis all-time query

```php
$key = RedisKeys::xpRanking($scope);
$results = Redis::zRevRange($key, $start, $end, ['WITHSCORES' => true]);
$total = (int) Redis::zCard($key);
$rank = Redis::zRevRank($key, (string) $userId);
```

### Time-filtered query

```php
$query = DB::table('metrics')
    ->where('timescale', $timescale)
    ->where('location_type', $enumType->value)
    ->where('location_id', $locationId)
    ->where('user_id', '>', 0)
    ->where('year', $year)
    ->where('month', $month);

// REQUIRED for daily queries (timescale=1) — omitting returns all daily rows for the month
if (isset($params['bucket_date'])) {
    $query->where('bucket_date', $params['bucket_date']);
}

$query->where('xp', '>', 0)
    ->orderByDesc('xp')
    ->orderBy('user_id')  // deterministic tie-breaking
    ->offset($start)
    ->limit(100)
    ->select('user_id', 'xp')
    ->get();
```

### Time filter mapping

| Filter | Timescale | Year | Month | bucket_date |
|--------|-----------|------|-------|-------------|
| `today` | 1 | current | current | today |
| `yesterday` | 1 | yesterday | yesterday | yesterday |
| `this-month` | 3 | current | current | — |
| `last-month` | 3 | prev | prev | — |
| `this-year` | 4 | current | 0 | — |
| `last-year` | 4 | prev | 0 | — |

### Scope resolution

```php
RedisKeys::xpRanking(RedisKeys::global())        // {g}:lb:xp
RedisKeys::xpRanking(RedisKeys::country($id))    // {c:$id}:lb:xp
RedisKeys::xpRanking(RedisKeys::state($id))      // {s:$id}:lb:xp
RedisKeys::xpRanking(RedisKeys::city($id))       // {ci:$id}:lb:xp
```

## Common Mistakes

- **Using `auth:api` guard in tests.** Leaderboard route uses `auth:sanctum`. Use `actingAs($user)` with no guard.
- **Forgetting the `chk_user_location` constraint was dropped.** Migration `2026_02_24_150115` drops this constraint. Per-user rows now exist at all location scopes.
- **Querying MySQL for all-time rankings.** All-time uses Redis. Only time-filtered queries hit MySQL.
- **Not including `xp > 0` in time-filtered queries.** Users with 0 XP should not appear on leaderboards.
- **Directly modifying ZSET scores outside RedisMetricsCollector.** Only `rewardXpToAdmin()` is allowed to bypass the pipeline (admin-only XP).
- **Using raw Redis key strings in `RewardLittercoin`.** Always use `RedisKeys::*` helpers with hash tags for Redis Cluster compatibility. Bare key strings like `"user:{$id}:littercoin"` break in cluster mode.
- **Expecting the team leaderboard to ignore pivot privacy flags.** `show_name_leaderboards`/`show_username_leaderboards` on the `team_user` pivot row override the global `show_name`/`show_username` flags for that team's leaderboard. Both must be checked in `formatUserData()`.
- **Omitting `bucket_date` for daily queries.** Without `WHERE bucket_date = ?`, daily (timescale=1) queries return ALL daily rows for the month, not just one day.
- **Not pruning zero-XP members from ZSETs.** After delete, `ZREMRANGEBYSCORE` must run to remove ≤ 0 scores. Without it, ghost entries persist in Redis.
- **Missing `orderBy('user_id')` for tie-breaking.** Without secondary sort, tied-XP users get non-deterministic pagination order.
- **Expecting `Redis::zScore()` to return `null` for missing members.** PHP Redis returns `false`, not `null`. Use `assertFalse()` in tests.

## Frontend

### Pinia Store

| File | Purpose |
|------|---------|
| `resources/js/stores/leaderboard/index.js` | State: `leaderboard`, `currentPage`, `hasNextPage`, `total`, `currentUserRank`, `loading`, `error`, `currentFilters`, `countries`, `states`, `cities` |
| `resources/js/stores/leaderboard/requests.js` | `FETCH_LEADERBOARD()` (unified), `FETCH_COUNTRIES()`, `FETCH_STATES(countryId)`, `FETCH_CITIES(stateId)`, backward-compat wrappers |

`FETCH_LEADERBOARD({ timeFilter, locationType, locationId, page })` is the single entry point. Sets `loading`/`error`, stores `total`/`currentUserRank` from response.

### Vue Components

| File | Purpose |
|------|---------|
| `Leaderboard.vue` | Page wrapper — dark gradient bg, auth gate, stats bar (rank/total), filters, list, pagination |
| `LeaderboardFilters.vue` | Time pills (desktop) / select (mobile) + cascading location selectors (type → country → state → city). Emits `change` event. |
| `LeaderboardList.vue` | Dark glass user cards — medal, flag, name, xp. Props: `leaders` array only. |

### Design

Matches Locations page dark glass theme (bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900). Cards use `bg-white/5 border border-white/10 rounded-xl`. Active time pill: `bg-emerald-500/20 text-emerald-400`.

### Data Flow

1. `Leaderboard.vue` calls `FETCH_LEADERBOARD()` on mount
2. `LeaderboardFilters.vue` emits `change` with `{ timeFilter, locationType, locationId }`
3. `Leaderboard.vue` calls `FETCH_LEADERBOARD()` with emitted params
4. Pagination calls `FETCH_LEADERBOARD()` with `...currentFilters + page`
5. Country list loaded via `FETCH_COUNTRIES()` from `/api/v1/locations`
6. State list loaded via `FETCH_STATES(countryId)` from `/api/v1/locations/country/{id}`
7. City list loaded via `FETCH_CITIES(stateId)` from `/api/v1/locations/state/{id}`
