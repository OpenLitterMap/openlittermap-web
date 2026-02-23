---
name: tagging-system
description: PhotoTag, PhotoTagExtraTags, categories, litter objects, materials, brands, ClassifyTagsService, GeneratePhotoSummaryService, tag migration, and the v4-to-v5 conversion.
---

# Tagging System

V5 uses a normalized hierarchy: Photo -> PhotoTag (category + object + quantity) -> PhotoTagExtraTags (materials, brands, custom tags). All tag data lives in `photo_tags` and `photo_tag_extra_tags` tables — not the old per-category tables.

## Key Files

- `app/Models/Litter/Tags/PhotoTag.php` — Primary tag record (category + object)
- `app/Models/Litter/Tags/PhotoTagExtraTags.php` — Materials, brands, custom tags per tag
- `app/Models/Litter/Tags/Category.php` — Tag categories (smoking, food, etc.)
- `app/Models/Litter/Tags/LitterObject.php` — Taggable objects (butts, wrapper, etc.)
- `app/Models/Litter/Tags/BrandList.php` — Brand records (`brandslist` table)
- `app/Models/Litter/Tags/Materials.php` — Material records (`materials` table)
- `app/Models/Litter/Tags/CustomTagNew.php` — Custom tags (`custom_tags_new` table)
- `app/Models/Litter/Tags/CategoryObject.php` — Pivot: `category_litter_object`
- `app/Services/Tags/ClassifyTagsService.php` — Tag classification + deprecated key mapping
- `app/Services/Tags/UpdateTagsService.php` — V4->V5 migration per photo
- `app/Services/Tags/GeneratePhotoSummaryService.php` — Summary JSON + XP from PhotoTags
- `app/Services/Tags/XpCalculator.php` — XP scoring rules
- `app/Enums/Dimension.php` — Tag type enum (object, category, material, brand, custom_tag)

## Invariants

1. **`photo_tags` uses FK columns:** `category_id` and `litter_object_id` (not string columns). Tests must create Category/LitterObject records and use their IDs.
2. **`photo_tag_extra_tags` is polymorphic:** `tag_type` is `'material'|'brand'|'custom_tag'`, `tag_type_id` is the FK to the respective table.
3. **Namespace is `App\Models\Litter\Tags\PhotoTag`**, not `App\Models\PhotoTag`.
4. **Summary generation MUST follow any tag change.** Call `$photo->generateSummary()` after creating/updating/deleting PhotoTags.
5. **Unknown tags are auto-created:** `LitterObject::firstOrCreate(['key' => $key], ['crowdsourced' => true])`.

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
    custom_tag_primary_id,  -- for custom-only tags
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
categories (id, key, parent_id)
litter_objects (id, key, crowdsourced)
materials (id, key)
brandslist (id, key, crowdsourced)
custom_tags_new (id, key)
category_litter_object (id, category_id, litter_object_id)  -- pivot
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

## Common Mistakes

- **Using string keys in `photo_tags`.** The table uses `category_id` and `litter_object_id` (integer FKs), not string columns like `'smoking'` or `'butts'`.
- **Forgetting to regenerate summary after tag changes.** Always call `$photo->generateSummary()` after modifying PhotoTags.
- **Looking for PhotoTag in `App\Models\`.** The namespace is `App\Models\Litter\Tags\PhotoTag`.
- **Confusing `brandslist` table name.** Not `brands` — the table is literally `brandslist`.
- **Attaching brands directly to objects.** Brand matching is deferred. Brands go through `attachExtraTags()` or as brand-only PhotoTags.
- **Not handling `custom_tag_primary_id`.** Custom-only tags have no `category_id` or `litter_object_id` — they use `custom_tag_primary_id` instead.
