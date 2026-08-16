# Tag Suggestions (Quick Tags)

Server-side storage for user quick tag presets, synced between mobile devices and the web.

## Overview

Users can save up to 30 "quick tag" presets — pre-configured litter objects with quantity, picked_up, materials, and brands. The mobile app stores these locally via redux-persist; this backend provides durable cross-device sync via a simple bulk-replace API.

## Database

**Table:** `user_quick_tags`

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned | PK |
| user_id | int unsigned | FK → users.id (cascade delete) |
| clo_id | bigint unsigned | FK → category_litter_object.id (cascade delete) |
| type_id | int unsigned, nullable | References litter_object_types.id (no FK constraint) |
| custom_name | varchar(60), nullable | User-defined display name. NULL = use catalog name |
| quantity | tinyint unsigned | Default 1, validated 1-10 (trusted users: 1-100) |
| picked_up | boolean, nullable | null = inherit user default, true/false = explicit |
| materials | json | Array of material IDs, e.g. `[1, 3]` |
| brands | json | Array of `{"id": int, "quantity": int}` objects |
| sort_order | smallint unsigned | 0-indexed display order |
| timestamps | | created_at, updated_at |

**Constraints:**
- Max 30 rows per user (enforced in validation, not DB)
- Cascade delete on both `user_id` and `clo_id` FKs
- `type_id` validated against `litter_object_types` when non-null, but no FK (types rarely deleted)

## Key Files

- `app/Models/Users/UserQuickTag.php` — Eloquent model
- `app/Actions/QuickTags/SyncQuickTagsAction.php` — Transactional bulk-replace
- `app/Http/Controllers/API/QuickTagsController.php` — GET + PUT endpoints
- `app/Http/Requests/Api/SyncQuickTagsRequest.php` — Validation rules
- `app/Models/Users/User.php` — `quickTags()` HasMany relation
- `tests/Feature/QuickTags/QuickTagsApiTest.php` — 32 tests

## API Endpoints

Both routes are in the `v3` group with `auth:sanctum` middleware.

### `GET /api/v3/user/quick-tags`

Returns the authenticated user's quick tags, ordered by `sort_order`.

**Response 200:**
```json
{
    "success": true,
    "tags": [
        {
            "id": 1,
            "clo_id": 42,
            "type_id": null,
            "custom_name": "Coke bottle",
            "quantity": 2,
            "picked_up": true,
            "materials": [1, 3],
            "brands": [{"id": 5, "quantity": 1}],
            "sort_order": 0
        }
    ]
}
```

Hidden fields: `user_id`, `created_at`, `updated_at`.

### `PUT /api/v3/user/quick-tags`

Bulk-replaces all quick tags. Deletes existing rows and inserts new ones in a DB transaction.

**Request:**
```json
{
    "tags": [
        {
            "clo_id": 42,
            "type_id": null,
            "custom_name": "Coke bottle",
            "quantity": 2,
            "picked_up": true,
            "materials": [1, 3],
            "brands": [{"id": 5, "quantity": 1}]
        }
    ]
}
```

**Response 200:** Same format as GET (returns newly saved tags with server-assigned IDs).

**Response 422:** Validation error. Rejects the entire payload if any `clo_id` does not exist (`One or more of these tags is no longer available — refresh your tag list.`), names a retired object with no survivor, or has no existing approved survivor CLO in the submitted category. A retired+merged `clo_id` is remounted only onto an existing survivor CLO; API writes never create category/object relationships. The refusal runs inside the transaction, ahead of the delete, so existing presets are left untouched.

**Clearing all tags:** Send `"tags": []` — returns empty array, deletes all rows.

## Validation Rules

| Field | Rule |
|-------|------|
| tags | present, array, max 30 |
| tags.*.clo_id | required, integer, exists in category_litter_object |
| tags.*.type_id | nullable, integer, exists in litter_object_types |
| tags.*.custom_name | nullable, string, max 60 |
| tags.*.quantity | required, integer, 1-10 (trusted users: 1-100) |
| tags.*.picked_up | nullable, boolean |
| tags.*.materials | present, array (can be empty) |
| tags.*.materials.* | integer, exists in materials |
| tags.*.brands | present, array (can be empty) |
| tags.*.brands.*.id | required, integer, exists in brandslist |
| tags.*.brands.*.quantity | required, integer, 1-10 (trusted users: 1-100) |

## Sync Strategy (Mobile)

The backend is the durable source of truth. Mobile sync works as follows:

1. **On login:** Fetch from backend. If backend has tags and local is empty, use backend. If both have data, backend wins.
2. **On local change:** Debounce 2-3s, then PUT full array to backend.
3. **On conflict:** Backend wins. Local IDs are ephemeral — map server-assigned IDs back after sync.

## Architecture Notes

- **Bulk-replace pattern:** Every PUT deletes all existing rows and re-inserts. This avoids complex diffing and keeps the API idempotent.
- **Transaction safety:** Delete + insert wrapped in `DB::transaction()` — no partial states.
- **No Eloquent mass-assignment on insert:** Uses `UserQuickTag::insert()` for performance (single query for all rows). JSON fields are manually `json_encode()`d since `insert()` bypasses model casts.
- **Duplicate CLOs allowed:** A user can have multiple quick tags referencing the same CLO (e.g. same object with different quantities or picked_up settings).

---

## Open ticket — rename QuickTags to FavouriteTags

"Quick" describes how fast the tag is to apply, not what the thing is. These are a user's saved
favourites and the name should say so. Agreed 2026-08-15, not scheduled.

| What | From | To |
|---|---|---|
| Table | `user_quick_tags` | `user_favourite_tags` |
| Model | `UserQuickTag` | `UserFavouriteTag` |
| Relation | `User::quickTags()` | `User::favouriteTags()` |
| Action | `Actions\QuickTags\SyncQuickTagsAction` | `Actions\FavouriteTags\SyncFavouriteTagsAction` |
| Controller | `API\QuickTagsController` | `API\FavouriteTagsController` |
| Request | `SyncQuickTagsRequest` | `SyncFavouriteTagsRequest` |
| Routes | `/api/v3/user/quick-tags` | `/api/v3/user/favourite-tags` |
| Pinia store | `stores/quickTags.js` | `stores/favouriteTags.js` |
| Vue | `QuickTagsSection.vue`, plus hardcoded strings in `ProfileSettings.vue`, `OnboardingChips.vue`, `update27.vue` | |
| Tests | `tests/Feature/QuickTags/` | `tests/Feature/FavouriteTags/` |
| Docs | this file, `API.md`, `Mobile.md`, `Tags.md`, `CLAUDE.md` | |

26 files here, plus the mobile app repo.

**The route is the only hard part.** Shipped mobile builds call `/user/quick-tags` and cannot be
made to update, so either the old paths stay as aliases onto the new controller until the install
base turns over, or the rename stops at internal names and leaves the public route alone. Decide
that before starting.

**Order it after the tag retirements.** `olm:migrate-tag` reads and writes `user_quick_tags`
through raw `DB::table()` in ten places; renaming the table mid-retirement breaks the command.

No i18n keys are involved — the UI strings are hardcoded in the Vue components. Changelog entries
and `readme/audit/` CSVs are historical record; do not rewrite them.

**Next steps**

1. **Decide the route question first** — aliases onto the new controller, or internal names only
   and `/user/quick-tags` stays. Everything below is mechanical; this is the only judgement call,
   and it sets whether the mobile repo is in scope at all.
2. **Wait for the tag retirements to finish.** See the ordering note above.
3. **Then the rename in one pass** — `Schema::rename` migration, the 26 files, and the mobile repo
   if step 1 put it in scope. Web and mobile land together if the route moves.
