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
- `tests/Feature/Leaderboard/LeaderboardTest.php` — 12 tests covering all paths

## Invariants

1. **All-time leaderboards come from Redis ZSETs.** Key pattern: `{scope}:lb:xp`. Never query MySQL for all-time rankings.
2. **Time-filtered leaderboards come from MySQL.** Query `metrics` table WHERE `user_id > 0` ORDER BY `xp DESC, user_id ASC`. Secondary sort by `user_id` ensures deterministic pagination when XP is tied.
3. **Per-user rows are written by MetricsService.** `buildTimeSeriesRows()` produces two rows per timescale × location: aggregate (user_id=0) and per-user (user_id>0).
4. **ZSET scores are maintained by RedisMetricsCollector.** ZINCRBY for create/update, negative ZINCRBY for delete. Runs inside the Redis pipeline after MySQL commit.
5. **Zero-XP pruning on delete.** After decrementing a ZSET score, `ZREMRANGEBYSCORE {scope}:lb:xp -inf 0` removes members with score ≤ 0. Without this, deleted users remain as ghost entries.
6. **Daily queries MUST include `bucket_date`.** For `today`/`yesterday` (timescale=1), the query includes `WHERE bucket_date = ?`. Without it, all daily rows for the month are returned. Index `idx_leaderboard` includes `bucket_date` for this.
7. **Privacy is enforced in `formatUserData()`.** Respects `show_name`, `show_username`, and team pivot `show_name_leaderboards`/`show_username_leaderboards`.
8. **Route uses `auth:sanctum`** — not `auth:api`. Use `actingAs($user)` in tests (no guard argument).
9. **`rewardXpToAdmin()` must update both** MySQL (`users.xp`) and Redis (`{g}:lb:xp` ZSET + `{u:ID}:stats` hash).
10. **Rank is 1-indexed in API response.** `ZREVRANK` returns 0-indexed; `getCurrentUserRank()` adds 1.

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
- **Omitting `bucket_date` for daily queries.** Without `WHERE bucket_date = ?`, daily (timescale=1) queries return ALL daily rows for the month, not just one day.
- **Not pruning zero-XP members from ZSETs.** After delete, `ZREMRANGEBYSCORE` must run to remove ≤ 0 scores. Without it, ghost entries persist in Redis.
- **Missing `orderBy('user_id')` for tie-breaking.** Without secondary sort, tied-XP users get non-deterministic pagination order.
- **Expecting `Redis::zScore()` to return `null` for missing members.** PHP Redis returns `false`, not `null`. Use `assertFalse()` in tests.
