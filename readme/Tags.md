# OpenLitterMap v5 Tagging System

The v5 tagging system classifies litter with a flexible, hierarchical model. Each photo can carry multiple tags organised by category, object, and type, with optional materials, brands, and custom tags as extras. This document is the operational reference for the **currently deployed** architecture.

## Core Concepts

### What a tag records

When a person tags litter, they record:

```
I found [QUANTITY] × [OBJECT]
  that was a [TYPE] product          (optional)
  made of [MATERIAL(s)]              (optional)
  branded [BRAND(s)]                 (optional)
  in the [CATEGORY] of litter
  and I [PICKED IT UP / LEFT IT]     (optional)
  with optional [CUSTOM NOTE]        (optional)
```

| Dimension | Cardinality | Stored on |
|-----------|-------------|-----------|
| Category | 0 or 1 | `photo_tags.category_id` (derived from CLO). Null for extra-tag-only tags |
| LitterObject | 0 or 1 | `photo_tags.litter_object_id` (derived from CLO). Null for extra-tag-only tags |
| LitterObjectType | 0 or 1 | `photo_tags.litter_object_type_id` |
| Material | 0 to many (set membership, qty=1 in DB, weighted by tag qty in metrics) | `photo_tag_extra_tags` |
| Brand | 0 to many (independent quantities) | `photo_tag_extra_tags` |
| Custom tag | 0 to many (set membership, qty=1 in DB, weighted by tag qty in metrics) | `photo_tag_extra_tags` |
| Quantity | exactly 1 | `photo_tags.quantity` |
| Picked up | 0 or 1 | `photo_tags.picked_up` |

**Rule:** 1:1 dimensions live on the tag row. 1:many dimensions live in the extras table.

### Design principles

1. **An object key represents the physical thing** — `bottle`, `can`, `cup` (not `beer_bottle`, `water_bottle`).
2. **Product context is a separate dimension** — `beer`, `water`, `soda` via `LitterObjectType`.
3. **Category + Object pairing is always explicit** via a `category_litter_object_id` (CLO) — no `categories()->first()` inference, which breaks when an object belongs to multiple categories.
4. **Optional dimensions don't block data collection** — students can skip type/material/brand.
5. **Extra-tag-only tags allow null CLO** — brand-only, material-only, and custom-only PhotoTags have nullable `category_id`, `litter_object_id`, and `category_litter_object_id`. They are not forced into `unclassified/other`.
6. **Types are strictly "what was in it"** — beer, water, juice. Not container forms (pint, shot) or physical states (crushed, degraded).
7. **Materials are set membership, not counted** — a bottle is glass, full stop. Only brands carry independent quantities.

### Tag hierarchy

```
Photo
└── PhotoTag (one row per tagged item)
    ├── Category          (e.g. "alcohol", "food", "smoking")
    ├── LitterObject      (e.g. "bottle", "wrapper", "butts")
    ├── LitterObjectType  (optional, e.g. "beer", "water")
    ├── Quantity          (how many of this item)
    ├── PickedUp          (nullable boolean)
    └── PhotoTagExtraTags (additional properties)
        ├── Materials     (e.g. "plastic", "glass", "aluminium")
        ├── Brands        (e.g. "coca-cola", "marlboro", "mcdonalds")
        └── CustomTags    (user-defined notes)
```

The category+object pairing is identified by a single `category_litter_object_id` (CLO). Each (category, object) combination is a row in the `category_litter_object` pivot with its own auto-increment ID, so `bottle` in alcohol (e.g. CLO id 42) is a different selection than `bottle` in beverages (e.g. CLO id 43). `category_id` and `litter_object_id` on `photo_tags` are denormalised from the CLO and resolved on write (see [Denormalisation](#denormalisation-integrity)).

## Database Schema

### Lookup tables (seeded, small)

| Table | Model | Role |
|-------|-------|------|
| `categories` | `Category` | Category lookup (16 public + 1 hidden `unclassified`) |
| `litter_objects` | `LitterObject` | Canonical physical objects: `bottle`, `can`, `cup`, `butts`, `wrapper`… |
| `litter_object_types` | `LitterObjectType` | ~17 product/contents types: `beer`, `wine`, `water`, `soda`, `unknown`… |
| `materials` | `Materials` | ~30 material rows |
| `brandslist` | `BrandList` | Brand lookup |
| `custom_tags_new` | `CustomTagNew` | User-generated custom tags |
| `litter_states` | `LitterState` | Physical condition (degraded, micro, macro) — orthogonal, wired via `taggables` |

### Relationship pivots

```sql
category_litter_object (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PK,
    category_id         FK → categories NOT NULL,
    litter_object_id    FK → litter_objects NOT NULL,
    UNIQUE(category_id, litter_object_id)
)
-- Model: CategoryObject (Pivot). This IS the CLO. Each row's id is the
-- category_litter_object_id stored on photo_tags.

category_object_types (
    category_litter_object_id   FK → category_litter_object NOT NULL,
    litter_object_type_id       FK → litter_object_types NOT NULL,
    UNIQUE(category_litter_object_id, litter_object_type_id)
)
-- Controls which type options appear per category+object combo.
-- Dedicated pivot (not polymorphic) — types are structural, not user-generated.
```

### Tag tables (per-photo data)

```sql
photo_tags (
    id                          BIGINT UNSIGNED AUTO_INCREMENT PK,
    photo_id                    FK → photos NOT NULL,
    category_litter_object_id   FK → category_litter_object NULL,   -- null for extra-tag-only tags
    category_id                 FK → categories NULL,                -- denormalised from CLO; null for extra-tag-only tags
    litter_object_id            FK → litter_objects NULL,            -- denormalised from CLO; null for extra-tag-only tags
    litter_object_type_id       FK → litter_object_types NULL,
    custom_tag_primary_id       FK → custom_tags_new NULL,           -- legacy custom-only support (see PostMigrationCleanup.md)
    quantity                    INT UNSIGNED NOT NULL DEFAULT 1,
    picked_up                   BOOLEAN NULL                         -- true / false / null
)

photo_tag_extra_tags (
    id              BIGINT UNSIGNED AUTO_INCREMENT PK,
    photo_tag_id    FK → photo_tags NOT NULL,
    tag_type        ENUM('material', 'brand', 'custom_tag') NOT NULL,
    tag_type_id     INT UNSIGNED NOT NULL,
    quantity        INT UNSIGNED DEFAULT 1
)
-- Upsert dedup key: (photo_tag_id, tag_type, tag_type_id).
-- Materials/custom tags: quantity always 1 in DB (set membership).
-- Brands: independent count (e.g. 3 Heineken + 2 Coca-Cola out of 5 bottles).
```

> **Denormalisation note:** `category_id` and `litter_object_id` are derived from `category_litter_object_id` and must always match. They are resolved from the CLO on write in `AddTagsToPhotoAction`. They exist because `MetricsService` and Redis keys reference `category_id`/`object_id` directly, avoiding joins in the hottest code path. See [Denormalisation integrity](#denormalisation-integrity).

> **Pending cleanup:** `custom_tag_primary_id` on `photo_tags` and the `brand_id` legacy column are slated for removal. See `readme/PostMigrationCleanup.md`.

### Loose / extra-tag-only PhotoTags

A PhotoTag does **not** require a category/object (CLO). The `category_id`, `litter_object_id`, and `category_litter_object_id` columns are all nullable. This lets brand-only, material-only, and custom-tag-only tags exist independently without being forced into `unclassified/other`.

How it works:
- `AddTagsToPhotoAction::createExtraTagOnly()` creates a PhotoTag with null CLO fields and attaches the brand, material, or custom tag as an extra tag.
- `GeneratePhotoSummaryService` only counts `totals.litter` when `object_id > 0` — extra-tag-only tags do not inflate litter counts.
- Object XP is only awarded when `object_id > 0` — extra-tag-only tags earn no object XP but still earn their own extra-tag XP (brand=3, material=2, custom=1).
- Frontend `getTagsList()` renders extra-tag-only items directly with their tag key (no category/object prefix).

## Photo Summary JSON

`GeneratePhotoSummaryService::run()` builds the photo `summary` column as a **flat array**, where each entry in `tags[]` maps **1:1 to a `photo_tags` row**. (An earlier v5.0 nested-dict format — `tags.{cat_id}.{obj_id}` — collided when the same category+object appeared with different types; it has been replaced.)

```json
{
  "tags": [
    {
      "clo_id": 42,
      "category_id": 1,
      "object_id": 5,
      "type_id": 3,
      "quantity": 2,
      "picked_up": true,
      "materials": [1, 4],
      "brands": {"7": 1},
      "custom_tags": [45]
    }
  ],
  "totals": {
    "litter": 3,
    "materials": 3,
    "brands": 1,
    "custom_tags": 0
  },
  "keys": {
    "categories": {"1": "alcohol"},
    "objects": {"5": "bottle"},
    "types": {"3": "beer"},
    "materials": {"1": "glass"},
    "brands": {"7": "heineken"}
  }
}
```

### Format rules

- **`tags[]`** — one entry per `photo_tags` row (1:1). `clo_id` is the `category_litter_object_id` (null for extra-tag-only tags); `category_id`/`object_id` are the denormalised ids (`0` when absent); `type_id` is the optional `litter_object_type_id`; `picked_up` is cast to a strict boolean.
- **`materials`** and **`custom_tags`** are **ID arrays** — set membership, no per-item quantity (the parent tag's `quantity` weights them in metrics).
- **`brands`** is an **`{id: quantity}` map** — brands carry independent quantities. Serialised as a JSON object (empty = `{}`).
- **`totals.litter`** = sum of object quantities across all tags (extra-tag-only tags excluded). `totals.materials`/`custom_tags` are weighted by parent tag quantity (2 glass beer bottles + 1 glass wine bottle = 3 glass items). `totals.brands` = sum of independent brand quantities.
- **`keys`** is a reverse-lookup of id → key for human-readable display. Each sub-map (`categories`, `objects`, `types`, `materials`, `brands`, `custom_tags`) is **omitted when empty** (`array_filter`), and the entire `keys` block is omitted if all sub-maps are empty.

### What the service writes

Alongside `summary`, `GeneratePhotoSummaryService::run()` updates the photo:
- `xp` — see [XP System](#xp-experience-points-system).
- `total_tags` = `litter + materials + brands + custom_tags` (combined count).
- `total_brands` = total brand quantity.
- `result_string` — write-only legacy map-display string (`category.object qty,…`); active code reads `summary`/`total_tags`.

## XP (Experience Points) System

XP is defined by the `XpScore` enum (`app/Enums/XpScore.php`) — never hardcode XP values.

| Action | XP value |
|--------|----------|
| Upload | 5 (per photo) |
| Standard object | 1 per item (× quantity) |
| Brand | 3 per item (× brand's own quantity) |
| Material | 2 per item (× parent tag quantity) |
| Custom tag | 1 per item (× parent tag quantity) |
| Picked up | +5 per object × quantity where `photo_tags.picked_up = true` (objects only) |
| Special object — Small (`dumping_small` / `dumping`+`small`) | 10 per item |
| Special object — Medium (`dumping_medium` / `dumping`+`medium`) | 25 per item |
| Special object — Large (`dumping_large` / `dumping`+`large`) | 50 per item |
| Special object — Bags of litter (`bags_litter`) | 10 per item |

### How XP is computed

- **Tag XP** is calculated inside `GeneratePhotoSummaryService::run()` via `XpCalculator::calculateFromTags()`:
  - **Object:** `quantity × objectXp`. Default object XP is 1; special objects override via `XpScore::getObjectXp()` (supports both legacy keys like `dumping_small` and v5 `dumping` + size-type keys).
  - **Brand:** `brand.quantity × 3` (brands have independent quantities).
  - **Material:** `parentTag.quantity × 2` (set membership — weighted by parent tag quantity).
  - **Custom tag:** `parentTag.quantity × 1` (same set-membership weighting).
- **Picked-up bonus** is then added per tag: `XpScore::PickedUp->xp() × quantity` for every PhotoTag with `picked_up = true` **and** a non-null `litter_object_id` (objects only — brand/material/custom-only loose tags get no picked-up bonus).
- **Upload base (5 XP)** is awarded separately by `UploadPhotoController` at upload time, not inside the summary service. `XpCalculator::calculateFromTags()` intentionally returns tag XP only.

> `LitterObjectType` selection earns 0 XP — it is optional enrichment data, not something to incentivise guessing on.

### Worked example

```
Photo with one tag:
- 3 cigarette butts (object, quantity = 3, picked_up = true)
- 2 materials (plastic, paper)
- 1 brand (marlboro, brand quantity = 2)
- 1 custom tag

Tag XP = 3 × 1            (3 objects @ 1 XP)
       + 2 × (3 × 2)      (2 materials × parentQty 3 × 2 XP each = 12)
       + 2 × 3            (brand: brandQty 2 × 3 XP = 6)
       + 3 × 1            (custom: parentQty 3 × 1 XP = 3)
       = 3 + 12 + 6 + 3 = 24

Picked-up bonus = 3 × 5 = 15      (object qty 3 × PickedUp 5)
Upload base (added by UploadPhotoController) = 5

Total photo XP = 24 + 15 + 5 = 44
```

XP details and level thresholds: see `readme/XP.md`.

## Tagging Pipeline (`AddTagsToPhotoAction`)

`AddTagsToPhotoAction::run($userId, $photoId, $tags, $skipVerification = false)` is the single entry point for adding tags. Everything runs inside a `DB::transaction()`:

1. **Create rows** — `addTagsToPhoto()` iterates the payload, detecting the format per tag:
   - `createTagFromClo()` when `category_litter_object_id` is present (resolves denormalised `category_id`/`litter_object_id` from the CLO).
   - `createExtraTagOnly()` when the tag is brand-only / material-only / custom-only (null CLO fields).
   - `createTagLegacy()` for the legacy `{ object: {id, key}, … }` payload (auto-resolves category from the object).
2. **Generate summary + XP** — `$photo->generateSummary()` calls `GeneratePhotoSummaryService`, populating `summary`, `xp`, `total_tags`, `total_brands`.
3. **Verification** — unless `skipVerification` is true, `updateVerification()` runs. For trusted/non-school users it fires `TagsVerifiedByAdmin`, which drives `ProcessPhotoMetrics → MetricsService::processPhoto()`. Admin controllers pass `skipVerification = true` because they handle verification + metrics atomically themselves.

A null summary (zero tags) yields zero metrics. Summary + XP are generated regardless of trust level; metrics processing is what's gated by trust/school status (see `readme/SchoolPipeline.md`).

### Validation rules

1. `category_litter_object_id` must exist in the `category_litter_object` pivot (when present — null for extra-tag-only tags).
2. If `litter_object_type_id` is present, it must exist in `category_object_types` for this CLO.
3. `quantity` must be a positive integer (`max(1, …)` — never 0). IDs are always positive, never 0.
4. Each material ID must exist in `materials`; each brand ID in `brandslist`.
5. **Material extras:** quantity forced to 1 on write; metrics weight by parent tag quantity.
6. **Brand extras:** quantity is an independent count; sum of brand quantities per tag ≤ tag quantity.
7. **Custom tag extras:** quantity forced to 1 on write; metrics weight by parent tag quantity.
8. **`other` object:** require at least one extra tag (brand, material, or custom_tag) to prevent empty tags.
9. **Extra-tag-only (loose) tags:** null `category_id`/`litter_object_id`/`category_litter_object_id`, created via `createExtraTagOnly()`. No object XP, only extra-tag XP. Do not count toward `totals.litter`.

### Denormalisation integrity

`category_id` and `litter_object_id` on `photo_tags` must match the referenced CLO. This is enforced on write in `AddTagsToPhotoAction` (resolved from the CLO). The `olm:verify-tag-integrity` artisan command detects and repairs CLO↔denorm drift — run it after migrations, seeders, and data scripts.

### Deduplication & uniqueness

- **PhotoTags:** there is no DB unique constraint on `(photo_id, category_litter_object_id, litter_object_type_id)`. Duplicate CLO+type combinations are theoretically possible under a concurrent-request race, but in practice prevented by the transaction wrapping in `AddTagsToPhotoAction::run()`.
- **PhotoTagExtraTags:** extras are deduplicated within a single PhotoTag via `upsert()` on the composite key `(photo_tag_id, tag_type, tag_type_id)`. Duplicate extras in one request are merged, not inserted twice.

## Categories & Objects

`TagsConfig` defines 16 active categories (ordered alphabetically): `alcohol`, `art`, `civic`, `coffee`, `dumping`, `electronics`, `food`, `industrial`, `marine`, `medical`, `other`, `pets`, `sanitary`, `smoking`, `softdrinks`, `vehicles`.

The `unclassified` system category is NOT in `TagsConfig`; it is created by `GenerateTagsSeeder` and used by `ClassifyTagsService` / `UpdateTagsService` as the catch-all for legacy null-null tags. It is hidden from the UI.

`TagsConfig` helper methods (use these instead of hardcoding lists): `buildObjectMap()`, `buildObjectMaps()`, `allMaterialKeys()`, `allTypeKeys()`.

### Shared objects (multi-category via pivot)

Some objects exist under several categories; each combination is its own CLO row:

| Object | Categories |
|--------|------------|
| bottle | alcohol, beverages, food |
| can | alcohol, beverages, food |
| cup | alcohol, beverages |
| broken_glass | alcohol, beverages |
| packaging | alcohol, beverages, food, smoking |
| lid | beverages, food |
| battery | vehicles, electronics |
| other | all categories (including unclassified) |

`pint_glass`, `wine_glass`, `shot_glass` are NOT shared — they exist only under alcohol. They are distinct physical objects, not types.

### LitterObjectTypes

Types represent **what was in the container** — product/contents only. Not container forms (pint, shot), sizes, or physical states.

| Key | Name | Example use |
|-----|------|-------------|
| beer | Beer | alcohol: bottle, can, pint_glass |
| wine | Wine | alcohol: bottle, wine_glass |
| spirits | Spirits | alcohol: bottle, can, shot_glass |
| cider | Cider | alcohol: bottle, can, pint_glass |
| water | Water | beverages: bottle |
| soda | Soda | beverages: bottle, can, cup |
| juice | Juice | beverages: bottle, can, carton |
| energy | Energy Drink | beverages: bottle, can |
| sports | Sports Drink | beverages: bottle |
| coffee | Coffee | beverages: cup |
| tea | Tea | beverages: bottle, cup |
| milk | Milk | beverages: bottle, carton |
| smoothie | Smoothie | beverages: bottle, cup |
| iced_tea | Iced Tea | beverages: can, carton |
| sparkling_water | Sparkling Water | beverages: can |
| plant_milk | Plant Milk | beverages: carton |
| unknown | Unknown | required on every typed CLO — selected when contents are unidentifiable |

**Seeding rule:** any CLO with entries in `category_object_types` must include `unknown`, so users always have a valid selection. If a category+object has no types (e.g. smoking + butts), the type step is simply not shown.

Some categories need no types at all — for `food`, the object IS the specificity (`bag`, `box`, `crisps`, `cutlery`, `wrapper`…).

### Naming convention & i18n

All keys are `snake_case` — no exceptions. Keys are permanent, locale-independent identifiers: never rename a key after production use; add a new key and deprecate the old one.

Display names are resolved client-side from translation files (`lang/{locale}/tags.json`). The `name` column on lookup tables stores the English display name as a fallback. The backend never sees locale — it only receives and stores IDs; translation is purely a display/search concern. Frontend tag search (`UnifiedTagSearch.vue`) filters locally-loaded tag data against translated names for the user's locale.

## Supporting Services

| Service | Responsibility |
|---------|----------------|
| `AddTagsToPhotoAction` | Create PhotoTag + extras, generate summary, handle verification (see pipeline above) |
| `GeneratePhotoSummaryService` | Build the flat-array `summary`, compute `xp` / `total_tags` / `total_brands` |
| `XpCalculator` | Centralised XP math (`calculateFromTags`, `calculateFromSummary` — supports flat-array v5.1 and legacy nested-dict) |
| `ClassifyTagsService` | Resolve raw tag/category keys to models; normalise deprecated v4 keys and category aliases |

### ClassifyTagsService — deprecated key mapping

`ClassifyTagsService::normalizeDeprecatedTag()` maps legacy v4 object keys to canonical objects, auto-adding implied materials:

| Old tag | New object | Materials added |
|---------|------------|-----------------|
| `beerBottle` | `beer_bottle` | `[glass]` |
| `beerCan` | `beer_can` | `[aluminium]` |
| `coffeeCups` | `cup` | `[paper]` |
| `plasticFoodPackaging` | `packaging` | `[plastic]` |
| `waterBottle` | `water_bottle` | `[plastic]` |

`ClassifyTagsService::CATEGORY_ALIASES` maps deprecated v4 category keys to v5 equivalents. `getCategory(string $rawKey)` (public) checks aliases before querying the DB:

| Deprecated key | Resolves to |
|----------------|-------------|
| `coastal` | `marine` |
| `trashdog` | `pets` |
| `dogshit` | `pets` |
| `automobile` | `vehicles` |
| `pathway` | `unclassified` |
| `drugs` | `unclassified` |
| `political` | `unclassified` |
| `stationery` | `unclassified` |

Unknown object keys are created on the fly: `LitterObject::firstOrCreate(['key' => …], ['crowdsourced' => true])`.

> The v4→v5 data migration is complete. For the remaining post-migration code cleanup (dropping `custom_tag_primary_id`, the legacy `brand_id` column, orphaned prefixed `litter_object` rows, etc.), see `readme/PostMigrationCleanup.md`. The mobile v4→v5 tag shim (`ConvertV4TagsAction`) is documented in `readme/Mobile.md`.

## API

### Tag data loading — `GET /api/tags/all`

Returns everything the frontend needs to render the tagging UI as flat arrays:

```json
{
  "categories": [{"id": 1, "key": "alcohol", "name": "Alcohol"}],
  "objects": [{"id": 5, "key": "bottle", "name": "Bottle"}],
  "types": [{"id": 3, "key": "beer", "name": "Beer"}],
  "materials": [{"id": 1, "key": "glass", "name": "Glass"}],
  "brands": [{"id": 7, "key": "heineken", "name": "Heineken"}],
  "category_objects": [{"id": 42, "category_id": 1, "object_id": 5}],
  "category_object_types": [
    {"category_litter_object_id": 42, "litter_object_type_id": 3},
    {"category_litter_object_id": 42, "litter_object_type_id": 8}
  ]
}
```

Objects include their categories via eager load (`LitterObject::with(['categories:id,key'])`). `category_object_types` returns only `category_litter_object_id` and `litter_object_type_id` (no `id` column). The frontend uses `category_objects` to filter objects per selected category, and `category_object_types` to show the type options per selected category+object.

### Add tags — `POST /api/v3/tags`

`PhotoTagsController` → `AddTagsToPhotoAction`. The preferred (CLO-based) payload:

```json
{
  "photo_id": 123,
  "tags": [
    {
      "category_litter_object_id": 42,
      "litter_object_type_id": 3,
      "quantity": 2,
      "picked_up": true,
      "materials": [1, 4],
      "brands": [{"id": 7, "quantity": 1}],
      "custom_tags": ["found-near-school"]
    }
  ]
}
```

Materials are sent as a flat array of IDs (set membership, quantity always 1). Brands include explicit quantities. The backend resolves denormalised `category_id`/`litter_object_id` from the CLO.

The action also accepts the **legacy per-tag formats** the current Vue frontend still uses:

1. **Object tag** — `{ "object": {"id": 5, "key": "butts"}, "quantity": 3, "picked_up": true, "materials": [{"id": 2, "key": "plastic"}], "brands": [{"id": 1, "key": "marlboro"}], "custom_tags": ["dirty-bench"] }`. Category is auto-resolved from the object; it need not be sent.
2. **Custom-only** — `{ "custom": true, "key": "dirty-bench", "quantity": 1, "picked_up": null }`. Creates a `CustomTagNew` from `key` and a loose PhotoTag (null CLO).
3. **Brand-only** — `{ "brand_only": true, "brand": {"id": 1, "key": "coca-cola"}, "quantity": 1, "picked_up": null }`. Loose PhotoTag with the brand as an extra tag.
4. **Material-only** — `{ "material_only": true, "material": {"id": 2, "key": "plastic"}, "quantity": 1, "picked_up": null }`. Same loose-PhotoTag pattern.

### Replace / edit tags — `PUT /api/v3/tags`

The `/tag?photo=<id>` URL loads a specific photo for editing. If it already has tags, `AddTags.vue` enters **edit mode** and uses `PUT /api/v3/tags` to replace all tags. An empty `tags: []` clears all tags from the photo.

`PhotoTagsController::update()` wraps the whole operation in `DB::transaction()` so a partial failure can't lose data:

```php
DB::transaction(function () use ($photo, $validatedData) {
    // 1. Delete old tags + extras
    $photo->photoTags()->each(function ($tag) {
        $tag->extraTags()->delete();
        $tag->delete();
    });

    // 2. Reset summary, XP, verification
    $photo->update(['summary' => null, 'xp' => 0, 'verified' => 0]);

    // 3. Re-add tags (regenerates summary + XP, fires TagsVerifiedByAdmin)
    $this->addTagsToPhotoAction->run(Auth::id(), $photo->id, $validatedData['tags']);
});
```

**Metrics delta handling:** when `TagsVerifiedByAdmin` fires, `ProcessPhotoMetrics → MetricsService::processPhoto()` detects the photo was previously processed (has `processed_at`) and calls `doUpdate()`, which diffs old `processed_tags` against the new summary and applies positive/negative adjustments to all MySQL + Redis metrics.

**Security / ownership:** both `PhotoTagsRequest` (POST) and `ReplacePhotoTagsRequest` (PUT) enforce `$photo->user_id === $this->user()->id`, returning 403 for non-owners. `GET_SINGLE_PHOTO` loads via `/api/v3/user/photos?id=X&id_operator==&per_page=1`, which is filtered by `Auth::user()->id` server-side, so a user cannot load another user's photo.

Tests: `tests/Feature/Tags/ReplacePhotoTagsTest.php` (replace tags, already-tagged photos, ownership, auth, extra-tag cleanup).

### Uploads serializer — `getNewTags()`

`UsersUploadsController::index()` returns each photo's tags under the key `new_tags` (frontend reads `photo.new_tags`). `UsersUploadsController::getNewTags()` builds the array:

- `category` and `object` keys are included only when **both** relations resolve (`category_id != null && litter_object_id != null`). Extra-tag-only PhotoTags omit them.
- `extra_tags` is included only when the PhotoTag has at least one extra tag (empty arrays are not serialised).
- `litter_object_type_id` is **always** included — required for edit round-trips to restore the type dimension.
- `quantity` is always ≥ 1.

## MetricsService Impact

`MetricsService` reads the flat-array summary and aggregates counts. `MetricsService` is the single writer for all metrics (the MySQL `metrics` table + Redis) — never increment Redis or `metrics` directly.

```php
foreach ($summary['tags'] as $tag) {
    $metrics['categories'][$tag['category_id']] += $tag['quantity'];
    $metrics['objects'][$tag['object_id']]     += $tag['quantity'];

    if ($tag['type_id']) {
        $metrics['types'][$tag['type_id']] += $tag['quantity'];
    }

    // Materials: set membership, WEIGHTED by parent tag quantity
    foreach ($tag['materials'] as $materialId) {
        $metrics['materials'][$materialId] += $tag['quantity'];
    }
    // Brands: independent quantities
    foreach ($tag['brands'] as $brandId => $count) {
        $metrics['brands'][$brandId] += $count;
    }
    // Custom tags: weighted by parent tag quantity (same as materials)
    foreach ($tag['custom_tags'] as $customTagId) {
        $metrics['custom_tags'][$customTagId] += $tag['quantity'];
    }
}
```

Delta calculation fingerprints old vs new and diffs the flattened counts. Redis adds a `types` dimension following the existing pattern (`{prefix}:types`, `{prefix}:rank:types`, per-user `tags → types` sub-hash). Achievements add a `TypesChecker` following the `MaterialsChecker` pattern. See `readme/Metrics.md` and `readme/Achievements.md`.

## Web Frontend Tagging

The Vue tagging page (`/tag` route → `AddTags.vue`) builds a search index, lets the user select tags, and submits via `POST /api/v3/tags` (or `PUT` in edit mode).

### Tagging UX flow

```
1. Pick CATEGORY     →  Alcohol              (required)
2. Pick OBJECT       →  Bottle               (required, filtered by category)
3. Pick TYPE         →  Beer                 (optional, filtered by category+object)
4. Pick MATERIAL     →  Glass                (optional)
5. Pick BRAND        →  Heineken             (optional)
6. QUANTITY          →  1                    (required, default 1)
7. PICKED UP?        →  Yes                  (optional)
```

Steps 3–5 are optional chips/dropdowns on the tag card. If no types exist for a category+object pair (e.g. smoking + butts), step 3 is not shown.

### Search index (category disambiguation)

`AddTags.vue` builds a `searchableTags` computed that generates **one entry per (object, category) pair** instead of one per object. This prevents corruption when the same object exists in multiple categories (e.g. "bottle" in alcohol, beverages, and food). Each entry has:

- `id`: composite `obj-{objectId}-cat-{categoryId}` for deduplication.
- `cloId`: pre-resolved `category_litter_object_id` from the store's `getCloId(categoryId, objectId)`.
- `categoryId`, `categoryKey`: the specific category for this entry.
- `lowerKey`: precomputed `key.toLowerCase()` for fast filtering.

**Type entries** are generated from `categoryObjectTypes` with composite id `type-{cloId}-{typeId}`; results show the parent object and category as context.

### Display formatting

`formatKey(key)` converts `snake_case` to `Title Case`: `key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())`. Tag cards show `"Bottle · Alcohol"` (object + category). Type pills replace the old `<select>` — clicking an active pill deselects it.

### Validation (frontend)

`hasUnresolvedTags` blocks submit when any object tag lacks a pre-resolved `cloId`. Unresolved tags show a red border, the `TaggingHeader` shows a warning, and the submit button is disabled.

### Edit mode

- `GET_SINGLE_PHOTO(id)` loads the photo (ownership enforced server-side).
- `convertExistingTags(photo)` transforms the `new_tags` API format back into the frontend tag format (handles object, brand-only, material-only, custom-only) and reads `litter_object_type_id` to restore `typeId` — so the type dimension survives edit round-trips.
- Submit calls `REPLACE_TAGS({ photoId, tags })` → `PUT /api/v3/tags`.

### Frontend guards

- **Double-submit prevention:** `isSubmitting` ref blocks `submitTags()` re-entry on rapid clicks / Ctrl+Enter.
- **XP bar refresh:** after a successful POST/PUT, `REFRESH_USER()` updates the nav XP bar with server-side totals (non-blocking).
- **Parallel store refresh:** `UPLOAD_TAGS` refreshes stats and photos via `Promise.all()` to avoid stale intermediate state.
- **Nullish coalescing:** `TaggingHeader.vue` uses `??` (not `||`) for `untaggedCount` so `0` doesn't fall through to a stale `total`.
- **imageLoading guard:** `handleNavigation` only sets `imageLoading = true` when `currentPhoto` exists, preventing a stuck skeleton on empty pages.

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

All shortcuts except Escape and Ctrl+Enter early-return when the focused element is INPUT, SELECT, or TEXTAREA. `hasUnresolvedTags` blocks confirm shortcuts.

### Search UX & level titles

`UnifiedTagSearch.vue` uses a 100ms debounce. Results are grouped: `['object', 'type', 'material', 'brand', 'customTag']`, with category breadcrumbs for object results and parent object names for type results. `TaggingHeader.vue` displays user level titles matching `config/levels.php`.

### Dark Glass UI

The tagging frontend uses the dark-glass design system:
- **Background:** `bg-gradient-to-br from-slate-900 via-blue-900 to-emerald-900`
- **Glass panels:** `bg-white/5 border border-white/10 rounded-xl`
- **Accent:** Emerald (`text-emerald-400`, `bg-emerald-500`, `focus:border-emerald-500/50`)
- **Text hierarchy:** `text-white`, `text-white/60`, `text-white/40`, `text-white/30`
- **Layout:** two-panel split — 55% photo (left) / 45% tags (right); on mobile, photo is `h-[40vh]` with tags scrolling below.
- **Progress bar:** thin emerald bar showing `taggedCount / totalPhotos`.
- **Auto-advance:** after a successful submission, a green flash overlay (400ms) appears, tags clear, and the next photo loads. When all photos are tagged, an empty-state celebratory checkmark links to Upload / My Photos.

### Frontend files

| File | Purpose |
|------|---------|
| `resources/js/views/General/Tagging/v2/AddTags.vue` | Main tagging page — search index, selection, submit, edit mode |
| `resources/js/views/General/Tagging/v2/components/UnifiedTagSearch.vue` | Debounced search combobox with grouped results |
| `resources/js/views/General/Tagging/v2/components/TagCard.vue` | Tag card with type pills, category display, `formatKey` |
| `resources/js/views/General/Tagging/v2/components/ActiveTagsList.vue` | Container for active tags |
| `resources/js/views/General/Tagging/v2/components/TaggingHeader.vue` | XP bar, level title, pagination, unresolved warning, edit badge |
| `resources/js/views/General/Tagging/v2/components/PhotoViewer.vue` | Photo display with zoom |
| `resources/js/stores/photos/requests.js` | `UPLOAD_TAGS()` (POST), `REPLACE_TAGS()` (PUT), `GET_SINGLE_PHOTO()` |
| `resources/js/stores/tags/requests.js` | `GET_ALL_TAGS()` → `GET /api/tags/all` |

## Related Docs

- `readme/XP.md` — XP scoring, quantity rules, special objects, levels
- `readme/Metrics.md` — metrics pipeline and aggregation
- `readme/Achievements.md` — achievements (incl. types/materials checkers)
- `readme/Upload.md` — upload/tagging architecture, metrics pipeline, Redis key alignment
- `readme/Mobile.md` — mobile v4→v5 tag shim (`ConvertV4TagsAction`)
- `readme/PostMigrationCleanup.md` — remaining post-migration code cleanup (legacy columns, orphaned rows)
- `readme/API.md` — full API endpoint reference (source of truth for request/response contracts)
