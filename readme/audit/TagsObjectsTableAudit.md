# Tags & Objects Table Audit

**Audited:** 2026-03-14

---

## Table Inventory

### v5 Tables (Active)

| Table | Rows | Purpose |
|-------|------|---------|
| `photo_tags` | 482,618 | Per-photo tag entries (CLO + quantity + picked_up) |
| `photo_tag_extra_tags` | 498,780 | Extra dimensions (brands, materials, custom tags) on photo_tags |
| `categories` | 17 | Tag categories (smoking, food, etc.) |
| `litter_objects` | 198 | Individual litter objects (butts, wrapper, etc.) |
| `category_litter_object` | 175 | CLO pivot (category + object = unique CLO ID) |
| `litter_object_types` | 33 | Type dimension (full, empty, broken, etc.) |
| `category_object_types` | 61 | Which types apply to which CLOs |
| `materials` | 40 | Material dimension (plastic, glass, metal, etc.) |
| `brandslist` | 2,615 | Brand dimension (v5 normalized brand list) |
| `custom_tags_new` | 6,928 | Custom tag dimension (user-created tags) |

### v4 Tables (Migration-Only)

| Table | Rows | Purpose |
|-------|------|---------|
| `smoking` | 95,162 | v4 category table — wide columns for each object |
| `food` | 80,247 | v4 category table |
| `softdrinks` | 97,022 | v4 category table |
| `alcohol` | 39,102 | v4 category table |
| `coffee` | 12,568 | v4 category table |
| `sanitary` | 20,045 | v4 category table |
| `other` | 167,469 | v4 category table |
| `coastal` | 21,446 | v4 category table |
| `dumping` | 1,854 | v4 category table |
| `industrial` | 1,547 | v4 category table |
| `material` | 26,662 | v4 material table (wide columns — NOT the same as v5 `materials`) |
| `brands` | 70,851 | v4 brands table (wide columns — NOT the same as v5 `brandslist`) |
| `arts` | 64 | v4 category table |
| `trashdog` | 218 | v4 category table |
| `pathways` | 89 | v4 category table |
| `drugs` | 48 | v4 category table |
| `dogshit` | 1,438 | v4 category table |
| `politicals` | 0 | v4 category table (empty — already dropped?) |
| `custom_tags` | 117,961 | v4 custom tags (per-photo tag text, not normalized) |

### Empty / Unused Tables

| Table | Rows | Status |
|-------|------|--------|
| `litter_states` | 0 | Schema exists, model exists, referenced by ManagesTaggables trait but 0 rows. Never populated. |
| `category_litter_object_material` | 0 | Pivot for CLO→material defaults. Schema exists, 0 references in app/ code. Never populated. |

---

## Phase A — Safe Now

### Columns to Drop
None

### Tables to Drop

| Table | Rows | Grep Evidence | Notes |
|-------|------|--------------|-------|
| `category_litter_object_material` | 0 | 0 matches in app/ | Pivot table never populated, never queried |
| `litter_states` | 0 | Only `LitterState` model + `ManagesTaggables` trait + `CategoryObject` relationship. Never populated, never called in active code | Also delete `LitterState.php` model |

### Indexes — No Drops

All v5 table indexes are justified:

**`photo_tags` (8 indexes):**
| Index | Columns | Status |
|-------|---------|--------|
| `PRIMARY` | id | Required |
| `photo_tags_photo_id_foreign` | photo_id | FK (implicit via `idx_photo_category`) |
| `idx_photo_tags_clo` | category_litter_object_id | CLO lookups |
| `idx_photo_category` | (photo_id, category_id) | Photo+category queries |
| `idx_photo_object` | (photo_id, litter_object_id) | Photo+object queries |
| `idx_category_object` | (category_id, litter_object_id) | Category+object queries |
| `idx_category_quantity` | (category_id, quantity) | Aggregation queries |
| `idx_object_quantity` | (litter_object_id, quantity) | Aggregation queries |
| `photo_tags_litter_object_type_id_foreign` | litter_object_type_id | FK index |

**`photo_tag_extra_tags` (3 indexes):** All justified (PK, FK, type+id composite).

**All other v5 tables:** Indexes are minimal and justified (PK, unique constraints, FK indexes, covering indexes).

---

## Phase B — Post-Migration

### v4 Category Tables to Drop (18 tables)

All read by `UpdateTagsService::getTags()` via `$photo->tags()` method which reads v4 FK columns on photos. Required until migration completes (97,123 photos remaining).

| Table | Rows | FK from photos | Notes |
|-------|------|---------------|-------|
| `smoking` | 95,162 | `smoking_id` | |
| `food` | 80,247 | `food_id` | |
| `softdrinks` | 97,022 | `softdrinks_id` | |
| `alcohol` | 39,102 | `alcohol_id` | |
| `coffee` | 12,568 | `coffee_id` | |
| `sanitary` | 20,045 | `sanitary_id` | |
| `other` | 167,469 | `other_id` | |
| `coastal` | 21,446 | `coastal_id` | |
| `dumping` | 1,854 | `dumping_id` | |
| `industrial` | 1,547 | `industrial_id` | |
| `material` | 26,662 | `material_id` | v4 wide-column table — NOT `materials` (v5) |
| `brands` | 70,851 | `brands_id` | v4 wide-column table — NOT `brandslist` (v5) |
| `arts` | 64 | `art_id` | |
| `trashdog` | 218 | `trashdog_id` | |
| `pathways` | 89 | `pathways_id` | |
| `drugs` | 48 | `drugs_id` | |
| `dogshit` | 1,438 | `dogshit_id` | **NO FK constraint on photos** — skip `dropForeign()` |
| `politicals` | 0 | `political_id` | Empty table, but FK constraint still exists |

### v4 Custom Tags Table

| Table | Rows | Notes |
|-------|------|-------|
| `custom_tags` | 117,961 | v4 per-photo custom tags. Only referenced in `tmp/v5/Migration/` commands. Drop after migration. |

### Post-Migration Code Cleanup

After dropping v4 tables, also delete:
- [ ] All v4 category model files (Smoking, Food, Coffee, Alcohol, etc.)
- [ ] `ManagesTaggables` trait (only used by CategoryObject for litter_states)
- [ ] `LitterState` model
- [ ] `CustomTag` model (v4 — keep `CustomTagNew` model)
- [ ] `Suburb` model
- [ ] Photo model `tags()` method (reads v4 FK columns)
- [ ] All `app/Console/Commands/tmp/` migration commands

---

## Active — Do Not Touch

### v5 Core Tables

All columns on these tables are actively used:

**`photo_tags`** — All 10 columns active (id, photo_id, category_id, litter_object_id, category_litter_object_id, litter_object_type_id, quantity, picked_up, created_at, updated_at). All nullable FK columns are intentionally nullable for extra-tag-only PhotoTags.

**`photo_tag_extra_tags`** — All 7 columns active.

**`categories`** — All 6 columns active. `parent_id` used for category hierarchy (Category model relationships). `crowdsourced` used by AchievementsController to filter official vs user-created categories.

**`litter_objects`** — All 5 columns active. `crowdsourced` used by ClassifyTagsService.

**`category_litter_object`** — All 5 columns active. Core CLO pivot.

**`litter_object_types`** — All 5 columns active. Type dimension for pills.

**`category_object_types`** — Both columns active. CLO→type pivot.

**`materials`** — All 5 columns active. `crowdsourced` set by ClassifyTagsService.

**`brandslist`** — 5 of 6 columns active (`is_custom` is unused — see Phase A). `crowdsourced` set by ClassifyTagsService.

**`custom_tags_new`** — All 7 columns active. `approved` used by Points aggregation queries to filter displayable custom tags. `created_by` FK to users.
