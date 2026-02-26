---
name: achievements-system
description: AchievementEngine, AchievementRepository, milestone checkers, AchievementsSeeder, user_achievements pivot, AchievementsController API, and achievement evaluation flow.
---

# Achievements System

Milestone-based achievements unlocked when users hit thresholds for uploads, streaks, and per-tag counts. Definitions are seeded from `config/achievements.php` milestones crossed with every tag in the system.

## Key Files

- `config/achievements.php` — Milestones array, cache TTL, level thresholds
- `app/Services/Achievements/AchievementEngine.php` — Core evaluator (checkers + fallback pass)
- `app/Services/Achievements/AchievementRepository.php` — DB reads/writes + caching
- `app/Services/Achievements/Checkers/AchievementChecker.php` — Abstract base checker
- `app/Services/Achievements/Checkers/UploadsChecker.php` — Uploads dimension
- `app/Services/Achievements/Checkers/ObjectsChecker.php` — Objects dimension + per-object
- `app/Services/Achievements/Checkers/CategoriesChecker.php` — Categories dimension + per-category
- `app/Services/Achievements/Checkers/MaterialsChecker.php` — Materials (extends OptimizedTagBasedChecker)
- `app/Services/Achievements/Checkers/BrandsChecker.php` — Brands (extends OptimizedTagBasedChecker)
- `app/Services/Achievements/Checkers/CustomTagChecker.php` — Custom tags (extends OptimizedTagBasedChecker)
- `app/Services/Achievements/Checkers/OptimizedTagBasedChecker.php` — Abstract base for tag-based checkers
- `app/Services/Achievements/Tags/TagKeyCache.php` — Tag key-to-ID resolution cache
- `app/Providers/AchievementServiceProvider.php` — DI wiring (engine, repository, checkers)
- `app/Models/Achievements/Achievement.php` — Eloquent model
- `app/Events/AchievementsUnlocked.php` — Event fired when achievements unlock
- `app/Http/Controllers/Achievements/AchievementsController.php` — API endpoint
- `database/seeds/AchievementSeeder.php` — Seeds achievement definitions
- `database/factories/AchievementFactory.php` — Test factory
- `tests/Feature/Achievements/AchievementEngineTest.php` — Engine integration tests
- `tests/Feature/Achievements/LongTermAchievementsTest.php` — Long-term achievement tests
- `tests/Unit/Achievements/CheckerUnitTest.php` — Checker unit tests

## Invariants

1. **Achievements are evaluated after Redis metrics update.** `AchievementEngine::evaluate()` reads from `RedisMetricsCollector::getUserMetrics()`. Redis must be updated first.
2. **Definitions are seeded, not runtime-created.** `AchievementsSeeder` creates all definition rows. Adding a new tag type or milestone requires rerunning the seeder.
3. **`user_achievements` pivot has composite PK `(user_id, achievement_id)`.** `insertOrIgnore` prevents duplicates.
4. **Checkers use early-exit optimization.** Achievements sorted by threshold ascending — once a threshold isn't met, remaining are skipped.
5. **Fallback pass in engine catches checker gaps.** `meetsThreshold()` runs after all checkers, catching anything they missed (safety net for off-by-one or unregistered checkers).
6. **Caching:** Definitions cached 24h (`achievements.all`), per-user unlocked cached 5m (`user.achievements.$userId`). Cache cleared on unlock.

## Architecture

```
Photo tagged + verified
  → TagsVerifiedByAdmin event
    → ProcessPhotoMetrics listener
      → MetricsService::processPhoto() (MySQL + Redis)
      → EvaluateUserAchievements job (queued)
        → AchievementEngine::evaluate($userId)
          → RedisMetricsCollector::getUserMetrics($userId)
          → Run all registered checkers
          → Fallback meetsThreshold() pass
          → AchievementRepository::unlockAchievements()
          → Cache invalidation
```

## Achievement Types

| Type | Scope | Example |
|------|-------|---------|
| `uploads` | Dimension-wide (no tag_id) | "Upload 42 photos" |
| `streak` | Dimension-wide (no tag_id) | "7-day upload streak" |
| `objects` | Dimension-wide (no tag_id) | "Tag 1000 total objects" |
| `object` | Per-tag (tag_id = litter_object.id) | "Tag 69 cigarette butts" |
| `categories` | Dimension-wide (no tag_id) | "Tag items in 10 categories" |
| `category` | Per-tag (tag_id = category.id) | "Tag 256 smoking items" |
| `materials` | Dimension-wide (no tag_id) | "Tag 500 total materials" |
| `material` | Per-tag (tag_id = material.id) | "Tag 42 plastic items" |
| `brands` | Dimension-wide (no tag_id) | "Tag 100 total brands" |
| `brand` | Per-tag (tag_id = brand.id) | "Tag 69 Coca-Cola items" |
| `customTag` | Per-tag (tag_id = custom_tag.id) | Per custom tag milestones |

## Milestones

From `config/achievements.php`:
```
[1, 42, 69, 256, 360, 404, 420, 451, 512, 666, 777, 1337, 2048, 3333, 3600, 9001, 13337, 42069, 69420, 133337, 420420, 666666, 696969, 4206969]
```

Each milestone is crossed with each type/tag combination, producing ~222k achievement definitions.

## API

`GET /api/achievements` (auth required) — Returns hierarchical JSON:
- `overview`: progress for uploads, streak, total_categories, total_objects
- `categories`: per-category with nested per-object progress
- `summary`: total/unlocked/percentage

## Common Mistakes

- **Evaluating before Redis is updated.** `getUserMetrics()` reads from Redis. If called before `RedisMetricsCollector::processPhoto()`, counts are stale.
- **Forgetting to reseed after adding tags.** New LitterObjects/Materials/Brands need new achievement definition rows.
- **Assuming checkers cover all types.** The fallback `meetsThreshold()` is the safety net. If a checker is missing, achievements still unlock (just slower).
