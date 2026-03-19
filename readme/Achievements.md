# Achievements System

## Overview

The achievements system is **built and partially functional** — the engine, checkers, seeder, repository, model, service provider, controller, and API route all exist. The migration script populates achievements for migrated users. However, it is **not wired into the live tagging flow**, so new photo tags after the migration will not trigger achievement evaluation.

---

## Database State

| Table | Rows | Notes |
|-------|------|-------|
| `achievements` | 221,976 | Definition rows — seeded by `AchievementsSeeder` |
| `user_achievements` | 12,173 | Unlocked achievements across 303 users (from migration script) |

Schema: `achievements(id, type, tag_id, threshold, metadata)` with unique constraint on `(type, tag_id, threshold)`. Pivot: `user_achievements(user_id, achievement_id)` with composite PK.

---

## How It Works

### AchievementsSeeder

Creates achievement definitions by combining:
- **6 dimension-wide types** (uploads, objects, categories, materials, brands, streak) x 22 milestones = 132 dimension-wide definitions
- **Per-tag definitions**: one row per (tag, milestone) for every LitterObject, Category, Material, Brand, and CustomTag

Milestones from `config/achievements.php`: `[1, 42, 69, 256, 360, 404, 420, 451, 512, 666, 777, 1337, 2048, 3333, 3600, 9001, 13337, 42069, 69420, 133337, 420420, 666666, 696969, 4206969]`

### AchievementEngine::evaluate(int $userId)

1. Fetches user metrics from Redis via `RedisMetricsCollector::getUserMetrics($userId)`
2. Gets already-unlocked achievement IDs from `user_achievements` (cached 5 min)
3. Gets all achievement definitions (cached 24 hr)
4. Runs each registered checker — each returns achievement IDs to unlock
5. Fallback pass: `meetsThreshold()` catches anything the checkers missed (safety net)
6. Persists new unlocks via `AchievementRepository::unlockAchievements()` (bulk `insertOrIgnore`)
7. Clears user cache

### Registered Checkers (AchievementServiceProvider)

| Checker | Registered? | Type checked |
|---------|-------------|-------------|
| `UploadsChecker` | Yes | `uploads` (dimension-wide only) |
| `ObjectsChecker` | Yes | `objects` (dimension-wide) + `object` (per-tag) |
| `CategoriesChecker` | Yes | `categories` (dimension-wide) + `category` (per-tag) |
| `MaterialsChecker` | Yes | `materials` (dimension-wide) + `material` (per-tag) |
| `BrandsChecker` | Yes | `brands` (dimension-wide) + `brand` (per-tag) |
| `CustomTagChecker` | **NO** | Would check `custom_tags`/`customTag` but not registered |

All checkers read from the `counts` array provided by `RedisMetricsCollector::getUserMetrics()`, which returns `{uploads, streak, categories:{}, objects:{}, materials:{}, brands:{}, custom_tags:{}}`.

### AchievementsController (API)

Route: `GET /api/achievements` (auth required). Returns hierarchical JSON with overview (uploads, streak, categories, objects), per-category/object progress, and summary stats.

**Bug:** Line 38 hardcodes `$counts = 0` instead of calling `RedisMetricsCollector::getUserCounts($userId)`. The original call is commented out on line 37. This means the progress percentage shown to users is always 0.

---

## Bugs Found

### 1. NOT WIRED INTO LIVE FLOW (Critical)

`AchievementEngine::evaluate()` is **only called** by the migration script (`MigrationScript.php:257`). It is NOT called from:
- `ProcessPhotoMetrics` listener (on `TagsVerifiedByAdmin`)
- `AddTagsToPhotoAction`
- Any other listener, action, or controller

**Impact:** After the v5 migration completes, no new achievements will ever be unlocked for any user during normal operation.

**Where to wire it:** The natural integration point is `ProcessPhotoMetrics` (the listener on `TagsVerifiedByAdmin`), AFTER Redis metrics have been updated. This ensures `getUserMetrics()` returns fresh data for the checkers. Alternatively, dispatch a separate queued job from `ProcessPhotoMetrics`.

### 2. CustomTagChecker not registered (Minor)

`CustomTagChecker` exists in `app/Services/Achievements/Checkers/CustomTagChecker.php` but is not registered in `AchievementServiceProvider`. The seeder creates `customTag` per-tag achievements, but the checker is never run.

**Mitigated by:** The fallback `meetsThreshold()` pass in `AchievementEngine` catches `custom_tags` and `custom_tag` types. However, it's less efficient (no early-exit optimization, checks every definition).

### 3. AchievementsController hardcodes $counts = 0 (Medium)

Line 38 of `AchievementsController::index()`:
```php
$counts = 0;  // should be RedisMetricsCollector::getUserCounts($userId)
```

The commented-out line 37 has the correct call. This means the achievements API returns 0% progress for everything, even though achievements are unlocked correctly in `user_achievements`.

### 4. AchievementsUnlocked event is a stub (Low)

`AchievementsUnlocked` event constructor receives `$user` and `$defs` but has an empty body — doesn't store them as properties. `config/achievements.php` has `'dispatch_events' => true` but the engine never dispatches this event. Not blocking, but the infrastructure exists for WebSocket notifications (e.g., "You unlocked an achievement!") once wired.

---

## Files Inventory

| File | Purpose |
|------|---------|
| `config/achievements.php` | Milestones, cache TTL, levels config |
| `app/Services/Achievements/AchievementEngine.php` | Core evaluator |
| `app/Services/Achievements/AchievementRepository.php` | DB reads/writes + caching |
| `app/Services/Achievements/Checkers/AchievementChecker.php` | Abstract base checker |
| `app/Services/Achievements/Checkers/UploadsChecker.php` | Uploads dimension |
| `app/Services/Achievements/Checkers/ObjectsChecker.php` | Objects dimension + per-object |
| `app/Services/Achievements/Checkers/CategoriesChecker.php` | Categories dimension + per-category |
| `app/Services/Achievements/Checkers/MaterialsChecker.php` | Materials (extends OptimizedTagBasedChecker) |
| `app/Services/Achievements/Checkers/BrandsChecker.php` | Brands (extends OptimizedTagBasedChecker) |
| `app/Services/Achievements/Checkers/CustomTagChecker.php` | Custom tags — **NOT REGISTERED** |
| `app/Services/Achievements/Checkers/OptimizedTagBasedChecker.php` | Abstract base for tag-based checkers |
| `app/Services/Achievements/Tags/TagKeyCache.php` | Tag key-to-ID resolution cache |
| `app/Services/Achievements/DslHelpers.php` | DSL helpers (legacy, from old config approach) |
| `app/Providers/AchievementServiceProvider.php` | DI wiring (engine, repository, checkers) |
| `app/Models/Achievements/Achievement.php` | Eloquent model |
| `app/Events/AchievementsUnlocked.php` | Event stub (empty body) |
| `app/Http/Controllers/Achievements/AchievementsController.php` | API endpoint |
| `database/seeds/AchievementSeeder.php` | Seeds achievement definitions |
| `database/factories/AchievementFactory.php` | Test factory |
| `tests/Feature/Achievements/AchievementEngineTest.php` | Engine integration tests |
| `tests/Feature/Achievements/LongTermAchievementsTest.php` | Long-term achievement tests |
| `tests/Unit/Achievements/CheckerUnitTest.php` | Checker unit tests |
| `tests/Unit/Achievements/TagKeyCacheZeroGuardTest.php` | TagKeyCache edge case tests |

---

## Recommended Next Steps

1. **Wire `AchievementEngine::evaluate()` into the live flow** — Call it from `ProcessPhotoMetrics` listener after Redis metrics are updated, or dispatch a separate queued job
2. **Register `CustomTagChecker`** in `AchievementServiceProvider`
3. **Fix `AchievementsController`** — Uncomment the `getUserCounts()` call on line 37, remove line 38
4. **Fix `AchievementsUnlocked` event** — Store constructor args as public properties; optionally dispatch from engine after unlocking
5. **Consider a `StreakChecker`** — The seeder creates `streak` achievements but no `StreakChecker` exists (handled by fallback `meetsThreshold()`)
