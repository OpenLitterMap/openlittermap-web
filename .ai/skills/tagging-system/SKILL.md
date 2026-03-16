---
name: tagging-system
description: PhotoTag, PhotoTagExtraTags, categories, litter objects, materials, brands, ClassifyTagsService, GeneratePhotoSummaryService, tag migration, and the v4-to-v5 conversion.
---

# Tagging System

V5 uses a normalized hierarchy: Photo -> PhotoTag (category + object + quantity) -> PhotoTagExtraTags (materials, brands, custom tags). All tag data lives in `photo_tags` and `photo_tag_extra_tags` tables — not the old per-category tables.

**V5.1 Architecture (Phase 1 complete — schema + seed only, no behavior changes):** Added `LitterObjectType` dimension ("what was in the container" — beer, water, soda, etc.), `category_object_types` pivot controlling which types are valid per category+object combo, and `category_litter_object_id`/`litter_object_type_id` nullable FK columns on `photo_tags`. Full spec: `readme/TaggingArchitectureSpec.md`.

## Key Files

- `app/Models/Litter/Tags/PhotoTag.php` — Primary tag record (category + object)
- `app/Models/Litter/Tags/PhotoTagExtraTags.php` — Materials, brands, custom tags per tag
- `app/Models/Litter/Tags/Category.php` — Tag categories (smoking, food, etc.)
- `app/Models/Litter/Tags/LitterObject.php` — Taggable objects (butts, wrapper, etc.)
- `app/Models/Litter/Tags/BrandList.php` — Brand records (`brandslist` table)
- `app/Models/Litter/Tags/Materials.php` — Material records (`materials` table)
- `app/Models/Litter/Tags/CustomTagNew.php` — Custom tags (`custom_tags_new` table)
- `app/Models/Litter/Tags/CategoryObject.php` — Pivot: `category_litter_object` + `types()` BelongsToMany
- `app/Models/Litter/Tags/LitterObjectType.php` — Type lookup: "what was in the container" (beer, water, etc.)
- `database/seeds/Tags/GenerateTagsSeeder.php` — Seeds all categories, objects, CLO pivots, materials, and types from TagsConfig. Also ensures `unclassified` system category exists.
- `app/Services/Tags/ClassifyTagsService.php` — Tag classification + deprecated key mapping
- `app/Services/Tags/UpdateTagsService.php` — V4->V5 migration per photo
- `app/Services/Tags/GeneratePhotoSummaryService.php` — Summary JSON + XP from PhotoTags
- `app/Services/Tags/XpCalculator.php` — XP scoring rules
- `app/Enums/Dimension.php` — Tag type enum (object, category, material, brand, custom_tag)

## Invariants

1. **`photo_tags` uses FK columns:** `category_id` and `litter_object_id` (not string columns). Tests must create Category/LitterObject records and use their IDs. **These columns are now NULLABLE** — extra-tag-only tags (brands, materials, custom tags) can exist without a litter object.
2. **`photo_tag_extra_tags` is polymorphic:** `tag_type` is `'material'|'brand'|'custom_tag'`, `tag_type_id` is the FK to the respective table.
3. **Namespace is `App\Models\Litter\Tags\PhotoTag`**, not `App\Models\PhotoTag`.
4. **Summary generation MUST follow any tag change.** Call `$photo->generateSummary()` after creating/updating/deleting PhotoTags.
5. **Unknown tags are auto-created:** `LitterObject::firstOrCreate(['key' => $key], ['crowdsourced' => true])`.
6. **Loose PhotoTags (nullable CLO).** `category_litter_object_id`, `category_id`, `litter_object_id` are all nullable. `AddTagsToPhotoAction::createExtraTagOnly()` creates standalone extra-tag PhotoTags with null CLO fields. `GeneratePhotoSummaryService` counts objects only when `objectId > 0` (variable renamed `$totalLitter` → `$totalObjects`). `XpCalculator` awards object XP only when `object_id > 0` — extra-tag-only tags don't get phantom object XP. Frontend `useXpCalculator.js` mirrors this logic.
7. **No unique constraint on `photo_tags` for (CLO, type) pairs.** There is no DB-level unique constraint on `(photo_id, category_litter_object_id, litter_object_type_id)`. Duplicate CLO+type pairs are possible (each is a separate PhotoTag row). Do NOT assume uniqueness. Extra-tag deduplication (materials/brands within a single tag) is handled via `upsert` inside a single PhotoTag's extra tags, not across multiple PhotoTag rows.
8. **`getNewTags()` serializer contract.** `UsersUploadsController::getNewTags()` conditionally includes `category` and `object` only when both `category_id` and `litter_object_id` resolve. For extra-tag-only PhotoTags (brand/material/custom-only), `category` and `object` are returned as `null`. Always includes `litter_object_type_id` (may be null), `quantity`, `picked_up` (cast to bool with photo-level fallback), and `extra_tags` array.

## Patterns

### Creating a tag with extras

```php
// Create primary tag
$photoTag = PhotoTag::create([
    'photo_id' => $photo->id,
    'category_id' => $category->id,
    'litter_object_id' => $object->id,
    'quantity' => 5,
    'picked_up' => true,
]);

// Attach materials
$photoTag->attachExtraTags([
    ['id' => $plasticId, 'quantity' => 5],
    ['id' => $paperId, 'quantity' => 3],
], 'material', 0);

// Attach brands
$photoTag->attachExtraTags([
    ['id' => $marlboroId, 'quantity' => 3],
], 'brand', 0);
```

### Custom-tag-only tags (no category/object)

```php
$photoTag = PhotoTag::create([
    'photo_id' => $photo->id,
    'custom_tag_primary_id' => $customTag->id,
    'quantity' => $quantity,
    'picked_up' => $pickedUp,
]);
```

### Brand-only tags (no specific object)

```php
$photoTag = PhotoTag::create([
    'photo_id' => $photo->id,
    'category_id' => Category::where('key', 'brands')->value('id'),
    'quantity' => array_sum($brandQuantities),
]);
$photoTag->attachExtraTags($brands, Dimension::BRAND->value, 0);
```

### Deprecated key normalization (v4 -> v5)

```php
// ClassifyTagsService::normalizeDeprecatedTag('beerBottle')
// Returns: ['object' => 'beer_bottle', 'materials' => ['glass']]

// ClassifyTagsService::normalizeDeprecatedTag('coffeeCups')
// Returns: ['object' => 'cup', 'materials' => ['paper']]

// ClassifyTagsService::normalizeDeprecatedTag('butts')
// Returns: ['object' => 'butts', 'materials' => ['plastic', 'paper']]
```

130+ mappings from old camelCase keys to normalized keys with inferred materials.

### Category aliases (CATEGORY_ALIASES)

`ClassifyTagsService::CATEGORY_ALIASES` resolves deprecated v4 category keys: `coastal→marine`, `trashdog→pets`, `dogshit→pets`, `automobile→vehicles`, `pathway→unclassified`, `drugs→unclassified`, `political→unclassified`, `stationery→unclassified`. The public `getCategory(string $rawKey)` method checks aliases before DB lookup.

`TagsConfig` defines 16 active categories (ordered alphabetically): alcohol, art, civic, coffee, dumping, electronics, food, industrial, marine, medical, other, pets, sanitary, smoking, softdrinks, vehicles. The `unclassified` system category is NOT in TagsConfig but is created by `GenerateTagsSeeder` for v4 alias resolution.

### Dimension enum

```php
enum Dimension: string
{
    case LITTER_OBJECT = 'object';   // table: litter_objects
    case CATEGORY = 'category';       // table: categories
    case MATERIAL = 'material';       // table: materials
    case BRAND = 'brand';            // table: brandslist
    case CUSTOM_TAG = 'custom_tag';  // table: custom_tags_new

    public function table(): string
    public static function fromTable(string $table): ?self
}
```

### Database schema

```sql
-- photo_tags: FK columns, NOT strings
photo_tags (
    id, photo_id, category_id, litter_object_id,
    category_litter_object_id,  -- v5.1: nullable FK to category_litter_object (Phase 3: NOT NULL)
    litter_object_type_id,      -- v5.1: nullable FK to litter_object_types
    custom_tag_primary_id,      -- for custom-only tags
    quantity, picked_up,
    created_at, updated_at
)

-- photo_tag_extra_tags: polymorphic extras
photo_tag_extra_tags (
    id, photo_tag_id,
    tag_type,      -- 'material'|'brand'|'custom_tag'
    tag_type_id,   -- FK to materials/brandslist/custom_tags_new
    quantity, index,
    created_at, updated_at
)

-- Reference tables
categories (id, key, parent_id)          -- includes 'unclassified' (hidden from UI)
litter_objects (id, key, crowdsourced)
litter_object_types (id, key, name)      -- v5.1: "what was in the container" (~17 rows)
materials (id, key)
brandslist (id, key, crowdsourced)
custom_tags_new (id, key)
category_litter_object (id, category_id, litter_object_id)  -- CLO pivot

-- v5.1: controls which types are valid per CLO
category_object_types (
    category_litter_object_id,  -- FK to category_litter_object
    litter_object_type_id,      -- FK to litter_object_types
    UNIQUE(category_litter_object_id, litter_object_type_id)
)
```

### TagKeyCache for performance

```php
use App\Services\Achievements\Tags\TagKeyCache;

// Lookup
$id = TagKeyCache::idFor('material', 'glass');         // null if not found
$id = TagKeyCache::getOrCreateId('material', 'glass'); // creates if missing
$key = TagKeyCache::keyFor('material', $id);           // reverse lookup

// Bulk preload (call once at script startup)
TagKeyCache::preloadAll();
```

Three-layer cache: in-memory array -> Redis hash (24h TTL) -> database fallback.

## Web Frontend Tag Types (POST /api/v3/tags)

The Vue frontend sends 4 distinct tag types to `AddTagsToPhotoAction`:

### 1. Object tag (with optional materials/brands/custom_tags)
```json
{ "object": { "id": 5, "key": "butts" }, "quantity": 3, "picked_up": true,
  "materials": [{ "id": 2, "key": "plastic" }], "brands": [], "custom_tags": [] }
```
Backend auto-resolves category from `object->categories()->first()`. Category need NOT be sent.

**Materials and brands accept flexible formats:**
- Materials: `[50, 51]` (plain IDs) or `[{"id": 50}]` (objects). Quantity inherits from parent tag.
- Brands: `[10]` (plain IDs, quantity=1) or `[{"id": 10, "quantity": 3}]` (objects with per-brand quantity).
- `attachMaterials()` and `attachBrands()` both check `is_array($item) ? $item['id'] : $item`.

### 2. Custom-only tag
```json
{ "custom": true, "key": "dirty-bench", "quantity": 1, "picked_up": null }
```
`$tag['custom']` is boolean true (flag), `$tag['key']` is the actual tag name. Creates `CustomTagNew` via `$tag['key']`.

### 3. Brand-only tag
```json
{ "brand_only": true, "brand": { "id": 1, "key": "coca-cola" }, "quantity": 1 }
```
Creates PhotoTag with null category/object, attaches brand as extra tag.

### 4. Material-only tag
```json
{ "material_only": true, "material": { "id": 2, "key": "plastic" }, "quantity": 1 }
```
Same pattern as brand-only — PhotoTag with null FKs, material as extra tag.

### GET /api/tags/all response (v5.1)

```json
{
    "categories": [{"id": 1, "key": "alcohol"}],
    "objects": [{"id": 5, "key": "bottle", "categories": [{"id": 1, "key": "alcohol"}]}],
    "materials": [{"id": 1, "key": "glass"}],
    "brands": [{"id": 7, "key": "heineken"}],
    "types": [{"id": 3, "key": "beer", "name": "Beer"}],
    "category_objects": [{"id": 42, "category_id": 1, "litter_object_id": 5}],
    "category_object_types": [{"category_litter_object_id": 42, "litter_object_type_id": 3}]
}
```

`unclassified` category is excluded from the response. `category_object_types` maps which types are valid per CLO.

### Frontend files
| File | Purpose |
|---|---|
| `resources/js/views/General/Tagging/v2/AddTags.vue` | Main tagging page — dark glass UI, 55/45 split layout, search index with per-(object,category) entries, progress bar, auto-advance, success flash, keyboard shortcuts (/, Escape, J/K/←/→, Enter, Ctrl+Enter, ?), empty state |
| `resources/js/views/General/Tagging/v2/components/UnifiedTagSearch.vue` | Debounced (100ms) search combobox, grouped results (object/type/material/brand/customTag), formatKey display, category breadcrumbs, emerald accent |
| `resources/js/views/General/Tagging/v2/components/TagCard.vue` | Tag card with "Object · Category" display, type pills, picked-up pills, dark glass styling, red border on unresolved CLO |
| `resources/js/views/General/Tagging/v2/components/TaggingHeader.vue` | XP bar (emerald), level titles, unresolved tags warning, submit disabled when unresolved, edit mode badge |
| `resources/js/views/General/Tagging/v2/components/ActiveTagsList.vue` | Container for active tags, keyboard hint in empty state |
| `resources/js/stores/photos/requests.js` | `UPLOAD_TAGS()` → POST, `REPLACE_TAGS()` → PUT, `GET_SINGLE_PHOTO()` |
| `resources/js/stores/user/requests.js` | `REFRESH_USER()` — refreshes user XP/level after tag submission |
| `resources/js/stores/tags/requests.js` | `GET_ALL_TAGS()` → GET /api/tags/all |

### Frontend category disambiguation

The search index generates **one entry per (object, category) pair** with pre-resolved `cloId`, `categoryId`, `categoryKey`. This prevents the bug where searching "bottle" picked `categories[0]` alphabetically (always the first category). Each entry has a precomputed `lowerKey` for fast filtering. Type entries use composite id `type-{cloId}-{typeId}`.

`formatKey(key)` converts `snake_case` → `Title Case` (e.g., `six_pack_rings` → "Six Pack Rings"). Used everywhere in the tagging UI.

`hasUnresolvedTags` computed blocks submit when any object tag lacks a `cloId`. Keyboard shortcuts guard against firing inside form inputs (INPUT/SELECT/TEXTAREA).

### Dark glass design system

All tagging components use a dark glass UI with emerald accent:
- **Background:** `bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900`
- **Glass panels:** `bg-white/5 border border-white/10 rounded-xl`
- **Accent:** Emerald (`text-emerald-400`, `bg-emerald-500`, `focus:border-emerald-500/50`)
- **Text:** `text-white` / `text-white/60` / `text-white/40` / `text-white/30`

**Auto-advance flow:** Submit → success flash (green border pulse, 400ms) → clear tags → advance to next photo.

**Keyboard shortcuts:** `/` focus search, `Escape` blur/close, `J/←` prev, `K/→` next, `Enter` confirm (bare), `Ctrl+Enter` confirm (in input), `?` toggle hints.

## Common Mistakes

- **Using string keys in `photo_tags`.** The table uses `category_id` and `litter_object_id` (integer FKs), not string columns like `'smoking'` or `'butts'`.
- **Forgetting to regenerate summary after tag changes.** Always call `$photo->generateSummary()` after modifying PhotoTags.
- **Looking for PhotoTag in `App\Models\`.** The namespace is `App\Models\Litter\Tags\PhotoTag`.
- **Confusing `brandslist` table name.** Not `brands` — the table is literally `brandslist`.
- **Attaching brands directly to objects.** Brand matching is deferred. Brands go through `attachExtraTags()` or as brand-only PhotoTags.
- **Not handling `custom_tag_primary_id`.** Custom-only tags have no `category_id` or `litter_object_id` — they use `custom_tag_primary_id` instead.
- **Expecting category from frontend.** The web frontend sends `object.id` but NOT `category`. Backend auto-resolves category from `object->categories()->first()`.
- **Reading `$tag['custom']` as the tag name.** It's a boolean flag. The actual name is `$tag['key']`.
- **Checking `$tag['brands']` for brand-only tags.** Brand-only tags use `$tag['brand']` (singular) + `$tag['brand_only']` flag.
- **Using `cot.id` for type entries.** The `category_object_types` API only returns `category_litter_object_id` and `litter_object_type_id` — no `id` column. Use composite key `type-${cot.category_litter_object_id}-${cot.litter_object_type_id}`.
- **Relying on old localStorage recentTags.** Entries from before category disambiguation lack `cloId`. Filter them out on mount: `parsed.filter((t) => t.type !== 'object' || t.cloId)`.
- **Losing `litter_object_type_id` on edit round-trip.** `UsersUploadsController::getNewTags()` must include `litter_object_type_id` in the response, and `convertExistingTags()` must read it into `typeId`. Without this, the type dimension (e.g., "beer" on a "bottle") is lost when editing tags.
- **Replace tags without DB::transaction.** `PhotoTagsController::update()` must wrap delete + reset + add in `DB::transaction()`. If `AddTagsToPhotoAction::run()` throws after tags are deleted, the photo loses all data.
- **Using `||` instead of `??` for counts that can be zero.** `photosStore.untaggedStats.leftToTag || fallback` treats `0` as falsy. Use `??` (nullish coalescing) to only fall through on `null`/`undefined`.
- **Assuming one PhotoTag row per (photo, CLO, type).** There is no unique constraint. Multiple rows with the same `category_litter_object_id` and `litter_object_type_id` can exist on the same photo. Don't add a UNIQUE index or query logic that assumes uniqueness across rows.
- **Expecting `category`/`object` to always be present in `getNewTags()` output.** For brand-only, material-only, or custom-only PhotoTags, `category` and `object` are `null` in the serializer output. The frontend must handle null gracefully.
