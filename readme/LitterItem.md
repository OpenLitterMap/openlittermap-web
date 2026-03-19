# LitterItem — Composite Item Templates

**Status:** DRAFT — Spec for review. Do not implement yet.

---

## 1. Problem

OLM's current tagging model is flat: one `PhotoTag` = one object + optional extras. But real litter is often composite:

- A juice carton **with a straw**
- A coffee cup **with a lid**
- A beer bottle **with a cap**
- A cigarette packet **with 20 butts inside**

Today, users must tag each component separately (carton, straw, lid, cap). There's no way to express "this is one item that consists of multiple parts." This means:

1. **Slow tagging** — users create 2-3 separate tags for what they see as one item
2. **No structural knowledge** — the system doesn't know a carton typically has a straw
3. **AI can't learn composites** — no training signal for multi-part item recognition
4. **Search misses intent** — searching "juice carton" doesn't surface the straw component

## 2. What LitterItem Is

A `LitterItem` is a **read-only template** that defines a recognizable litter thing and its component parts. It maps onto the existing `PhotoTag` model — it does NOT replace it.

**Core principle:** `PhotoTag` remains the source of truth for saved observations. `LitterItem` is a definition layer that improves search and accelerates tagging by expanding into normal `PhotoTag` payloads.

**What it is at runtime:**
- **Database-backed lookup catalog** — two read-only tables (`litter_items` + `litter_item_components`)
- **Seeded from code** — a Laravel seeder populates the initial curated catalog, version-controlled in Git
- **Exposed via API** — folded into `GET /api/tags/all` response as `litter_items` key
- **Frontend shortcut** — used only for search results and client-side expansion into standard tag payloads
- **No provenance** — v1 does NOT track which `LitterItem` a `PhotoTag` came from. No `litter_item_id` FK on `photo_tags`.

```
LitterItem (definition)                  PhotoTag (observation)
┌─────────────────────────┐              ┌──────────────────────┐
│ juice_carton_with_straw │   expands    │ photo_tags row 1:    │
│                         │  ────────►   │   carton + type:juice│
│ components:             │              │ photo_tags row 2:    │
│   1. carton (primary)   │              │   straw              │
│   2. straw (attached)   │              └──────────────────────┘
└─────────────────────────┘
```

**What it is NOT:**
- Not a new tag storage format
- Not a replacement for `PhotoTag`
- Not required for current tagging to work
- Not a way to encode brands, materials, or `picked_up` into item identity

## 3. Architecture Fit

### Unchanged
- `photos`, `photo_tags`, `photo_tag_extra_tags` — no schema changes
- `summary` JSON — still generated from `PhotoTag` rows
- XP calculation — still from expanded `PhotoTag`s via `XpScore` enum
- `AddTagsToPhotoAction` — still the write path
- `POST /api/v3/tags` and `PUT /api/v3/tags` — unchanged
- `GeneratePhotoSummaryService` — unchanged
- `MetricsService` — unchanged

### New tables
- `litter_items` — item template definitions
- `litter_item_components` — component objects per item

### No changes to photo_tags
`litter_item_id` is NOT added to `photo_tags` in v1. The item definition is a search/UX layer only. If we later want to track "this photo tag came from litter item X," that's a future enhancement.

### Relationship to `TagsConfig`

`LitterItem` does **not** replace `TagsConfig`. The existing taxonomy remains the source of truth:

- `TagsConfig` defines canonical category/object definitions, valid types per object, and valid material options per object
- `GenerateTagsSeeder` converts `TagsConfig` into database rows (categories, litter_objects, CLO pivots, materials, types, category_object_types)
- `BrandsConfig` defines brand→object relationships per category

`LitterItem` is a **curated template layer** that composes existing CLO + optional type combinations into recognizable items. It must not duplicate or override structural rules already in `TagsConfig`. For example:

- `TagsConfig` already says `softdrinks.bottle` has types `[water, soda, juice, energy, ...]` and materials `[plastic, glass]`
- `LitterItem` says "`water_bottle` = softdrinks.bottle + type:water" — a curated shortcut, not a re-definition
- Material options for that bottle are still governed by `TagsConfig`, not `LitterItem`

**Hierarchy:**
```
TagsConfig / BrandsConfig     (structural ontology — source of truth)
    ↓
DB tables (categories, litter_objects, CLO pivots, materials, types, brandslist)
    ↓
LitterItem                    (curated template/shortcut layer)
    ↓
PhotoTag                      (saved observations — actual data)
```

### Brands

Brands remain observation-time extras and are **not** stored on `LitterItem` in v1.

- `BrandsConfig::BRAND_OBJECTS` already defines 100+ brands with per-category object associations (e.g., `starbucks → coffee.[cup, lid, sleeve, stirrer]`)
- Brands are dynamic and many-to-many with objects — encoding them into item identity would cause combinatorial explosion
- Existing brand tagging works through `photo_tag_extra_tags` with `tag_type = 'brand'`
- Future search may use brand→object relationships from `BrandsConfig` to rank or suggest `LitterItem`s, but brand does not define the item template

## 4. Database Schema

### 4.1 `litter_items`

```sql
CREATE TABLE litter_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    category_litter_object_id BIGINT UNSIGNED NOT NULL,
    litter_object_type_id BIGINT UNSIGNED NULL,
    description TEXT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    crowdsourced BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (category_litter_object_id)
        REFERENCES category_litter_object(id) ON DELETE CASCADE,
    FOREIGN KEY (litter_object_type_id)
        REFERENCES litter_object_types(id) ON DELETE SET NULL
);
```

**Notes:**
- `key` is the unique slug: `juice_carton_with_straw`, `beer_bottle`, `cigarette_butt`
- `category_litter_object_id` is **NOT NULL** — every item must be anchored to a primary CLO from the existing `category_litter_object` pivot. No "pure wrapper" items in v1.
- `litter_object_type_id` is the v5.1 type dimension ("what was in it" — beer, water, juice, etc.)
- `crowdsourced` tracks user-submitted items vs admin-seeded ones
- `active` allows soft-disabling items without deleting

### 4.2 `litter_item_components`

```sql
CREATE TABLE litter_item_components (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    litter_item_id BIGINT UNSIGNED NOT NULL,
    category_litter_object_id BIGINT UNSIGNED NOT NULL,
    litter_object_type_id BIGINT UNSIGNED NULL,
    quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    role VARCHAR(50) NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (litter_item_id)
        REFERENCES litter_items(id) ON DELETE CASCADE,
    FOREIGN KEY (category_litter_object_id)
        REFERENCES category_litter_object(id) ON DELETE CASCADE,
    FOREIGN KEY (litter_object_type_id)
        REFERENCES litter_object_types(id) ON DELETE SET NULL,

    UNIQUE (litter_item_id, category_litter_object_id, litter_object_type_id)
);
```

**Notes:**
- `category_litter_object_id` is NOT nullable here — every component must reference a real CLO
- `is_primary` — exactly one component per item MUST be primary (MUST match `litter_items.category_litter_object_id`)
- `role` — optional descriptor: `primary`, `attached`, `contained`, `cap`, `lid`, `straw`, `wrapper`
- `sort_order` — controls display order (primary first, then attachments)
- `quantity` — default 1. Keep it simple in v1 — don't model "packet with 20 butts" yet
- Unique constraint prevents duplicate components on the same item

## 5. Data Model Rules

### 5.1 Primary component invariant

Each `LitterItem` MUST have exactly one component where `is_primary = true`. That component's CLO MUST match `litter_items.category_litter_object_id`, and its type MUST match `litter_items.litter_object_type_id`. This is enforced by the seeder and validated at seed time.

### 5.2 Simple items

A simple item (no sub-components) has exactly one component:

```
litter_items: { key: "cigarette_butt", clo_id: 42 }
litter_item_components: [
    { clo_id: 42, is_primary: true, role: "primary" }
]
```

This seems redundant, but it means the expansion logic is uniform — always iterate components.

### 5.3 Composite items

A composite item has 2+ components:

```
litter_items: { key: "coffee_cup_with_lid", clo_id: 55 }
litter_item_components: [
    { clo_id: 55, type_id: 3, is_primary: true, role: "primary", sort_order: 0 },
    { clo_id: 91, is_primary: false, role: "lid", sort_order: 1 }
]
```

### 5.4 What LitterItem does NOT encode

- **Brands** — "Coca-Cola can" is not a LitterItem. "Can" is the item; Coca-Cola is an observation-time brand stored via `photo_tag_extra_tags`. Brand→object relationships from `BrandsConfig` may influence future search ranking, but brands are not part of item identity.
- **Materials** — "Plastic bottle" is not a LitterItem. "Bottle" is the item; plastic is an observation-time material. Material options are governed by `TagsConfig` (e.g., `alcohol.bottle` → `[glass, plastic]`). `LitterItem` must not duplicate these defaults.
- **Picked up status** — observation-time, not definition
- **Custom tags** — observation-time, not definition
- **Quantity** — observation-time, not definition. Component quantities default to 1.

### 5.5 Structural validity

Every `LitterItem` and its components must reference valid entries in the existing taxonomy:

- `litter_items.category_litter_object_id` must be non-null and reference a valid CLO
- Each item must have exactly one primary component (`is_primary = true`)
- The primary component's CLO must equal `litter_items.category_litter_object_id`
- The primary component's type must equal `litter_items.litter_object_type_id`
- Any component `litter_object_type_id` must be valid for that component's CLO (i.e., exist in `category_object_types` pivot)
- `LitterItem` must not invent CLOs, types, or materials that don't exist in `TagsConfig` / seeder output

## 6. Expansion Model

When a user selects a `LitterItem` in the tagging UI, it expands into the normal tag payload:

### Example: `juice_carton_with_straw`

Components:
1. `carton` (CLO 44, type: juice) — primary
2. `straw` (CLO 91) — attached

Expands to POST payload:
```json
{
    "photo_id": 123,
    "tags": [
        {
            "category_litter_object_id": 44,
            "litter_object_type_id": 7,
            "quantity": 1,
            "picked_up": true
        },
        {
            "category_litter_object_id": 91,
            "quantity": 1,
            "picked_up": true
        }
    ]
}
```

This is a standard `POST /api/v3/tags` payload. No backend changes needed. The expansion happens client-side.

### Expansion rules

1. Each component becomes one tag in the payload
2. `picked_up` from the UI is applied to ALL components (user picked up the whole item)
3. `quantity` defaults to the component's default (usually 1), multiplied by the user's quantity
4. Materials/brands/custom tags are added by the user at observation time, not from the template
5. Primary component inherits the item's `litter_object_type_id`

## 7. API

### 7.1 Folded into `GET /api/tags/all` (Phase 2)

`LitterItem` data is returned as a new `litter_items` key in the existing tags response:

```json
{
    "categories": [...],
    "objects": [...],
    "types": [...],
    "category_objects": [...],
    "category_object_types": [...],
    "litter_items": [
        {
            "id": 12,
            "key": "juice_carton_with_straw",
            "name": "Juice Carton with Straw",
            "category_litter_object_id": 44,
            "litter_object_type_id": 7,
            "components": [
                {
                    "category_litter_object_id": 44,
                    "litter_object_type_id": 7,
                    "quantity": 1,
                    "role": "primary",
                    "is_primary": true,
                    "sort_order": 0
                },
                {
                    "category_litter_object_id": 91,
                    "litter_object_type_id": null,
                    "quantity": 1,
                    "role": "straw",
                    "is_primary": false,
                    "sort_order": 1
                }
            ]
        }
    ]
}
```

Only `active = true` items are returned. Components are eager-loaded and sorted by `sort_order`.

### 7.2 No write endpoint in v1

Items are seeded by admin/migration. No public creation endpoint yet. `crowdsourced = true` items can come later via a suggestion flow.

## 8. Frontend Integration

### 8.1 Search

`UnifiedTagSearch.vue` (or the React Native equivalent) adds `LitterItem` results alongside existing object/type/material/brand/custom results. Composite items show a component count badge.

### 8.2 Selection

When a user selects a `LitterItem`:
1. Client-side expansion creates multiple tag drafts from components
2. Each draft becomes a tag card in `ActiveTagsList`
3. User can modify quantities, add materials/brands, set `picked_up` per tag
4. On submit, the normal `POST /api/v3/tags` payload is sent

### 8.3 No backend write-path changes

The frontend sends expanded tags, not `litter_item_id`. The backend doesn't know or care that the tags came from a template. This means:

- Zero risk to existing tagging
- Zero risk to metrics/summary/XP
- Mobile and web can adopt at different speeds
- Old frontend versions continue to work

## 9. Seed Data (Phase 1)

Start with high-value items. Include both simple (CLO+type shorthand) and composite variants:

| Key | Primary CLO | Components | Type |
|-----|------------|------------|------|
| `cigarette_butt` | butts | butts | Simple — high frequency |
| `beer_bottle` | bottle (type: beer) | bottle | Simple — CLO+type shorthand |
| `beer_can` | can (type: beer) | can | Simple — CLO+type shorthand |
| `water_bottle` | bottle (type: water) | bottle | Simple — CLO+type shorthand |
| `juice_carton` | carton (type: juice) | carton | Simple — standalone variant |
| `coffee_cup` | cup (type: coffee) | cup | Simple — standalone variant |
| `coffee_cup_with_lid` | cup (type: coffee) | cup, lid | Composite |
| `juice_carton_with_straw` | carton (type: juice) | carton, straw | Composite |
| `bottle_with_cap` | bottle | bottle, cap | Composite |
| `can_with_tab` | can | can, tab | Composite |

**Selection rule for simple items:** Only items that provide meaningful CLO+type shorthand or strong search intent. Do NOT add plain `wrapper`, `bag`, `lid` — those are well-served by existing object search.

**Both variants seeded where useful:** `juice_carton` AND `juice_carton_with_straw`, `coffee_cup` AND `coffee_cup_with_lid`. Users pick the one that matches what they see.

## 10. Phased Rollout

### Phase 1: Schema + seed (no API, no frontend)
- Migration: create `litter_items` and `litter_item_components`
- Seeder: populate ~10-20 high-value items
- Tests: model relationships, component invariants
- **Risk:** Zero — read-only tables, no existing code touched

### Phase 2: Read-only API
- Add `litter_items` key to `GET /api/tags/all` response (folded, not separate endpoint)
- Each item includes eager-loaded components
- **Risk:** Zero — additive key in existing response, no write-path changes

### Phase 3: Frontend search integration
- Show `LitterItem` results in tag search alongside existing object/type results
- Client-side expansion on selection → normal editable tag cards
- Component count badge on composite items only (`2 parts`, `3 parts`)
- Users can remove expanded components before submitting
- **Risk:** Low — expansion produces standard tag payloads

### Phase 3.5: Measure before expanding

Before growing the catalog beyond ~10-15 items, measure:
- Do users actually select composite `LitterItem`s in search?
- Do composite items reduce tagging time vs individual tag selection?
- Does the `litter_items` payload in `/api/tags/all` cause noticeable load time increase?
- Do users remove expanded components (indicating the composite was wrong for their case)?

This data determines whether to invest in Phase 4 or keep it as a small curated catalog.

### Phase 4: Future enhancements (deferred)
- Optional `litter_item_id` FK on `photo_tags` (analytics only — provenance tracking)
- Material/brand defaults per component
- User-submitted item suggestions (`crowdsourced = true`)
- AI training labels from item templates
- Recursive components (item containing items)

## 11. Laravel Models

### `LitterItem`

```php
class LitterItem extends Model
{
    protected $fillable = [
        'key', 'name', 'category_litter_object_id',  // NOT NULL — every item has a primary CLO
        'litter_object_type_id', 'description', 'active', 'crowdsourced',
    ];

    protected $casts = [
        'active' => 'boolean',
        'crowdsourced' => 'boolean',
    ];

    public function primaryClo(): BelongsTo
    {
        return $this->belongsTo(CategoryObject::class, 'category_litter_object_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LitterObjectType::class, 'litter_object_type_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(LitterItemComponent::class)->orderBy('sort_order');
    }

    public function primaryComponent(): HasOne
    {
        return $this->hasOne(LitterItemComponent::class)->where('is_primary', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
```

### `LitterItemComponent`

```php
class LitterItemComponent extends Model
{
    protected $fillable = [
        'litter_item_id', 'category_litter_object_id',
        'litter_object_type_id', 'quantity', 'role',
        'is_primary', 'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public function litterItem(): BelongsTo
    {
        return $this->belongsTo(LitterItem::class);
    }

    public function clo(): BelongsTo
    {
        return $this->belongsTo(CategoryObject::class, 'category_litter_object_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LitterObjectType::class, 'litter_object_type_id');
    }
}
```

## 12. Expansion Helper (Client-Side)

```js
/**
 * Expand a LitterItem into tag drafts for the tagging UI.
 * Each component becomes one tag in the standard payload format.
 */
function expandLitterItem(item, userQuantity = 1, pickedUp = null) {
    return item.components.map((component) => ({
        cloId: component.category_litter_object_id,
        typeId: component.litter_object_type_id || undefined,
        quantity: component.quantity * userQuantity,
        pickedUp,
        materials: [],
        brands: [],
        customTags: [],
        fromLitterItem: item.key, // UI hint, not sent to backend
    }));
}
```

## 13. What This Spec Intentionally Defers

| Feature | Why deferred |
|---------|-------------|
| `litter_item_id` on `photo_tags` | No analytics need yet. Can add later without migration headache. |
| Material defaults per component | `TagsConfig` already defines valid materials per CLO. Duplicating this creates drift. Users add materials at tag time. Future: per-component overrides for composites only. |
| Brand defaults / constraints | Brands are dynamic observation-time extras. `BrandsConfig::BRAND_OBJECTS` already handles brand→object associations. Encoding brands into item identity causes combinatorial explosion. |
| Alias tables | Can use existing `ClassifyTagsService::normalizeDeprecatedTag()` pattern. |
| Confidence / ML metadata | Premature. Wait until AI training actually needs it. |
| Recursive components | Flat components cover 99% of cases. Tree model is overkill. |
| User creation endpoint | Start admin-seeded. User suggestions can come later. |
| Deprecation chains | Item A replaced by Item B — not needed until item catalog grows. |

## 14. Design Decisions (Resolved)

### Q1: Should simple items (1 component) be in `litter_items`?

**Yes, selectively.** Do NOT put every single-component object in `litter_items` — that duplicates the CLO/type catalog. Only include simple items when they provide:

- Meaningful shorthand for CLO + type (e.g., `beer_bottle` = bottle + type:beer)
- Strong search intent (users think/search "beer bottle" as one phrase)
- A stable future AI label

**Good candidates:** `beer_bottle`, `water_bottle`, `beer_can`, `juice_carton`, `cigarette_butt`
**Bad candidates:** plain `wrapper`, `bag`, `lid`, `other` — already well-served by base tagging

### Q2: Separate API endpoint or fold into `GET /api/tags/all`?

**Fold into `GET /api/tags/all` in v1.** Same lifecycle as categories/objects/types. One request for Vue and RN. Lower implementation overhead.

Response shape:
```json
{
    "categories": [...],
    "objects": [...],
    "types": [...],
    "category_objects": [...],
    "category_object_types": [...],
    "litter_items": [...]
}
```

Split into a separate endpoint later only if the payload gets too large or server-side search/pagination is needed.

### Q3: Show component count badge in search?

**Yes, but only for composite items.** Simple items get no badge (noise). Composite items show a subtle secondary indicator:

- `Juice Carton with Straw` `2 parts`
- `Coffee Cup with Lid` `2 parts`

Do NOT show `Beer Bottle` `1 part` — that's clutter.

### Q4: How to handle partial selection?

**Expand first, let users remove components.** When a user selects "Juice Carton with Straw":

1. UI expands into 2 normal tag cards: carton + straw
2. Both are editable — user can remove straw before submitting
3. No confirmation modal, no follow-up questions

**Also seed both variants where useful:**
- `juice_carton` (simple, 1 component)
- `juice_carton_with_straw` (composite, 2 components)

This way users who only want the carton can select it directly. Users who want the composite use the shortcut. Components are always removable after expansion.

---

## Related Docs

- **readme/Tags.md** — Current tagging architecture (PhotoTag, extras, summary, XP)
- **readme/Taggingarchitecturespec.md** — v5.1 type dimension spec
- **readme/Upload.md** — Upload + tag pipeline
- **readme/API.md** — Endpoint contracts
