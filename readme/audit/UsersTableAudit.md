# Users Table Audit

**Schema:** 68 columns, 8 indexes, 8,948 rows, 1 FK constraint
**Audited:** 2026-03-14

---

## Phase A — Safe Now

Zero references in active code. Can be dropped immediately.

### Columns to Drop

| Column | Type | Grep Evidence |
|--------|------|--------------|
| `total_smoking` | int unsigned | 0 matches in app/ |
| `total_food` | int unsigned | 0 matches in app/ |
| `total_softdrinks` | int unsigned | 0 matches in app/ |
| `total_alcohol` | int unsigned | 0 matches in app/ |
| `total_coffee` | int unsigned | 0 matches in app/ |
| `total_sanitary` | int unsigned | 0 matches in app/ |
| `total_other` | int unsigned | 0 matches in app/ |
| `total_dumping` | int unsigned | 0 matches in app/ |
| `total_industrial` | int unsigned | 0 matches in app/ |
| `total_coastal` | int unsigned | 0 matches in app/ |
| `total_art` | int unsigned | 0 matches in app/ |
| `total_dogshit` | int unsigned | 0 matches in app/ |
| `total_verified` | int unsigned | 0 matches in app/ |
| `total_verified_litter` | int unsigned | 0 matches in app/ |
| `has_uploaded` | tinyint(1) | 0 matches in app/ |
| `has_uploaded_today` | tinyint(1) | 0 matches in app/ |
| `has_uploaded_counter` | int | 0 matches in app/ |
| `billing_id` | varchar | 0 matches in app/ |
| `role_id` | int unsigned | Only in `$hidden` and `$guarded` — Spatie Permission replaced it |
| `count_correctly_verified` | int unsigned | Only in `$fillable` — write-only, never read |
| `enable_admin_tagging` | tinyint(1) | Only in `$fillable` — write-only, never read |
| `link_instagram` | varchar | Only in `$fillable` — write-only, never read |
| `total_images` | int unsigned | Removed from `$fillable` (2026-03-14). No active reads — metrics/photo counts used instead |
| `total_litter` | int unsigned | 0 matches in app/ (never referenced on users table) |
| `images_remaining` | int | Only in `$fillable` + RegisterController (write-only) | Confirm RegisterController write is dead code |
| `verify_remaining` | int | Only in `$fillable` + RegisterController (write-only) | Confirm RegisterController write is dead code |
| `photos_per_month` | text | Only in GenerateTimeSeries command + `$fillable` | Confirm GenerateTimeSeries command is unused |

### Migration Cleanup

After dropping columns, also remove from User model:
- [ ] Remove dropped columns from `$fillable` array
- [ ] Remove `role_id` from `$hidden` and `$guarded` - double-check spatie roles & permissions is not broken
- [ ] Remove `billing_id` from any model references

### Indexes

All 8 indexes are justified — no drops recommended.

| Index | Columns | Status |
|-------|---------|--------|
| `PRIMARY` | id | Required |
| `users_email_unique` | email | Required (auth) |
| `users_username_unique` | username | Required (profile lookups) |
| `users_stripe_id_index` | stripe_id | Required (Cashier) |
| `users_active_team_foreign` | active_team | Required (FK) |
| `idx_users_xp` | xp | Used by rank fallback query |
| `idx_users_public_profile` | public_profile | Used by public profile lookups |
| `idx_users_created_at` | created_at | Used by new users stats query |

---

## Phase B — Post-Migration

No plan yet

---

## Active — Do Not Touch

| Column | Evidence |
|--------|----------|
| `verification_required` | AddTagsToPhotoAction, AdminUsersController |
| `remaining_teams` | CreateTeamAction, AssignSchoolManager, AdminUsersController |
| `phone` | UsersController, CSV export, BecomeAMerchantController, TagsConfig |
| `littercoin_allowance` | User model accessor, VerifyBoxController |
| `littercoin_owed` | User model accessor, VerifyBoxController |
| `littercoin_paid` | User model accessor, VerifyBoxController |
| `show_name_createdby` | LoadDataHelper, LocationHelper, ApiSettingsController |
| `show_username_createdby` | LoadDataHelper, LocationHelper, ApiSettingsController |
| `picked_up` | User default preference, UploadPhotoController, ProfileController |
| `previous_tags` | AddTagsToPhotoAction, ProfileController |
| `total_brands` | User model, PhotoTagsController |
| `eth_wallet` | varchar | Only in `$fillable` — write-only, never read |
| All standard columns | id, name, email, password, username, xp, level, avatar, etc. |
