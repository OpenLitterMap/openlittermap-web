# OpenLitterMap v5.1 — Tagging Architecture Spec

## Status: DRAFT — Pending review before implementation

---

## Problem Statement

The current tagging system encodes product context into object keys (`beer_bottle`, `water_bottle`, `soda_can`), causing slug explosion (~170 objects), duplication across categories, inconsistent naming, and a `categories()->first()` bug when objects belong to multiple categories.

## Design Principles

1. **An object key represents the physical thing** — `bottle`, `can`, `cup`
2. **Product context is a separate dimension** — `beer`, `water`, `soda` via LitterObjectType
3. **Category + Object pairing is always explicit** — no auto-inference
4. **Optional dimensions don't block data collection** — students can skip what they don't know
5. **Extra-tag-only tags allow null CLO** — brand-only, material-only, and custom-only PhotoTags have nullable `category_id`, `litter_object_id`, and `category_litter_object_id`. They are not forced into `unclassified/other`
6. **Types are strictly "what was in it"** — beer, water, juice. Not container forms (pint, shot) or physical states (crushed, degraded)
7. **Materials are set membership, not counted** — a bottle is glass, full stop. Only brands carry independent quantities

---

## Existing Infrastructure (What Already Works)

The following models and tables already exist and are well-structured for this architecture:

| Model | Table | Role | Change Needed |
|-------|-------|------|---------------|
| `Category` | `categories` | Category lookup | None — add new category rows only |
| `LitterObject` | `litter_objects` | Object lookup | None — add new canonical object rows, consolidate prefixed ones later |
| `CategoryObject` (Pivot) | `category_litter_object` | CLO identity — already has auto-increment ID, unique(category, object) | None — this IS the CLO the spec needs. Add `litter_object_types` relationship |
| `Materials` | `materials` | Material lookup | None |
| `BrandList` | `brandslist` | Brand lookup | None |
| `CustomTagNew` | `custom_tags_new` | User-generated tags | None |
| `LitterState` | `litter_states` | Object states (degraded, micro, macro) | Keep — see States section below |
| `Taggable` | `taggables` | Polymorphic pivot for CLO↔materials/brands/states/custom | None |
| `PhotoTag` | `photo_tags` | Per-photo tag row | Add `category_litter_object_id`, `litter_object_type_id`. Phase 4: drop `custom_tag_primary_id`, `brand_id` |
| `PhotoTagExtraTags` | `photo_tag_extra_tags` | Material/brand/custom extras per tag | Phase 4: change upsert key (drop `index` from unique constraint) |

### States (existing dimension — keep)

`LitterState` models physical condition: `degraded`, `micro`, `macro`, etc. These are already wired into `CategoryObject` via the `taggables` polymorphic table (`states()` relationship). The current `TagsConfig.php` references states for coastal/rope items.

**Decision:** Keep states as-is. They flow through the existing `taggables` infrastructure and don't conflict with the new architecture. States describe physical condition of an object — orthogonal to category, object, type, and material.

### PhotoTag.brand_id (legacy column)

`PhotoTag` has a direct `brand_id` FK and `brand()` BelongsTo relationship. This predates the `photo_tag_extra_tags` system. Some older photo_tags may use this column instead of extras.

**Decision:** Keep during Phases 1-3. Drop in Phase 4 after verifying all brand data has been migrated to `photo_tag_extra_tags`. The `brand()` relationship on PhotoTag can be marked `@deprecated`.

### PhotoTagExtraTags upsert key

`PhotoTag::attachExtraTags()` uses `['photo_tag_id', 'tag_type', 'tag_type_id', 'index']` as the upsert unique key. When `index` is dropped, this changes to `['photo_tag_id', 'tag_type', 'tag_type_id']`.

**Decision:** Update in Phase 3 when `AddTagsToPhotoAction` is rewritten. Until then, existing code continues to work.

When a person tags litter, they're recording:

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
| Category | 0 or 1 | photo_tags (via category_litter_object_id). Null for extra-tag-only tags |
| LitterObject | 0 or 1 | photo_tags (via category_litter_object_id). Null for extra-tag-only tags |
| LitterObjectType | 0 or 1 | photo_tags.litter_object_type_id |
| Material | 0 to many (set membership, qty=1 in DB, weighted by tag qty in metrics) | photo_tag_extra_tags |
| Brand | 0 to many (with independent quantities) | photo_tag_extra_tags |
| Custom tag | 0 to many (set membership, qty=1 in DB, weighted by tag qty in metrics) | photo_tag_extra_tags |
| Quantity | exactly 1 | photo_tags.quantity |
| Picked up | 0 or 1 | photo_tags.picked_up |

**Rule:** 1:1 dimensions go on the tag row. 1:many dimensions go in the extras table.

---

## Database Schema

### Lookup Tables (seeded, small)

```sql
categories (
    id          BIGINT UNSIGNED AUTO_INCREMENT PK,
    key         VARCHAR(50) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP
)
-- Seeded from TagsConfig (16 public + 1 hidden unclassified)

litter_objects (
    id              BIGINT UNSIGNED AUTO_INCREMENT PK,
    key             VARCHAR(50) NOT NULL UNIQUE,
    name            VARCHAR(100) NOT NULL,
    crowdsourced    BOOLEAN DEFAULT false,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
)
-- ~100 canonical physical objects: bottle, can, cup, butts, wrapper...

litter_object_types (
    id          BIGINT UNSIGNED AUTO_INCREMENT PK,
    key         VARCHAR(50) NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP
)
-- ~17 rows: beer, wine, spirits, cider, water, soda, juice,
-- energy, sports, coffee, tea, milk, smoothie, iced_tea,
-- sparkling_water, plant_milk, unknown
-- SCOPE: strictly "what was in the container" — product/contents only.
-- Not container forms (pint, shot), sizes, or physical states.
-- NOTE: No "other" type. If contents are unidentifiable, use "unknown".
-- If contents don't apply to this object, don't select a type (it's optional).

materials (id, key, name)
-- existing, ~30 rows

brandslist (id, key, name, crowdsourced)
-- existing

custom_tags_new (id, key, user_id)
-- existing
```

### Relationship Pivots

```sql
category_litter_object (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PK,
    category_id         FK → categories NOT NULL,
    litter_object_id    FK → litter_objects NOT NULL,
    created_at          TIMESTAMP,
    updated_at          TIMESTAMP,
    UNIQUE(category_id, litter_object_id)
)
-- ALREADY EXISTS (188 rows). Model: CategoryObject (Pivot).
-- No schema change needed. Add new rows for new canonical objects.
-- Add litter_object_types() relationship to CategoryObject model.

category_object_types (
    category_litter_object_id   FK → category_litter_object NOT NULL,
    litter_object_type_id       FK → litter_object_types NOT NULL,
    UNIQUE(category_litter_object_id, litter_object_type_id)
)
-- NEW TABLE. Controls which type options appear per category+object combo.
-- Simple dedicated pivot (not polymorphic via taggables — types are structural,
-- not user-generated like brands/materials).
```

### Tag Tables (per-photo data)

```sql
photo_tags (
    id                          BIGINT UNSIGNED AUTO_INCREMENT PK,
    photo_id                    FK → photos NOT NULL,
    category_litter_object_id   FK → category_litter_object NULL,       -- NEW. Null for extra-tag-only (brand/material/custom) tags
    category_id                 FK → categories NULL,                    -- EXISTS — null for extra-tag-only tags
    litter_object_id            FK → litter_objects NULL,                -- EXISTS — null for extra-tag-only tags
    litter_object_type_id       FK → litter_object_types NULL,          -- NEW
    custom_tag_primary_id       FK → custom_tags_new NULL,              -- EXISTS — drop in Phase 4
    brand_id                    FK → brandslist NULL,                    -- EXISTS — drop in Phase 4
    quantity                    INT UNSIGNED NOT NULL DEFAULT 1,         -- EXISTS
    picked_up                   BOOLEAN NULL,                           -- EXISTS

    INDEX idx_photo_tags_photo (photo_id),              -- EXISTS
    INDEX idx_photo_tags_cat_obj (category_id, litter_object_id),  -- ADD
    INDEX idx_photo_tags_clo (category_litter_object_id)           -- ADD
)
)

-- DENORMALISATION NOTE: category_id and litter_object_id are derived from
-- category_litter_object_id and must always match. Enforced in application
-- layer (AddTagsToPhotoAction resolves them from CLO on write). These exist
-- because MetricsService and Redis keys reference category_id/object_id
-- directly — avoiding joins in the hottest code path.

photo_tag_extra_tags (
    id              BIGINT UNSIGNED AUTO_INCREMENT PK,
    photo_tag_id    FK → photo_tags NOT NULL,
    tag_type        ENUM('material', 'brand', 'custom_tag') NOT NULL,
    tag_type_id     INT UNSIGNED NOT NULL,
    index           INT UNSIGNED,                           -- EXISTS — drop in Phase 4
    quantity        INT UNSIGNED DEFAULT 1,

    -- CURRENT upsert key: (photo_tag_id, tag_type, tag_type_id, index)
    -- AFTER Phase 4:      (photo_tag_id, tag_type, tag_type_id)
    INDEX idx_extra_tags_photo_tag (photo_tag_id, tag_type)
)

-- QUANTITY RULES:
-- Materials: always quantity = 1 in DB (set membership — "this item is glass").
--   BUT metrics weight by parent tag quantity: 20 bottles + glass = 20 glass items.
-- Brands: independent count (e.g. 3 Heineken + 2 Coca-Cola out of 5 bottles)
-- Custom tags: always quantity = 1 in DB. Metrics weight by parent tag quantity.
```

### Changes from Current Schema

| Change | Phase | Reason |
|--------|-------|--------|
| `category_litter_object_id` added to photo_tags | 1 | Source of truth — impossible to have invalid category+object combo |
| `litter_object_type_id` added to photo_tags | 1 | New dimension: what was in the container |
| `litter_object_types` table created | 1 | New lookup table for types |
| `category_object_types` pivot created | 1 | Controls which types are valid per CLO |
| `CategoryObject` gets `types()` relationship | 1 | Wire new pivot into existing model |
| `PhotoTag` gets `categoryObject()` + `type()` relationships | 1 | Wire new columns into model |
| `category_id` becomes denormalised from CLO | 3 | Was the source of truth, becomes derived. Enforced in app layer |
| `litter_object_id` becomes denormalised from CLO | 3 | Was the source of truth, becomes derived. Enforced in app layer |
| `custom_tag_primary_id` dropped from photo_tags | 4 | Custom-only tags use extras on `other` object instead |
| `brand_id` dropped from photo_tags | 4 | Legacy — all brands go through photo_tag_extra_tags |
| `index` column dropped from photo_tag_extra_tags | 4 | Not needed — changes upsert key |
| Materials quantity always 1 | 3 | Materials are set membership, not independent counts |
| Brand quantities independent | — | Already works this way in extras |

---

## Summary JSON (Redesigned)

Current nested-dict format (`tags.{cat_id}.{obj_id}`) breaks when the same category+object appears with different types. Replaced with flat array where each entry maps 1:1 to a `photo_tags` row.

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
            "materials": [1],
            "brands": {"7": 1},
            "custom_tags": []
        },
        {
            "clo_id": 42,
            "category_id": 1,
            "object_id": 5,
            "type_id": 8,
            "quantity": 1,
            "picked_up": false,
            "materials": [1],
            "brands": {},
            "custom_tags": []
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
        "types": {"3": "beer", "8": "wine"},
        "materials": {"1": "glass"},
        "brands": {"7": "heineken"}
    }
}
```

Two alcohol bottles — same category, same object, different types. No collision.

Materials stored as arrays of IDs (set membership — quantity always 1 in DB). Brands stored as `{id: quantity}` maps.

**Totals semantics:** `materials` and `custom_tags` are weighted by parent tag quantity (2 glass beer bottles + 1 glass wine bottle = 3 glass items). `brands` are summed from independent brand quantities. `litter` is sum of all tag quantities.

---

## API Contract

### Frontend Sends (POST /api/v3/tags)

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

One ID (`category_litter_object_id`) for the category+object pair. Backend resolves denormalised fields.

**Note:** Materials are sent as a flat array of IDs (set membership, quantity always 1). Brands include explicit quantities.

### Backend Validation (AddTagsToPhotoAction)

```
1. category_litter_object_id must exist in category_litter_object pivot (when present)
2. Resolve category_id + litter_object_id from CLO (for denormalised columns)
3. If litter_object_type_id present, must exist in category_object_types for this CLO
4. quantity must be positive integer
5. Each material ID must exist in materials table (quantity forced to 1 on write)
6. Each brand ID must exist in brandslist table
7. Sum of brand quantities per tag ≤ tag quantity
8. If object is "other", require at least one extra tag
9. Custom tag quantity forced to 1 on write
10. Extra-tag-only tags (brand_only, material_only, custom): null CLO fields,
    created via createExtraTagOnly(). No object XP awarded, only extra-tag XP
```

### Tag Data Loading (GET /api/tags/all)

Returns everything the frontend needs to render the tagging UI:

```json
{
    "categories": [{"id": 1, "key": "alcohol", "name": "Alcohol"}],
    "objects": [{"id": 5, "key": "bottle", "name": "Bottle"}],
    "types": [{"id": 3, "key": "beer", "name": "Beer"}],
    "materials": [{"id": 1, "key": "glass", "name": "Glass"}],
    "brands": [{"id": 7, "key": "heineken", "name": "Heineken"}],
    "category_objects": [
        {"id": 42, "category_id": 1, "object_id": 5}
    ],
    "category_object_types": [
        {"category_litter_object_id": 42, "type_id": 3},
        {"category_litter_object_id": 42, "type_id": 8}
    ]
}
```

Frontend uses `category_objects` to filter objects per selected category. Uses `category_object_types` to show type dropdown per selected category+object.

---

## Tagging UX Flow

```
1. Pick CATEGORY     →  Alcohol              (required)
2. Pick OBJECT       →  Bottle               (required, filtered by category)
3. Pick TYPE         →  Beer                 (optional, filtered by category+object)
4. Pick MATERIAL     →  Glass                (optional)
5. Pick BRAND        →  Heineken             (optional)
6. QUANTITY          →  1                    (required, default 1)
7. PICKED UP?        →  Yes                  (optional)
```

Steps 3-5 are optional chips/dropdowns on the tag card. If the student can't identify the contents, skip step 3. The data is still useful.

If no types exist for a category+object pair (e.g. smoking + butts), step 3 is not shown at all.

---

## MetricsService Impact

### extractMetricsFromPhoto()

Current implementation walks nested dict. New implementation iterates flat array:

```php
foreach ($summary['tags'] as $tag) {
    $metrics['categories'][$tag['category_id']] += $tag['quantity'];
    $metrics['objects'][$tag['object_id']] += $tag['quantity'];

    if ($tag['type_id']) {
        $metrics['types'][$tag['type_id']] += $tag['quantity'];
    }

    // Materials are set membership but WEIGHTED by tag quantity
    // 20 bottles + material:glass = 20 glass items, not 1
    foreach ($tag['materials'] as $materialId) {
        $metrics['materials'][$materialId] += $tag['quantity'];
    }
    // Brands have independent quantities (3 Heineken + 2 Coca-Cola)
    foreach ($tag['brands'] as $brandId => $count) {
        $metrics['brands'][$brandId] += $count;
    }
    // Custom tags weighted by tag quantity (same logic as materials)
    foreach ($tag['custom_tags'] as $customTagId) {
        $metrics['custom_tags'][$customTagId] += $tag['quantity'];
    }
}
```

Delta calculation unchanged — fingerprint old vs new, diff the flattened counts.

### Redis: New Types Dimension

RedisMetricsCollector adds one new dimension following existing pattern:

```
{prefix}:types              HINCRBY type_id count
{prefix}:rank:types         ZINCRBY type_id count
user:{id}:tags → types      sub-hash for per-user type breakdown
```

### Achievements: New TypesChecker

Follows `MaterialsChecker` pattern — checks `types` counts from Redis against achievement thresholds.

---

## XP System

No structural change. LitterObjectType tagging earns 0 XP initially — it's optional enrichment data, not something to incentivise guessing on. Can revisit post-LitterWeek.

---

## Categories (17: 16 public + 1 system)

| Key | Name | Notes |
|-----|------|-------|
| smoking | Smoking & Tobacco | Includes vaping |
| alcohol | Alcohol | Beer, wine, spirits — distinct for public health research |
| beverages | Beverages | Non-alcoholic — merges old coffee + softdrinks |
| food | Food | Wrappers, packaging, containers, cutlery |
| personal_care | Personal Care | Hygiene, dental, menstrual — renamed from "sanitary" |
| medical | Medical & PPE | Syringes, pill packs, gloves, masks — split from sanitary |
| industrial | Industrial | Construction, dumping, chemicals — absorbs old "dumping" category |
| vehicles | Vehicles | Car parts, tyres, batteries — renamed from "automobile" |
| marine | Marine & Fishing | Nets, rope, buoys — replaces "coastal" (environment-based) |
| electronics | Electronics | Batteries, cables, chargers |
| pets | Pets | Dog waste |
| unclassified | Unclassified | Hidden from UI. Migration catch-all for legacy null-null tags |

---

## Canonical Objects per Category

### smoking (12 objects)
`butts`, `lighters`, `cigarette_box`, `tobacco_pouch`, `rolling_papers`, `packaging`, `filters`, `vape_pen`, `vape_cartridge`, `match_box`, `ashtray`, `other`

### alcohol (12 objects)
`bottle`, `can`, `pint_glass`, `wine_glass`, `shot_glass`, `broken_glass`, `bottle_cap`, `pull_ring`, `six_pack_rings`, `cup`, `packaging`, `other`

**Types for alcohol + bottle:** beer, wine, spirits, cider, unknown
**Types for alcohol + can:** beer, cider, spirits, unknown
**Types for alcohol + pint_glass:** beer, cider, unknown
**Types for alcohol + wine_glass:** wine, unknown
**Types for alcohol + shot_glass:** spirits, unknown

### beverages (13 objects)
`bottle`, `can`, `carton`, `cup`, `lid`, `straw`, `straw_wrapper`, `juice_pouch`, `coffee_pod`, `label`, `broken_glass`, `packaging`, `other`

**Types for beverages + bottle:** water, soda, juice, energy, sports, tea, milk, smoothie, unknown
**Types for beverages + can:** soda, energy, juice, iced_tea, sparkling_water, unknown
**Types for beverages + carton:** juice, milk, iced_tea, plant_milk, unknown
**Types for beverages + cup:** coffee, tea, soda, smoothie, unknown

### food (16 objects)
`bag`, `box`, `can`, `crisps`, `cutlery`, `gum`, `jar`, `lid`, `packet`, `packaging`, `plate`, `pizza_box`, `napkins`, `tinfoil`, `wrapper`, `other`

No types needed — the object IS the specificity.

### personal_care (13 objects)
`wipes`, `nappies`, `ear_swabs`, `toothbrush`, `toothpaste_tube`, `dental_floss`, `deodorant_can`, `sanitary_pad`, `tampon`, `menstrual`, `condom`, `condom_wrapper`, `other`

### medical (9 objects)
`syringe`, `pill_pack`, `medicine_bottle`, `bandage`, `plaster`, `gloves`, `face_mask`, `sanitiser`, `other`

### industrial (13 objects)
`oil`, `oil_drum`, `chemical`, `construction`, `bricks`, `tape`, `pallet`, `wire`, `pipe`, `container`, `dumping_small`, `dumping_medium`, `dumping_large`, `other`

### vehicles (9 objects)
`car_part`, `battery`, `bumper`, `tyre`, `wheel`, `light`, `mirror`, `license_plate`, `other`

### marine (9 objects)
`fishing_net`, `rope`, `buoy`, `crate`, `microplastics`, `macroplastics`, `styrofoam`, `shotgun_cartridge`, `other`

### electronics (6 objects)
`battery`, `cable`, `phone`, `charger`, `headphones`, `other`

### pets (3 objects)
`dog_waste`, `dog_waste_in_bag`, `other`

**Total: 17 categories (16 public + 1 hidden), ~114 canonical objects, ~17 types**

---

## Shared Objects (multi-category via pivot)

| Object | Categories |
|--------|------------|
| bottle | alcohol, beverages, food |
| can | alcohol, beverages, food |
| cup | alcohol, beverages |
| broken_glass | alcohol, beverages |
| packaging | alcohol, beverages, food, smoking |
| lid | beverages, food |
| battery | vehicles, electronics |
| other | all 12 categories (including unclassified) |

Each combination is a row in `category_litter_object` with its own ID. `bottle` in alcohol (CLO id=42) is a different selection than `bottle` in beverages (CLO id=43).

**Note:** `pint_glass`, `wine_glass`, `shot_glass` are NOT shared — they exist only under alcohol. They are distinct physical objects, not types.

---

## LitterObjectTypes (Seed Data)

Types represent **what was in the container** — the product/contents. Not container forms, sizes, or physical states.

| Key | Name | Used by |
|-----|------|---------|
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
| unknown | Unknown | All typed CLOs must include this. Selected when contents are unidentifiable |

---

## Naming Convention

All keys `snake_case`. No exceptions. Old camelCase keys (`bottleTop`, `sixPackRings`, `earSwabs`) mapped to snake_case during migration.

### Translation & i18n

Keys are permanent, locale-independent identifiers. They must never be renamed after production use — add a new key and deprecate the old one instead.

Display names are resolved client-side via translation files:

```
lang/en/tags.json  →  {"bottle": "Bottle", "butts": "Cigarette Butts"}
lang/fr/tags.json  →  {"bottle": "Bouteille", "butts": "Mégots"}
lang/es/tags.json  →  {"bottle": "Botella", "butts": "Colillas"}
```

The `name` column on lookup tables (`categories`, `litter_objects`, `litter_object_types`, `materials`) stores the English display name as a fallback. Frontend tag search (in `UnifiedTagSearch.vue`) filters the locally-loaded tag data against translated names for the user's locale.

The backend never sees locale — it only receives and stores IDs. Translation is purely a display/search concern.

---

## Migration Path

### Phase 1: Schema Changes

```
1. Create litter_object_types table + seed ~18 rows
2. Create category_object_types pivot + seed
3. Add category_litter_object_id to photo_tags (NULLABLE)
4. Add litter_object_type_id to photo_tags (NULLABLE)
5. Backfill category_litter_object_id from existing category_id + litter_object_id:
   → For every photo_tag with non-null category/object, look up the CLO id from the pivot
   → Extra-tag-only tags (brand-only, material-only, custom-only) retain null CLO fields.
     These are "loose tags" — they exist independently without a category/object.
     No migration to unclassified/other needed.
```

### Phase 2: Object Consolidation

```
1. Create canonical objects (bottle, can, pint_glass, wine_glass, shot_glass, etc.) if they don't exist
2. Map prefixed objects to canonical + type:
     beer_bottle  → object: bottle, type: beer, category: alcohol
     water_bottle → object: bottle, type: water, category: beverages
     soda_can     → object: can, type: soda, category: beverages
     wine_glass   → object: wine_glass, category: alcohol (type: wine optional)
     ... (full mapping in migration script)
3. Update photo_tags rows: set new litter_object_id + litter_object_type_id
4. TRAP: Remap taggables pivots (brands, materials, AND states).
   Any taggables rows keyed by old CLO (e.g. CLO for beer_bottle)
   must be remapped to new CLO (alcohol + bottle).
   States especially: if old prefixed objects had states attached
   (e.g. degraded coastal bottle), don't silently drop them.
   The category_object_types seed must cover all inferred types
   BEFORE validation is enabled, otherwise migrated rows get rejected.
5. Regenerate summary JSON (flat array format)
```

### Phase 3: Make Authoritative

```
1. category_litter_object_id remains NULLABLE (required for extra-tag-only loose tags)
2. Update frontend to send category_litter_object_id (for object tags)
3. Update AddTagsToPhotoAction validation
4. Update GeneratePhotoSummaryService for flat array format
5. Update MetricsService extractMetricsFromPhoto() for flat array
6. Reprocess metrics (FLUSHDB + rebuild)
```

### Phase 4: Cleanup

```
1. Drop orphaned litter_object rows (beer_bottle, water_bottle, etc.)
2. Drop custom_tag_primary_id from photo_tags
3. Drop index column from photo_tag_extra_tags
4. Update TagsConfig with canonical objects
5. Update GenerateTagsSeeder
```

---

## Files Affected

### Phase 1 — Schema + Seed (no behavior changes)

| File | Change | Effort |
|------|--------|--------|
| New: `litter_object_types` migration | Create table + seed ~18 rows | Small |
| New: `category_object_types` migration | Create pivot table | Small |
| New: `photo_tags` migration | Add `category_litter_object_id` (nullable), `litter_object_type_id` (nullable), indexes | Small |
| New: `LitterObjectType` model | `App\Models\Litter\Tags\LitterObjectType` — simple Eloquent model | Small |
| `CategoryObject` model | Add `types()` BelongsToMany relationship | Small |
| `PhotoTag` model | Add `categoryObject()` BelongsTo, `type()` BelongsTo relationships | Small |
| Tags API endpoint | Add `types` and `category_object_types` to `/api/tags/all` response | Small |

### Phase 2 — Object Consolidation (data migration)

| File | Change | Effort |
|------|--------|--------|
| New: migration script | Map prefixed objects → canonical + type, remap taggables | Medium |
| `TagsConfig.php` | Rewrite with canonical objects | Medium |
| `GenerateTagsSeeder` | Follow new TagsConfig | Medium |

### Phase 3 — Make Authoritative (behavior changes)

| File | Change | Effort |
|------|--------|--------|
| `photo_tags` migration | `category_litter_object_id` stays NULLABLE (extra-tag-only tags) | Small |
| `AddTagsToPhotoAction` | Accept CLO id, validate pair, resolve denorm fields, handle type | Medium |
| `GeneratePhotoSummaryService` | Flat array format | Medium |
| `MetricsService` | Walk flat array, weight materials/custom_tags by parent quantity | Medium |
| `RedisMetricsCollector` | Add types dimension | Small |
| `PhotoTagsController` | Accept CLO id from frontend | Small |
| `PhotoTag::attachExtraTags()` | Remove `$index` parameter, change upsert key, force material/custom qty=1 | Small |
| New: `olm:verify-tag-integrity` | Artisan command to detect/repair CLO↔denorm drift. Run after migrations | Small |
| Frontend: `TagCard.vue` | Add type selector | Small |
| Frontend: `UnifiedTagSearch.vue` | Send CLO id | Small |
| Frontend: `stores/tags/requests.js` | Load types + pivots | Small |

### Phase 4 — Cleanup

| File | Change | Effort |
|------|--------|--------|
| `photo_tags` migration | Drop `custom_tag_primary_id`, `brand_id` | Small |
| `photo_tag_extra_tags` migration | Drop `index` column, update unique constraint | Small |
| `PhotoTag` model | Remove `primaryCustomTag()`, `brand()` relationships | Small |
| `ConvertV4TagsAction` (mobile shim) | Map v4 tags → CLO + type | Medium |
| `AchievementEngine` | Add `TypesChecker` | Small |
| Data cleanup | Drop orphaned litter_object rows | Small |

---

## Validation Rules

1. `category_litter_object_id` must exist in `category_litter_object` pivot (when present — null for extra-tag-only tags)
2. If `litter_object_type_id` present, must exist in `category_object_types` for this CLO
3. `quantity` must be positive integer
4. Each material ID must exist in `materials` table
5. Each brand ID must exist in `brandslist` table
6. **Material extras:** quantity forced to 1 on write in `attachExtraTags()` regardless of input. Metrics weight by parent tag quantity (20 bottles + glass = 20 glass items)
7. **Brand extras:** quantity is an independent count. Sum of brand quantities per photo_tag ≤ photo_tag quantity
8. **Custom tag extras:** quantity forced to 1 on write. Metrics weight by parent tag quantity
9. `other` object: require at least one extra tag (brand, material, or custom_tag) — prevents empty tags
10. **Extra-tag-only tags (loose tags):** brand-only, material-only, and custom-only tags have null `category_id`, `litter_object_id`, and `category_litter_object_id`. Created via `AddTagsToPhotoAction::createExtraTagOnly()`. These do not count toward `totalLitter` and receive no object XP — only their extra-tag XP (brand=3, material=2, custom=1)
11. **Denormalisation integrity:** `category_id` and `litter_object_id` on photo_tags must match the referenced CLO (when present). Enforced on write in `AddTagsToPhotoAction`. An `olm:verify-tag-integrity` artisan command must exist to detect and repair drift — run after migrations, seeders, and data scripts. CI should include this check.
12. **Typed CLO seeding rule:** any CLO that has entries in `category_object_types` must include `unknown`. This ensures users always have a valid selection when contents are unidentifiable

---

## Mobile Compatibility

The existing `ConvertV4TagsAction` shim handles v4 format from mobile apps. After this change:

- Mobile continues sending v4 format → shim maps to v5.1 canonical objects + types
- Shim updated to resolve `beer_bottle` → CLO(alcohol, bottle) + type(beer), `wine_glass` → CLO(alcohol, wine_glass)
- No mobile app update required for LitterWeek
