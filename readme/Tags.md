# OpenLitterMap v5 Tagging System

## Overview

OpenLitterMap v5 introduces a flexible, hierarchical tagging system that allows precise classification of litter items. Each photo can have multiple tags organized by categories, objects, and their properties (materials, brands, and custom attributes).

## Core Concepts

### Tag Hierarchy

```
Photo
├── PhotoTag (Primary tagged item)
│   ├── Category (e.g., "smoking", "food", "softdrinks")
│   ├── LitterObject (e.g., "butts", "wrapper", "bottle")
│   ├── Quantity (How many of this item)
│   └── PhotoTagExtraTags (Additional properties)
│       ├── Materials (e.g., "plastic", "glass", "aluminium")
│       ├── Brands (e.g., "coca-cola", "marlboro", "mcdonalds")
│       └── CustomTags (User-defined tags)
```

### Database Structure

```
photos
├── id
├── user_id
├── summary (JSON) - Cached tag structure
├── xp (INT) - Calculated experience points
├── total_tags (INT) - Total item count
├── total_brands (INT) - Total brand count
├── processed_at (TIMESTAMP) - When metrics were processed
├── processed_fp (VARCHAR) - Fingerprint for idempotency
├── processed_tags (TEXT) - Cached tags for metrics
├── processed_xp (INT UNSIGNED) - XP value at last metrics processing
└── migrated_at (TIMESTAMP) - v5 migration timestamp

photo_tags
├── id
├── photo_id
├── category_id (NULLABLE - null for extra-tag-only tags)
├── litter_object_id (NULLABLE - null for extra-tag-only tags)
├── category_litter_object_id (NULLABLE - null for extra-tag-only tags)
├── custom_tag_primary_id (for custom-only tags)
├── quantity
└── picked_up (BOOLEAN)

photo_tag_extra_tags
├── photo_tag_id
├── tag_type (material|brand|custom_tag)
├── tag_type_id
├── quantity
└── index
```

### Loose Tags (Extra-Tag-Only PhotoTags)

PhotoTags do **not** require a category/object (CLO). The `category_id`, `litter_object_id`, and `category_litter_object_id` columns are all nullable. This allows brand-only, material-only, and custom-tag-only tags to exist independently without being forced into `unclassified/other`.

**How it works:**
- `AddTagsToPhotoAction::createExtraTagOnly()` creates a PhotoTag with null CLO fields and attaches the brand, material, or custom tag as an extra tag
- `GeneratePhotoSummaryService` only counts `totalLitter` when `objectId > 0` — extra-tag-only tags do not inflate litter counts
- `XpCalculator::calculateFromFlatSummary()` only awards object XP when `object_id > 0` — extra-tag-only tags get no object XP but still earn their own XP (brand=3, material=2, custom=1)
- Frontend `getTagsList()` renders extra-tag-only items directly with their tag key (no category/object prefix)

## Photo Summary Structure

Each photo maintains a `summary` JSON field with this structure:

```json
{
    "tags": {
        "2": {
            "15": {
                "quantity": 5,
                "materials": {
                    "3": 5,
                    "7": 5
                },
                "brands": {
                    "12": 3,
                    "18": 2
                },
                "custom_tags": {}
            }
        }
    },
    "totals": {
        "total_tags": 10,
        "total_objects": 5,
        "by_category": {
            "2": 5
        },
        "materials": 10,
        "brands": 5,
        "custom_tags": 0
    },
    "keys": {
        "categories": {"2": "smoking"},
        "objects": {"15": "butts"},
        "materials": {"3": "plastic", "7": "paper"},
        "brands": {"12": "marlboro", "18": "camel"},
        "custom_tags": {}
    }
}
```

**Key meanings:**
- `"2"` = Category ID (smoking)
- `"15"` = Object ID (butts)
- `"3"` = Material ID (plastic) with quantity 5
- `"7"` = Material ID (paper) with quantity 5
- `"12"` = Brand ID (marlboro) with quantity 3
- `"18"` = Brand ID (camel) with quantity 2

## XP (Experience Points) System

XP rewards users for tagging litter:

| Action           | XP Value    |
|------------------|-------------|
| Upload           | 5           |
| Standard Object  | 1 per item  |
| Material         | 2 per item  |
| Brand            | 3 per item  |
| Custom Tag       | 1 per item  |
| Picked Up        | +5 per object (×qty) where `photo_tags.picked_up=true` |
| Special Objects: |             |
| - Small item     | 10 per item |
| - Medium item    | 25 per item |
| - Large item     | 50 per item |
| - Bags of Litter | 10 per item |

### XP Calculation Details

`AddTagsToPhotoAction::calculateXp()` uses `XpScore` enum multipliers:

- **Upload base:** always 5 XP per photo
- **Object:** `quantity × objectXp` (default 1; special objects override: dumping_small=10, dumping_medium=25, dumping_large=50, bags_litter=10)
- **Brand extra tags:** `brand.quantity × 3` (brands have their own independent quantity)
- **Material extra tags:** `parentTag.quantity × 2` (materials use parent tag's quantity — set membership)
- **Custom tag extra tags:** `parentTag.quantity × 1` (same as materials — set membership)

### XP Calculation Example

```
Photo with:
- 3 cigarette butts (qty=3)
- 2 materials (plastic, paper)
- 1 brand (marlboro, brandQty=2)
- 1 custom tag

XP = 5 (upload base)
   + 3 × 1 (3 objects at 1 XP each)
   + 2 × (3 × 2) (2 materials × parentQty × materialXP)
   + 2 × 3 (brand: brandQty × brandXP)
   + 3 × 1 (custom tag: parentQty × customXP)
   = 5 + 3 + 12 + 6 + 3 = 29 XP
```

## Brand-Object Relationships

### NOTE: Brands are deferred — doing them later.

### Discovery Process
```bash
# Step 1: Discover 1-to-1 relationships
php artisan olm:define-brand-relationships

# Step 2: Create relationships for remaining brands (≥10% threshold)
php artisan olm:auto-create-brand-relationships --apply
```

### How Brands Attach During Migration
1. **Pivot lookup**: Check taggables table for existing relationships
2. **Quantity matching**: Match brands to objects with same quantity
3. **Fallback**: Unmatched brands create brands-only PhotoTag

### Database Structure
```
taggables
├── category_litter_object_id  // Links to pivot table
├── taggable_type              // 'App\Models\Litter\Tags\BrandList'
├── taggable_id                // Brand ID from brandslist
└── quantity                   // Occurrence count
```

```
brandslist table:
├── id              // Primary key
├── key             // Brand key/slug (e.g., "coca-cola", "marlboro")  
├── crowdsourced    // Boolean
└── is_custom       // Boolean
```

## Tag Migration from v4 to v5

### Old Format (v4)
```php
[
    'smoking' => [
        'butts' => 5,
        'cigaretteBox' => 1
    ],
    'brands' => [
        'marlboro' => 3,
        'camel' => 2
    ]
]
```

### New Format (v5)
```php
PhotoTag::create([
    'photo_id' => $photo->id,
    'category_id' => 2,  // smoking
    'litter_object_id' => 15,  // butts
    'quantity' => 5,
    'picked_up' => true
]);

// Attach brands as extra tags
$photoTag->attachExtraTags([
    ['id' => 12, 'quantity' => 3],  // marlboro
    ['id' => 18, 'quantity' => 2],  // camel
], 'brand', 0);
```

## Special Cases

### 1. Brands-Only Tags (Loose Tags)
When a tag has only a brand without a specific object, it is created as an extra-tag-only PhotoTag with null CLO:

```php
// AddTagsToPhotoAction::createExtraTagOnly()
$photoTag = PhotoTag::create([
    'photo_id' => $photo->id,
    'category_id' => null,
    'litter_object_id' => null,
    'category_litter_object_id' => null,
    'quantity' => $quantity,
    'picked_up' => $pickedUp,
]);
$photoTag->attachExtraTags([['id' => $brandId, 'quantity' => $quantity]], 'brand', 0);
```

### 2. Material-Only and Custom-Only Tags (Loose Tags)
Same pattern — PhotoTag with null CLO, extra tag attached:

```php
// Material-only
$photoTag = PhotoTag::create([...null CLO fields...]);
$photoTag->attachExtraTags([['id' => $materialId, 'quantity' => 1]], 'material', 0);

// Custom-only
$photoTag = PhotoTag::create([...null CLO fields...]);
$photoTag->attachExtraTags([['id' => $customTagId, 'quantity' => 1]], 'custom_tag', 0);
```

### 3. Deprecated Tag Mapping
Old tags are automatically mapped to new equivalents:

| Old Tag                | New Object     | Materials Added |
|------------------------|----------------|-----------------|
| `beerBottle`           | `beer_bottle`  | `[glass]`       |
| `beerCan`              | `beer_can`     | `[aluminium]`   |
| `coffeeCups`           | `cup`          | `[paper]`       |
| `plasticFoodPackaging` | `packaging`    | `[plastic]`     |
| `waterBottle`          | `water_bottle` | `[plastic]`     |

**Note**: Materials are automatically added based on the deprecated tag mappings. For example, `beerBottle` automatically adds `glass` material to the object.

Full mapping in `ClassifyTagsService::normalizeDeprecatedTag()`.

### Category Aliases (CATEGORY_ALIASES)

`ClassifyTagsService::CATEGORY_ALIASES` maps deprecated v4 category keys to their v5 equivalents. When a raw category key is encountered during migration or classification, `getCategory(string $rawKey)` checks aliases before querying the database:

| Deprecated Key | Resolves To |
|----------------|-------------|
| `coastal`      | `marine`    |
| `trashdog`     | `pets`      |
| `dogshit`      | `pets`      |
| `automobile`   | `vehicles`  |
| `pathway`      | `unclassified` |
| `drugs`        | `unclassified` |
| `political`    | `unclassified` |
| `stationery`   | `unclassified` |

The `getCategory()` method is public and can be called directly: `$classifyTags->getCategory('coastal')` returns the `marine` Category model.

### TagsConfig Categories

`TagsConfig` defines 16 active categories (ordered alphabetically): alcohol, art, civic, coffee, dumping, electronics, food, industrial, marine, medical, other, pets, sanitary, smoking, softdrinks, vehicles.

The `unclassified` system category is NOT in TagsConfig but is created by `GenerateTagsSeeder` for v4 alias resolution (`ClassifyTagsService` and `UpdateTagsService`).

### 4. Unknown Tags
Unknown tags are automatically created as new objects:

```php
$created = LitterObject::firstOrCreate(
    ['key' => 'mystery_item'],
    ['crowdsourced' => true]
);
```

### 5. Multiple Brands per Object
A single object can have multiple brands attached:
- Example: `butts` object with both `marlboro` and `camel` brands
- Stored in `photo_tag_extra_tags` with `tag_type='brand'`

### 6. Multiple Objects per Brand
Brands can validly attach to multiple objects:
- Example: `mcdonalds` → `cup`, `packaging`, `lid`, `wrapper`
- Relationships defined in `taggables` table

## Validation Rules

- Quantities must be positive integers (enforced by `max(1, ...)` — never 0)
- IDs are always positive integers, never 0
- Category-Object relationships must be valid (when present)
- Materials, brands, and custom tags can exist as standalone extra-tag-only PhotoTags (null CLO) or attached to an object tag
- XP calculation uses enum-defined values
- Fingerprinting prevents duplicate processing

## Deduplication & Uniqueness

**PhotoTags:** There is no unique database constraint on `(photo_id, category_litter_object_id, litter_object_type_id)`. Duplicate CLO+type combinations are theoretically possible under a race condition (e.g., concurrent POST requests). In practice, this is prevented by the transaction wrapping in `AddTagsToPhotoAction::run()`.

**PhotoTagExtraTags:** Extra tags (brands, materials, custom tags) are deduplicated within a single PhotoTag via `upsert()` on the composite key `['photo_tag_id', 'tag_type', 'tag_type_id']`. Duplicate extra tags submitted in one request are merged rather than inserted twice.

## `getNewTags()` Serializer Details

`UsersUploadsController::getNewTags()` builds the `new_tags` array for the uploads API response:

- `category` and `object` keys are only included when **both** relations resolve (i.e., `category_id != null && litter_object_id != null`). Extra-tag-only PhotoTags omit these keys.
- `extra_tags` key is only included when the PhotoTag has at least one extra tag. Empty extra_tags arrays are not serialized.
- `litter_object_type_id` is always included — required for edit round-trips to restore the type dimension.
- `quantity` is always >= 1.

## API Response Format

```json
{
  "photo_id": 12345,
  "tags": {
    "smoking": {
      "butts": {
        "quantity": 5,
        "materials": ["plastic", "paper"],
        "brands": ["marlboro", "camel"]
      }
    }
  },
  "metrics": {
    "total_items": 5,
    "total_brands": 2,
    "xp_earned": 30
  },
  "location": {
    "country": "Ireland",
    "state": "Munster",
    "city": "Cork"
  }
}
```

## Web Frontend Replace/Edit Tags (PUT /api/v3/tags)

The `/tag?photo=<id>` URL loads a specific photo for editing. If the photo already has tags, AddTags.vue enters **edit mode** and uses `PUT /api/v3/tags` to replace all existing tags.

### Flow

1. **Load photo:** `GET_SINGLE_PHOTO(id)` calls `/api/v3/user/photos?id=X&id_operator==&per_page=1` — filters by authenticated user (ownership enforced server-side)
2. **Convert existing tags:** `convertExistingTags(photo)` transforms `new_tags` API format back into the frontend's tag format (handles object, brand-only, material-only, custom-only)
3. **User edits tags** — same UI as normal tagging (search, add, remove, quantity, materials/brands)
4. **Submit:** `REPLACE_TAGS({ photoId, tags })` calls `PUT /api/v3/tags`

### Backend (`PhotoTagsController::update()`)

The entire replace operation is wrapped in `DB::transaction()` to prevent data loss if any step fails (e.g., old tags deleted but new tags fail to save).

```php
DB::transaction(function () use ($photo, $validatedData) {
    // 1. Delete old tags + extras
    $photo->photoTags()->each(function ($tag) {
        $tag->extraTags()->delete();
        $tag->delete();
    });

    // 2. Reset summary, XP, verification
    $photo->update(['summary' => null, 'xp' => 0, 'verified' => 0]);

    // 3. Re-add tags (generates new summary, XP, fires TagsVerifiedByAdmin)
    $this->addTagsToPhotoAction->run(Auth::id(), $photo->id, $validatedData['tags']);
});
```

**MetricsService delta handling:** When `TagsVerifiedByAdmin` fires, `ProcessPhotoMetrics` → `MetricsService::processPhoto()` detects the photo was previously processed (has `processed_at`). It calls `doUpdate()` which calculates deltas between old `processed_tags` and the new summary, then applies positive/negative adjustments to all MySQL + Redis metrics.

### Security

- `ReplacePhotoTagsRequest` checks `$photo->user_id === $this->user()->id` — returns 403 for non-owners
- `GET_SINGLE_PHOTO` calls `/api/v3/user/photos` which filters by `Auth::user()->id` — cannot load another user's photo
- Both `PhotoTagsRequest` (POST) and `ReplacePhotoTagsRequest` (PUT) enforce ownership

### Frontend files

| File | Change for edit mode |
|---|---|
| `AddTags.vue` | Reads `route.query.photo`, loads specific photo, `isEditMode` ref, `convertExistingTags()`, uses `REPLACE_TAGS` on submit |
| `TaggingHeader.vue` | `isEditMode` prop — hides Skip/Pagination, shows "Editing" badge, "Update" button |
| `Uploads.vue` | Navigates to `/tag?photo=<id>` on photo click and "Tag this photo" link |
| `stores/photos/requests.js` | `GET_SINGLE_PHOTO()`, `REPLACE_TAGS()` actions |
| `stores/user/requests.js` | `REFRESH_USER()` — refreshes user XP/level after tag submission |

### Type ID preservation on edit

`UsersUploadsController::getNewTags()` includes `litter_object_type_id` in the `new_tags` response. `convertExistingTags()` in `AddTags.vue` reads this to populate `typeId` on each tag, so the type dimension survives edit round-trips.

### Frontend guards

- **Double-submit prevention:** `isSubmitting` ref blocks `submitTags()` re-entry on rapid clicks or Ctrl+Enter
- **XP bar refresh:** After successful POST or PUT, `REFRESH_USER()` is called (non-blocking) to update the nav XP bar with server-side totals
- **Parallel store refresh:** `UPLOAD_TAGS` refreshes stats and photos via `Promise.all()` to avoid stale intermediate state
- **Nullish coalescing:** `TaggingHeader.vue` uses `??` (not `||`) for `untaggedCount` — prevents `0` from falling through to stale `total`
- **imageLoading guard:** `handleNavigation` only sets `imageLoading = true` when `currentPhoto` exists, preventing stuck skeleton on empty pages

### Test file

`tests/Feature/Tags/ReplacePhotoTagsTest.php` — 5 tests (replace tags, already-tagged photos, ownership, auth, extra tags cleanup)

---

## Web Frontend Tagging (POST /api/v3/tags)

The Vue frontend (`/tag` route → `AddTags.vue`) sends tags via `POST /api/v3/tags` to `PhotoTagsController` → `AddTagsToPhotoAction` (v5). The frontend sends 4 distinct tag types:

### 1. Object tag (with optional materials/brands/custom tags)
```json
{
    "object": { "id": 5, "key": "butts" },
    "quantity": 3,
    "picked_up": true,
    "materials": [{ "id": 2, "key": "plastic" }],
    "brands": [{ "id": 1, "key": "marlboro" }],
    "custom_tags": ["dirty-bench"]
}
```
**Backend:** `resolveTag()` looks up object, auto-resolves category from `object->categories()->first()`. Category need NOT be sent.

### 2. Custom-only tag
```json
{ "custom": true, "key": "dirty-bench", "quantity": 1, "picked_up": null }
```
**Backend:** `$tag['custom']` is boolean true (flag), `$tag['key']` is the actual tag name. Creates `CustomTagNew` record via `$tag['key']`.

### 3. Brand-only tag
```json
{ "brand_only": true, "brand": { "id": 1, "key": "coca-cola" }, "quantity": 1, "picked_up": null }
```
**Backend:** Creates PhotoTag with `category_id=null`, `litter_object_id=null`, attaches brand as extra tag.

### 4. Material-only tag
```json
{ "material_only": true, "material": { "id": 2, "key": "plastic" }, "quantity": 1, "picked_up": null }
```
**Backend:** Same pattern as brand-only — PhotoTag with null FKs, material as extra tag.

### Frontend files

| File | Purpose |
|---|---|
| `resources/js/views/General/Tagging/v2/AddTags.vue` | Main tagging page — search index, tag selection, submit |
| `resources/js/views/General/Tagging/v2/components/UnifiedTagSearch.vue` | Debounced tag search combobox with grouped results |
| `resources/js/views/General/Tagging/v2/components/TagCard.vue` | Tag card with type pills, category display, formatKey |
| `resources/js/views/General/Tagging/v2/components/ActiveTagsList.vue` | Container for active tags |
| `resources/js/views/General/Tagging/v2/components/TaggingHeader.vue` | Header: XP bar, level title, pagination, unresolved warning |
| `resources/js/views/General/Tagging/v2/components/PhotoViewer.vue` | Photo display with zoom |
| `resources/js/stores/photos/requests.js` | `UPLOAD_TAGS()` → POST, `REPLACE_TAGS()` → PUT, `GET_SINGLE_PHOTO()` |
| `resources/js/stores/tags/requests.js` | `GET_ALL_TAGS()` → GET /api/tags/all |

### Tag data loading
`GET /api/tags/all` returns flat arrays: `{ categories, objects, materials, brands, types, category_objects, category_object_types }`. Objects include their categories via eager load: `LitterObject::with(['categories:id,key'])`. `category_object_types` only returns `category_litter_object_id` and `litter_object_type_id` (no `id` column).

### Frontend search index (category disambiguation)

`AddTags.vue` builds a `searchableTags` computed that generates **one entry per (object, category) pair** instead of one per object. This prevents data corruption when the same object exists in multiple categories (e.g., "bottle" exists in alcohol, beverages, and food).

Each entry has:
- `id`: composite `obj-{objectId}-cat-{categoryId}` for deduplication
- `cloId`: pre-resolved `category_litter_object_id` from the store's `getCloId(categoryId, objectId)`
- `categoryId`, `categoryKey`: the specific category for this entry
- `lowerKey`: precomputed `key.toLowerCase()` for fast search filtering

**Type entries** are also generated from `categoryObjectTypes` with composite id `type-{cloId}-{typeId}`. Type search results show the parent object and category as context.

### Display formatting

`formatKey(key)` converts `snake_case` keys to `Title Case`: `key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())`. Used in search results, tag cards, detail badges, and recent tags.

Tag cards show `"Bottle · Alcohol"` format (object + category). Type pills replace the old `<select>` dropdown — clicking an active pill deselects it.

### Validation

`hasUnresolvedTags` computed blocks submit when any object tag lacks a pre-resolved `cloId`. Unresolved tags show a red border and the TaggingHeader shows a warning indicator. Submit button is disabled.

### Dark Glass UI (2026-03-02)

The tagging frontend uses a dark glass design system with these tokens:
- **Background:** `bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900`
- **Glass panels:** `bg-white/5 border border-white/10 rounded-xl`
- **Accent color:** Emerald (`text-emerald-400`, `bg-emerald-500`, `focus:border-emerald-500/50`)
- **Text hierarchy:** `text-white`, `text-white/60`, `text-white/40`, `text-white/30`

**Layout:** Two-panel split — 55% photo (left) / 45% tags (right). On mobile, photo takes `h-[40vh]` with tags scrolling below.

**Progress bar:** Thin emerald bar at top showing `taggedCount / totalPhotos` progress.

**Auto-advance:** After successful tag submission, a green flash overlay appears (400ms), tags clear, and the next photo loads automatically.

**Empty state:** When all photos are tagged, shows a celebratory emerald checkmark with links to Upload more or view My Photos.

### Keyboard shortcuts

| Key | Action | Works in input? |
|-----|--------|----------------|
| `/` | Focus search | No |
| `Escape` | Blur input / close shortcuts panel | Yes |
| `J` / `ArrowLeft` | Previous photo | No |
| `K` / `ArrowRight` | Next photo | No |
| `Enter` | Confirm tags (bare, not in input) | No |
| `Ctrl+Enter` | Confirm tags | Yes |
| `?` | Toggle shortcuts hint panel | No |

All shortcuts except Escape and Ctrl+Enter early-return if the focused element is INPUT, SELECT, or TEXTAREA. `hasUnresolvedTags` blocks confirm shortcuts.

### Search UX

`UnifiedTagSearch.vue` uses 100ms debounce on the search query. Results are grouped by type: `['object', 'type', 'material', 'brand', 'customTag']`. Category breadcrumbs are shown for object results, parent object names for type results.

### Level titles

`TaggingHeader.vue` displays user level titles from a hardcoded map matching `config/levels.php` (50 entries: "Beginner" through "Founder").

---

## Related Docs

- **Migration.md** — v4→v5 migration rules, brand matching logic, deprecated mappings
- **MigrationScript.md** — how to run the `olm:v5` artisan command
- **Upload.md** — upload/tagging architecture, metrics pipeline, Redis key alignment
- **Mobile.md** — mobile v4 tag shim (ConvertV4TagsAction)
