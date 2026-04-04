---
name: v5-migration
description: The olm:v5 migration script, UpdateTagsService, batch processing, migrated_at, ClassifyTagsService deprecated mappings, and data migration from v4 category tables.
---

# V5 Migration

`php artisan olm:v5` migrates photos from v4 category-based tags (14 separate tables like `smoking`, `food`, `coffee`) to v5 normalized PhotoTags. Processes per-user, in batches, with idempotency via `migrated_at`.

## Key Files

- `app/Console/Commands/tmp/v5/Migration/MigrationScript.php` — Artisan command
- `app/Services/Tags/UpdateTagsService.php` — Per-photo v4->v5 conversion
- `app/Services/Tags/ClassifyTagsService.php` — Tag classification + deprecated key mapping
- `app/Services/Tags/GeneratePhotoSummaryService.php` — Summary JSON + XP after migration
- `app/Services/Achievements/Tags/TagKeyCache.php` — Cached tag ID lookups
- `app/Services/Metrics/MetricsService.php` — Processes metrics post-migration

## Invariants

1. **`migrated_at` prevents reprocessing.** Once set, the photo is skipped on subsequent runs. Re-running processes 0 photos.
2. **Migration is per-user, batched.** Default 500 photos per batch. Memory managed with `gc_collect_cycles()` between users.
3. **Three-step per photo:** `UpdateTagsService::updateTags()` -> `GeneratePhotoSummaryService::run()` -> `MetricsService::processPhoto()` -> mark `migrated_at`.
4. **Errors are logged and skipped.** A failed photo doesn't halt the migration. The next run retries it (no `migrated_at` set).
5. **Seeds reference tables if empty.** Categories, brands, achievements seeded on first run.

## Patterns

### Command usage

```bash
php artisan olm:v5                    # All users
php artisan olm:v5 --user=123         # Single user
php artisan olm:v5 --batch=1000       # Custom batch size
php artisan olm:v5 --skip-locations   # Skip location cleanup step
```

### Migration flow per photo

```php
// UpdateTagsService::updateTags($photo)
public function updateTags(Photo $photo): void
{
    // 1. Read v4 data from old category relationships
    [$tags, $customTagsOld] = $this->getTags($photo);

    // 2. Classify each tag (handles deprecated key mapping)
    $parsed = $this->parseTags($tags, $customTagsOld, $photo->id);
    // Returns: ['groups' => [...], 'globalBrands' => [...], 'customTags' => [...]]

    // 3. Create v5 PhotoTag + PhotoTagExtraTags records
    $this->createPhotoTags($photo, $parsed);
}
```

### Tag parsing (v4 -> v5 classification)

```php
// Input: ['smoking' => ['butts' => 5, 'cigaretteBox' => 1], 'brands' => ['marlboro' => 3]]

// For each tag:
$result = $this->classifyTags->classify($tagKey);
// 1. Check normalizeDeprecatedTag() — maps old keys to new + materials
// 2. Look up Category by key
// 3. Look up LitterObject by key (or auto-create as crowdsourced)
// 4. Return classification with materials list

// Output groups structure:
[
    'groups' => [
        'smoking' => [
            'category_id' => 2,
            'objects' => [
                ['id' => 45, 'key' => 'butts', 'quantity' => 5, 'materials' => ['plastic', 'paper']],
            ]
        ]
    ],
    'globalBrands' => [['id' => 12, 'key' => 'marlboro', 'quantity' => 3]],
    'customTags' => [...]
]
```

### TagKeyCache preloading

```php
// Called once at script startup for performance
TagKeyCache::preloadAll();

// Three-layer cache: in-memory array -> Redis hash (24h TTL) -> database
$id = TagKeyCache::idFor('material', 'glass');         // fast lookup
$id = TagKeyCache::getOrCreateId('material', 'glass'); // upsert if missing
```

### Memory management

```php
// In migration loop:
DB::disableQueryLog();           // Prevent query log from growing
gc_collect_cycles();             // Between users
// Batch stats: time, speed (photos/sec), memory delta per batch
```

### MigrationScript command structure

```php
protected $signature = 'olm:v5
    {--skip-locations : Skip the locations cleanup step}
    {--user= : Specific user ID to migrate}
    {--batch=500 : Number of photos per batch}';

public function handle(): int
{
    $this->ensureProcessingColumns();  // Add processed_* if missing
    $this->seedReferenceTables();      // Categories, brands, achievements
    TagKeyCache::preloadAll();
    DB::disableQueryLog();
    $this->runMigration();             // Per-user, batched
}
```

## Post-Migration Fix: Orphaned Tags (2026-04-04)

The v5 migration left 189,518 `photo_tags` rows with `category_litter_object_id = NULL` because `DEPRECATED_TAG_MAP` mapped v4 keys to composite names (e.g. `beerCan` → `beer_can`) instead of decomposing into object + type (e.g. `alcohol.can` + type `beer`). The fallback created runtime `litter_objects` (crowdsourced=1) without CLO relationships.

**Fix commands:**
- `php artisan olm:fix-orphaned-tags` — Remaps 71 orphan LOs to canonical CLO/LO/type targets. 74 mapping entries (3 multi-category splits). Batched, transacted, idempotent. Supports `--apply`, `--verify-only`, `--log`.
- `php artisan olm:regenerate-summaries --orphan-fix` — Regenerates stale summaries for affected photos. Chunked via `chunkById`, resumable (skips photos with non-null `clo_id` in summary), `Photo::withoutEvents()`.
- `php artisan olm:reprocess-metrics --from-file=<ids>` — Delta-based MetricsService reprocess for ~1,041 photos with XP changes (special object bonus corrections).

**Key files:**
- `app/Console/Commands/tmp/v5/Migration/FixOrphanedTags.php`
- `app/Console/Commands/tmp/v5/Migration/RegenerateSummaries.php`
- `app/Console/Commands/tmp/v5/Migration/ReprocessPhotoMetrics.php`
- `readme/TagsCleanupPostMigration.md` — Full mapping tables and judgment calls
- `readme/changelog/production-orphan-fix-runbook.md` — Production execution steps

## Common Mistakes

- **Removing `migrated_at` check.** This is the idempotency guard. Without it, photos get double-migrated.
- **Running without `TagKeyCache::preloadAll()`.** Cold lookups hit the database per tag. Preload caches first.
- **Not calling `GeneratePhotoSummaryService` after tag creation.** Summary must be generated for MetricsService to read.
- **Assuming all v4 keys map 1:1 to v5.** Many v4 keys like `beerBottle` split into object + materials. `normalizeDeprecatedTag()` handles this.
- **Not handling deprecated category keys.** `ClassifyTagsService::CATEGORY_ALIASES` resolves old category keys: `coastal→marine`, `trashdog→pets`, `dogshit→pets`, `automobile→vehicles`, `pathway→unclassified`, `drugs→unclassified`, `political→unclassified`, `stationery→unclassified`. The public `getCategory(string $rawKey)` method checks aliases before DB lookup.
- **Processing brands inline.** Brands are deferred to `globalBrands` array — not attached to specific objects during migration.
