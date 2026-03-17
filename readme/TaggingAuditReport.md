# v5.1 Tagging Architecture — Smoke Test Audit Report

**Date:** 2026-02-26
**Branch:** `upgrade/tagging-2025`
**Test Suite:** 754 passed, 1 skipped, 0 failures

---

## Results Summary

| # | Check | Status | Notes |
|---|-------|--------|-------|
| 1 | Test suite | ✅ | 754 passed, 1 skipped, 0 failures |
| 2 | GET /api/tags/all response shape | ✅ | All 7 keys present |
| 3 | AddTagsToPhotoAction accepts CLO ID | ✅ | CLO-first + legacy fallback |
| 4 | PhotoTagsController payload | ✅ | Thin controller, delegates to action |
| 5 | Frontend sends CLO ID | ✅ | `tagsStore.getCloId()` → `category_litter_object_id` |
| 6 | GeneratePhotoSummaryService flat format | ✅ | Flat `tags[]` array, no nested dict |
| 7 | MetricsService walks flat array | ✅ | `foreach $tags as $tag` with direct key access |
| 8 | RedisMetricsCollector types dimension | ✅ | Types tracked in CRUD, user metrics, ZSETs |
| 9 | photo_tags schema | ⚠️ | Migrations pending on dev DB (correct in test DB) |
| 10 | photo_tag_extra_tags schema | ⚠️ | Migrations pending on dev DB (correct in test DB) |
| 11 | PhotoTag model relationships | ✅ | Correct for post-migration schema |
| 12 | ConvertV4TagsAction mobile shim | ✅ | Maps v4→CLO, delegates to v5 action |
| 13 | olm:verify-tag-integrity | ✅ | Validates CLO refs, denorm drift, invalid types |
| 14 | Seeder integrity | ✅ | Seeds categories, objects, CLO pivots, materials, types |
| 15 | Cross-reference: old patterns | ✅ | No deprecated patterns in production code |

**Overall: 13 ✅, 2 ⚠️ (pending migrations on dev DB only), 0 ❌**

---

## Detailed Findings

### 1. Test Suite ✅

```
Tests: 1 skipped, 754 passed (4094 assertions)
Duration: 221.13s
```

The 1 skipped test is `TypesCheckerTest::test_it_unlocks_per_type_achievement` — skips when `litter_object_types` table is empty in the test DB (conditional skip via `markTestSkipped`). All other tests green.

---

### 2. GET /api/tags/all Response Shape ✅

**Route:** `GET /api/tags/all` → `GetTagsController::getAllTags()`
**File:** `app/Http/Controllers/API/Tags/GetTagsController.php`

Response includes all required keys:
- `categories` — Category records (id, key)
- `objects` — LitterObject records with eager-loaded categories
- `materials` — Materials records (id, key)
- `brands` — BrandList records (id, key)
- `types` — LitterObjectType records (id, key, name)
- `category_objects` — CategoryObject pivot records (id, category_id, litter_object_id)
- `category_object_types` — Raw pivot rows (category_litter_object_id, litter_object_type_id)

---

### 3. AddTagsToPhotoAction Accepts CLO ID ✅

**File:** `app/Actions/Tags/AddTagsToPhotoAction.php`

- **CLO-based path** (`createTagFromClo`): Accepts `category_litter_object_id`, auto-resolves `category_id` + `litter_object_id` from `CategoryObject::find($cloId)`. Validates type against `category_object_types` pivot. Validates "other" objects require at least one extra tag.
- **Legacy path** (`createTagLegacy`): Accepts `category` + `object` strings or IDs, resolves CLO from the pair. Auto-resolves category from object if omitted.
- **Format detection** (line 72-80): Routes to appropriate handler based on presence of `category_litter_object_id` key.

---

### 4. PhotoTagsController Payload Format ✅

**Route:** `POST /api/v3/tags` → `PhotoTagsController::store()`
**File:** `app/Http/Controllers/API/Tags/PhotoTagsController.php`

Thin controller. Uses `PhotoTagsRequest` for validation, then:
```php
$this->addTagsAction->run($request->user()->id, $request->photo_id, $request->tags);
```

`PhotoTagsRequest` validates both CLO-based format (`tags.*.category_litter_object_id` exists in `category_litter_object` table) and legacy format. Authorization checks ownership, existence, and verification status.

---

### 5. Frontend Sends CLO ID ✅

**File:** `resources/js/views/General/Tagging/v2/AddTags.vue`

Data flow:
1. User selects object → `tagsStore.getCloId(categoryId, objectId)` resolves CLO ID
2. Tag submission builds payload with `category_litter_object_id`
3. `photosStore.UPLOAD_TAGS()` sends `POST /api/v3/tags`

**Tag store:** `resources/js/stores/tags/index.js` provides `getCloId` getter that searches `categoryObjects` array loaded from `/api/tags/all`.

Fallback paths exist for custom-only, brand-only, and material-only tags that use the legacy format (routed to `createTagLegacy` on backend).

---

### 6. GeneratePhotoSummaryService Flat Format ✅

**File:** `app/Services/Tags/GeneratePhotoSummaryService.php`

Produces flat array where each entry maps 1:1 to a `photo_tags` row:
```php
'tags' => [
    [
        'clo_id' => 42,
        'category_id' => 1,
        'object_id' => 5,
        'type_id' => 3,
        'quantity' => 2,
        'materials' => [1, 4],
        'brands' => [7 => 1],
        'custom_tags' => [45],
    ],
    // ...
],
'totals' => [...],
'keys' => [...]
```

- Custom tags read from `extraTags` with `tag_type='custom_tag'` (not `custom_tag_primary_id`)
- No references to nested dict format or `custom_tag_primary_id`
- 7/7 tests passing in `GeneratePhotoSummaryTest.php`

---

### 7. MetricsService Walks Flat Array ✅

**File:** `app/Services/Metrics/MetricsService.php`

- Detects format via `array_is_list($summaryTags)` (line 200-204)
- Routes flat format to `extractFromFlatSummary()` (line 218+)
- Iterates: `foreach ($summaryTags as $tag)` with direct key access (`$tag['category_id']`, `$tag['object_id']`, etc.)
- Types tracked: `$tags['types'][$typeId] += $quantity` (lines 241-243)
- Materials, brands, custom tags iterated from `$tag['materials']`, `$tag['brands']`, `$tag['custom_tags']`
- Legacy nested format still supported via `extractFromNestedSummary()` for backward compatibility

---

### 8. RedisMetricsCollector Types Dimension ✅

**File:** `app/Services/Redis/RedisMetricsCollector.php`

- Types included in all 6 dimensions loop (line 95): `'types'` mapped to `RedisKeys::types($scope)` hash key
- Create: increments types hash + ZSET scores
- Update: applies delta for types dimension
- Delete: decrements with negative multiplier, prunes zero scores
- User metrics: tracks `"type:$id"` keys in user tags hash (lines 159-165)
- `getUserMetrics()` parses type entries and includes in return structure (lines 239-241)
- 15/15 unit tests passing

---

### 9. photo_tags Schema ⚠️

**Status:** Migrations exist but are pending on dev DB.

**Dev DB (olm_mig_9) — current state:**
- `category_litter_object_id` — **MISSING** (added by `2026_02_26_135052`)
- `litter_object_type_id` — **MISSING** (added by `2026_02_26_135052`)
- `custom_tag_primary_id` — **STILL EXISTS** (dropped by `2026_02_26_170012`)

**Post-migration state (test DB via RefreshDatabase):**
- `category_litter_object_id` — NOT NULL, FK to `category_litter_object`
- `litter_object_type_id` — nullable, FK to `litter_object_types`
- `custom_tag_primary_id` — DROPPED
- `brand_id` — DROPPED (earlier migration)

**Pending migration order:**
1. `2026_02_26_135052` — Add `category_litter_object_id` + `litter_object_type_id` columns
2. `2026_02_26_154906` — Backfill NULL CLO ids, make `category_litter_object_id` NOT NULL
3. `2026_02_26_165857` — Migrate `custom_tag_primary_id` data to `photo_tag_extra_tags`
4. `2026_02_26_170012` — Drop `custom_tag_primary_id` column + `index` column

**Action:** Run `php artisan migrate` on dev/prod after deploying code changes.

---

### 10. photo_tag_extra_tags Schema ⚠️

**Dev DB — current state:**
- `index` column — **STILL EXISTS** (dropped by `2026_02_26_170012`)

**Post-migration state (test DB):**
- Columns: id, photo_tag_id, tag_type, tag_type_id, quantity, created_at, updated_at
- `index` — DROPPED
- FK: `photo_tag_id` → `photo_tags.id` CASCADE DELETE

**Action:** Same migration as Check 9 handles this.

---

### 11. PhotoTag Model Relationships ✅

**File:** `app/Models/Litter/Tags/PhotoTag.php`

| Method | Status | Notes |
|--------|--------|-------|
| `photo()` | ✅ | BelongsTo Photo |
| `category()` | ✅ | BelongsTo Category |
| `object()` | ✅ | BelongsTo LitterObject via `litter_object_id` |
| `categoryObject()` | ✅ | BelongsTo CategoryObject via `category_litter_object_id` |
| `type()` | ✅ | BelongsTo LitterObjectType via `litter_object_type_id` |
| `extraTags()` | ✅ | HasMany PhotoTagExtraTags |
| `attachExtraTags()` | ✅ | Upserts extra tags with proper qty handling |
| `primaryCustomTag()` | ✅ REMOVED | Correctly deleted |
| `brand()` | ✅ REMOVED | Correctly deleted |

`$guarded = []` (mass-assignable). `attachExtraTags()` forces `qty=1` for materials and custom_tags (set membership).

---

### 12. ConvertV4TagsAction Mobile Shim ✅

**File:** `app/Actions/Tags/ConvertV4TagsAction.php`

- Maps v4 `{categoryKey: {objectKey: qty}}` to CLO ids via `Category::where('key')` → `LitterObject::where('key')` → `CategoryObject::where(cat, obj)`
- Delegates to `AddTagsToPhotoAction::run()` (v5)
- Does NOT write to old category columns (no `smoking_id`, etc.)
- Custom tags mapped to `unclassified.other` CLO with `custom_tags` array
- 7/7 tests passing in `ConvertV4TagsActionTest.php`

---

### 13. olm:verify-tag-integrity ✅

**File:** `app/Console/Commands/Tags/VerifyTagIntegrity.php`

Validates:
1. Orphaned CLO references — `photo_tags.category_litter_object_id` points to non-existent CLO
2. Denormalization drift — `category_id`/`litter_object_id` mismatch with CLO pivot
3. Invalid type references — `litter_object_type_id` not in `category_object_types` pivot

`--fix` auto-repairs denorm drift and clears invalid type IDs.

---

### 14. Seeder Integrity ✅

**File:** `database/seeds/Tags/GenerateTagsSeeder.php`

Seeds:
- Categories from `TagsConfig::get()`
- LitterObjects from `TagsConfig::get()`
- `category_litter_object` pivots with material + type attachments
- Materials from `Material::types()` + `TagsConfig`
- LitterObjectTypes (17 types: beer, wine, spirits, etc.)
- Custom tags migration from `custom_tags` → `custom_tags_new`

6/6 seeder tests passing in `GenerateTagsSeederTest.php`.

---

### 15. Cross-Reference: Old Patterns ✅

| Pattern | Status | Details |
|---------|--------|---------|
| `custom_tag_primary_id` in PHP code | ✅ REMOVED | Only in migration files |
| `primaryCustomTag` in PHP/JS | ✅ REMOVED | Only in `.ai/skills` docs |
| `->brand()` on PhotoTag | ✅ REMOVED | Method deleted |
| `smoking_id` being SET | ✅ CLEAN | No writes in production code |
| Old category column writes | ✅ CLEAN | No `$photo->*_id =` assignments |

Test files reference old columns only in backward-compat assertions (e.g., `test_it_does_not_write_to_v4_category_columns` asserts `$photo->smoking_id` stays NULL).

---

## Pre-Deployment Checklist

1. Deploy code changes
2. Run `php artisan migrate` (4 pending migrations)
3. Run `php artisan olm:verify-tag-integrity` — expect 0 mismatches
4. Run `php artisan olm:cleanup-orphaned-objects` — report only, no `--fix`
5. Smoke test: tag a photo via web, verify `photo_tags.category_litter_object_id` is set
6. Smoke test: tag a photo via mobile app, verify ConvertV4TagsAction produces correct PhotoTags
