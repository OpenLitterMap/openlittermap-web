# Photos Table Audit

**Schema:** 65 columns, 46 indexes, 24 FKs, 521,217 rows (97,123 unmigrated), 2 triggers
**Audited:** 2026-03-14

---

## Phase A — Safe Now

### Columns to Drop

Zero references in active code. Can be dropped immediately.

| Column                   | Type | Grep Evidence |
|--------------------------|------|--------------|
| `incorrect_verification` | int unsigned | 0 matches in app/ |
| `generated`              | tinyint(1) | 0 matches in app/ |
| `suburb`                 | varchar | Only `Suburb.php` model `$fillable` — no queries, no reads |
| `state_district`         | varchar | 0 matches (ResolveLocationAction uses geocoder key, not this column) |
| `wrong_tags`             | tinyint(1) | 0 matches in app/ |
| `wrong_tags_by`          | int unsigned | 0 matches in app/ |
| `geohash`                | varchar | Only consumers are deprecated `GlobalMapController` + `FilterPhotosByGeoHashTrait` (both `@deprecated`). v5 uses `geom` spatial column + `idx_photos_fast_cluster`. Frontend callers removed 2026-03-14. Still written by UploadPhotoController — remove write after dropping column. |


### Columns to drop and remove code references
| `total_brands`           | Brand count per photo |
| `total_litter`           | Code references removed (2026-03-14). `Photo::total()` method deleted, CSV export uses `total_tags`. Safe to drop. |
- Remove `suburb` from `Suburb.php` model and delete `Suburb.php`. We only have Country, State City for now.
- Remove dropped columns from Photo model `$fillable`/`$guarded` if present
- Remove `political_id` and all references. Delete political.php
- Remove 'verification' and all references.

### Geohash Cleanup (when dropping `geohash` column)
- [ ] Remove geohash write from `UploadPhotoController.php` (line 118: `'geohash' => ...`)
- [ ] Delete `app/Traits/GeohashTrait.php` (already `@deprecated`)
- [ ] Delete `app/Traits/FilterPhotosByGeoHashTrait.php` (already `@deprecated`)
- [ ] Delete `app/Http/Controllers/Maps/GlobalMapController.php` (already `@deprecated`)
- [ ] Remove `use FilterPhotosByGeoHashTrait` from `TeamsClusterController.php`
- [ ] Delete `TeamsClusterController::points()` method (already `@deprecated`)
- [ ] Remove deprecated routes: `/global/points`, `/global/art-data`, `/teams/points/{team}`
- [ ] Remove `use App\Http\Controllers\Maps\GlobalMapController` import from `routes/api.php`

### Indexes to Drop

Redundant or unused indexes. No query references, no USE INDEX hints.

| Index | Columns | Evidence |
|-------|---------|---------|
| `total_tags_idx` | `(created_at, country_id, state_id, city_id, total_tags)` | 0 references. No matching query patterns in app/ |
| `photos_geohash_index` | `(geohash)` | Geohash column deprecated — all consumers use `@deprecated` traits. Drop with column. |
| `idx_photos_verified_lat_lon` | `(verified, lat, lon)` | 0 references, no USE INDEX hints. Superseded by `idx_photos_fast_cluster` + `photos_geom_sidx` |
| `idx_photos_verified_tile` | `(verified, tile_key)` | Prefix of `idx_photos_fast_cluster (verified, tile_key, cell_x, cell_y, lat, lon)` — fully redundant |
| `idx_photos_tile_key` | `(tile_key)` | Also a prefix of `idx_photos_fast_cluster` — fully redundant |

---

## Phase B — Post-Migration

Drop after v5 migration completes (97,123 photos still unmigrated).

### v4 Category FK Columns (18 columns)

All read by `UpdateTagsService::getTags()` via `$photo->tags()`. Required until migration finishes.

| Column | Type | FK Constraint | FK Target Table | Notes |
|--------|------|--------------|-----------------|-------|
| `smoking_id` | int unsigned | `photos_smoking_id_foreign` | `smoking` | |
| `food_id` | int unsigned | `photos_food_id_foreign` | `food` | |
| `coffee_id` | int unsigned | `photos_coffee_id_foreign` | `coffee` | |
| `alcohol_id` | int unsigned | `photos_alcohol_id_foreign` | `alcohol` | |
| `softdrinks_id` | int unsigned | `photos_softdrinks_id_foreign` | `softdrinks` | |
| `drugs_id` | int unsigned | `photos_drugs_id_foreign` | `drugs` | |
| `sanitary_id` | int unsigned | `photos_sanitary_id_foreign` | `sanitary` | |
| `other_id` | int unsigned | `photos_other_id_foreign` | `other` | |
| `coastal_id` | int unsigned | `photos_coastal_id_foreign` | `coastal` | |
| `pathways_id` | int unsigned | `photos_pathways_id_foreign` | `pathways` | **Missing from PostMigrationCleanup.md** |
| `art_id` | int unsigned | `photos_art_id_foreign` | `arts` | |
| `brands_id` | int unsigned | `photos_brands_id_foreign` | `brands` | |
| `trashdog_id` | int unsigned | `photos_trashdog_id_foreign` | `trashdog` | |
| `political_id` | int unsigned | `photos_political_id_foreign` | `politicals` | **Missing from PostMigrationCleanup.md** |
| `dumping_id` | bigint unsigned | `photos_dumping_id_foreign` | `dumping` | |
| `industrial_id` | bigint unsigned | `photos_industrial_id_foreign` | `industrial` | |
| `material_id` | bigint unsigned | `photos_material_id_foreign` | `material` | |
| `dogshit_id` | bigint unsigned | **NO FK CONSTRAINT** | — | **Must skip `dropForeign()` in migration** |

### Other Post-Migration Columns (3 columns)

| Column | Type | Active References | Notes |
|--------|------|------------------|-------|
| `result_string` | text | Written by `GeneratePhotoSummaryService` during migration | Write-only; all active endpoints use `summary`. Per PostMigrationCleanup.md |
| `migrated_at` | datetime | Migration progress tracking | Drop after migration complete |

### Post-Migration Indexes to Drop (18 indexes)

All FK indexes for the 18 v4 category columns, plus migration tracking:

| Index | Notes |
|-------|-------|
| `photos_smoking_id_foreign` | v4 FK index |
| `photos_food_id_foreign` | v4 FK index |
| `photos_coffee_id_foreign` | v4 FK index |
| `photos_alcohol_id_foreign` | v4 FK index |
| `photos_softdrinks_id_foreign` | v4 FK index |
| `photos_drugs_id_foreign` | v4 FK index |
| `photos_sanitary_id_foreign` | v4 FK index |
| `photos_other_id_foreign` | v4 FK index |
| `photos_coastal_id_foreign` | v4 FK index |
| `photos_pathways_id_foreign` | v4 FK index |
| `photos_art_id_foreign` | v4 FK index |
| `photos_brands_id_foreign` | v4 FK index |
| `photos_trashdog_id_foreign` | v4 FK index |
| `photos_political_id_foreign` | v4 FK index |
| `photos_dumping_id_foreign` | v4 FK index |
| `photos_industrial_id_foreign` | v4 FK index |
| `photos_material_id_foreign` | v4 FK index |
| `photos_migrated_at_index` | Migration tracking |

---

## PostMigrationCleanup.md Corrections Needed

- [ ] Add missing FK columns: `pathways_id`, `drugs_id`
- [ ] Note: `dogshit_id` has NO FK constraint — migration must skip `dropForeign()`
- [ ] Note: `political_id` FK references `politicals` table (not `political`)

---

## Active — Do Not Touch

### Core Columns

| Column                                                           | Usage |
|------------------------------------------------------------------|-------|
| `id`                                                             | Primary key |
| `user_id`                                                        | Owner FK |
| `filename`                                                       | Photo file path / URL |
| `model`                                                          | Device model |
| `datetime`                                                       | Photo capture time |
| `verified`                                                       | VerificationStatus enum (0-5) |
| `is_public`                                                      | Public scope filter |
| `remaining`                                                      | Deprecated but 14+ active reads — drop after migration |
| `total_tags`                                                     | v5 tag count (active) |
| `summary`                                                        | v5 tag summary text (active) |
| `lat`, `lon`                                                     | Coordinates |
| `geom`                                                           | Spatial POINT (hidden from JSON, populated by trigger) |
| `cell_x`, `cell_y`                                               | Generated clustering grid coords |
| `tile_key`                                                       | Clustering tile key |
| ~~`geohash`~~                                                    | **Moved to Phase A drop** — all consumers deprecated 2026-03-14 |
| `country_id`, `state_id`, `city_id`                              | Location FKs |
| `team_id`                                                        | Team FK |
| `participant_id`                                                 | Participant sessions FK |
| `team_approved_at`, `team_approved_by`                           | School approval pipeline |
| `platform`                                                       | Upload source (web/mobile) |
| `verified_by`                                                    | Admin verification FK |
| `verification: deprecated` (double)                              | Active — `AddTagsToPhotoAction` sets 0.1/1, `FilterPhotos` queries it |
| `address_array`                                                  | Upload pipeline, display_name accessor, CSV export |
| `xp`                                                             | Per-photo XP |
| `processed_at`, `processed_fp`, `processed_tags`, `processed_xp` | Metrics idempotency |
| `created_at`, `updated_at`, `deleted_at`                         | Timestamps + soft deletes |
| `bounding_box` (text)                                            | text | 0 matches in app code. Column unused but bbox *system* is active — see Active section |
| `bbox_skipped` | VerifyBoxController queries |
| `bbox_assigned_to` | VerifyBoxController reads |
| `skipped_by` | Bbox system (0 app code refs but part of active system) |
| `bbox_verification_assigned_to` | VerifyBoxController reads/writes |
| `five_hundred_square_filepath` | Bbox workflow, photo deletion |

### Active Indexes (24 indexes)

| Index | Columns | Why |
|-------|---------|-----|
| `PRIMARY` | id | Required |
| `photos_user_id_foreign` | user_id | FK + user photo queries |
| `photos_country_id_foreign` | country_id | FK + location filters |
| `photos_state_id_foreign` | state_id | FK |
| `photos_city_id_foreign` | city_id | FK |
| `photos_team_id_foreign` | team_id | FK |
| `photos_participant_id_foreign` | participant_id | FK |
| `photos_verified_by_foreign` | verified_by | FK |
| `photos_created_at_index` | created_at | ORDER BY created_at DESC |
| `photos_deleted_at_index` | deleted_at | SoftDeletes scope |
| `photos_datetime_id_idx` | (datetime, id) | Date range queries |
| `idx_photos_fast_cluster` | (verified, tile_key, cell_x, cell_y, lat, lon) | **Critical** — USE INDEX in 3 clustering code paths |
| `idx_photos_tile_updated` | (tile_key, updated_at) | Dirty tile detection |
| `photos_geom_sidx` | geom (SPATIAL) | MBRContains spatial queries |
| `photos_public_verified_idx` | (is_public, verified) | Points API, admin queue |
| `photos_team_approval_idx` | (team_id, is_public, verified, created_at) | Facilitator approval queue |
| `photos_team_created_idx` | (team_id, created_at) | Team dashboard |
| `photos_team_public_idx` | (team_id, is_public) | Team photo listing |
| `photos_team_public_created_idx` | (team_id, is_public, created_at) | Team dashboard |
| `photos_team_public_verified_created_idx` | (team_id, is_public, verified, created_at) | Team approval + dashboard |
| `photos_team_user_idx` | (team_id, user_id, created_at) | Per-member counts |
| `photos_team_participant_idx` | (team_id, participant_id) | Participant sessions |
| `photos_processed_at_index` | processed_at | Metrics pipeline |
| `photos_processed_fp_index` | processed_fp | Idempotency checks |

### Triggers (Active)

| Trigger | Event | Purpose |
|---------|-------|---------|
| `photos_bi_geom` | BEFORE INSERT | Validates lat/lon, sets `geom = ST_SRID(POINT(lon, lat), 4326)` |
| `photos_bu_geom` | BEFORE UPDATE | Same for updates |

---

## Also: `merchant_photos` Table

| Detail | Value |
|--------|-------|
| Rows | Unknown (likely small) |
| References | `DeleteAccountController`, `MerchantPhoto` model only |
| FK | `merchant_id` → `merchants`, `uploaded_by` → `users` |
| Status | `MerchantsController` was deleted in dead code cleanup. Table is likely a Phase A drop candidate — **confirm merchants feature is fully retired first** |
