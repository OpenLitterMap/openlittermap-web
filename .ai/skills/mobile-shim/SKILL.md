---
name: mobile-shim
description: Mobile API surface (v3 only), removed legacy endpoints, and the v5-native tag format for mobile clients.
---

# Mobile API (v3 Only)

All legacy v1/v2/v4 mobile endpoints and the `ConvertV4TagsAction` shim have been **removed** (2026-03-01). The mobile app uses v3 endpoints exclusively.

## Key Files

- `app/Http/Controllers/Uploads/UploadPhotoController.php` — v3 upload (EXIF-based)
- `app/Http/Controllers/API/Tags/PhotoTagsController.php` — v3 tag CRUD (CLO format)
- `app/Http/Controllers/User/Photos/UsersUploadsController.php` — v3 user photos listing
- `app/Http/Controllers/Auth/AuthTokenController.php` — Mobile token login (Sanctum)
- `readme/Mobile.md` — Mobile documentation

## Active Mobile Endpoints

| Action | Method | Endpoint |
|--------|--------|----------|
| Auth (token) | `POST` | `/api/auth/token` |
| Validate token | `POST` | `/api/validate-token` |
| Upload photo | `POST` | `/api/v3/upload` |
| Add tags | `POST` | `/api/v3/tags` |
| Replace tags | `PUT` | `/api/v3/tags` |
| List photos | `GET` | `/api/v3/user/photos` |
| Photo stats | `GET` | `/api/v3/user/photos/stats` |
| Delete photo | `POST` | `/api/profile/photos/delete` (body: `{ "photoid": 123 }`) |
| Tag catalog | `GET` | `/api/tags/all` |
| Profile | `GET` | `/api/user/profile/index` |
| Global stats | `GET` | `/api/global/stats-data` (no auth) |
| Levels | `GET` | `/api/levels` (no auth) |

## Upload: Explicit Coordinates (Mobile Mode)

`POST /api/v3/upload` supports two modes:
- **Web (default):** Only `photo` required. GPS + datetime from EXIF.
- **Mobile:** Send `lat` + `lon` + `date` alongside `photo`. All three must be present. EXIF validation skipped. Platform set to `'mobile'`.

Optional fields: `picked_up` (boolean, overrides user default), `model` (string, device name).

Date accepts Unix timestamp (seconds) or ISO 8601 string. `(0, 0)` coordinates rejected.

## User Photos: Configurable Pagination

`GET /api/v3/user/photos?tagged=false&per_page=100` — fetches untagged photos. `per_page` default 8, max 100.

## Invariants

1. **v3 only.** No legacy endpoints exist. Mobile must use v5 CLO tag format.
2. **Sanctum token auth.** `POST /api/auth/token` returns a Bearer token. All subsequent requests include `Authorization: Bearer <token>`.
3. **`identifier` field for login.** `AuthTokenController` accepts `identifier`, `email`, or `username` for backward compatibility.
4. **CLO tag format.** Tags use `category_litter_object_id` + optional `litter_object_type_id`, not category/object string pairs.
5. **`picked_up` not `remaining`.** Photo responses include `picked_up` (boolean, never null) and `remaining` (deprecated, inverse). Per-tag `picked_up` in `new_tags[]` is nullable (true/false/null). The `remaining` column will be removed after the v5 migration script runs.
6. **Delete uses `photoid` (lowercase, no underscore).** `POST /api/profile/photos/delete` body: `{ "photoid": 123 }`. Response: `{ "message": "Photo deleted successfully!" }`.

## Tag Submission Format

```json
{
    "photo_id": 123,
    "tags": [
        {
            "category_litter_object_id": 42,
            "litter_object_type_id": 3,
            "quantity": 2,
            "picked_up": true,
            "materials": [{ "id": 1, "quantity": 1 }],
            "brands": [{ "id": 5, "quantity": 1 }]
        }
    ]
}
```

## Building Search Index

`GET /api/tags/all` returns 7 flat collections. Mobile must join them:
1. Object entries: `category_objects[].id` = `cloId`
2. Type entries: `category_object_types` → `types` for display names
3. Standalone: brands and materials can be submitted alone

## Removed (2026-03-01)

### Deleted endpoints
- `POST /api/photos/submit` (and all aliases)
- `POST /api/add-tags`
- `POST /api/v2/add-tags-to-uploaded-image`
- `GET /api/v2/photos/get-untagged-uploads`
- `GET /api/v2/photos/web/*`
- `DELETE /api/photos/delete`
- `POST /api/upload`
- `GET /api/user` (closure), `GET /api/current-user`

### Deleted code
- `app/Actions/Tags/ConvertV4TagsAction.php` — v4→v5 conversion shim (no longer needed)
- `app/Http/Controllers/ApiPhotosController.php` — legacy mobile upload/delete
- `app/Http/Controllers/API/AddTagsToUploadedImageController.php` — legacy mobile tagging
- `app/Http/Controllers/API/GetUntaggedUploadController.php` — legacy untagged photos list

## Common Mistakes

- **Sending v4 tag format.** `{ smoking: { butts: 3 } }` is no longer accepted. Use CLO format.
- **Using old upload endpoint.** `POST /api/photos/submit` no longer exists. Use `POST /api/v3/upload`.
- **Using old delete endpoint.** `DELETE /api/photos/delete` no longer exists. Use `POST /api/profile/photos/delete`.
- **Not building search index from `/api/tags/all`.** The 7 flat collections must be joined client-side.
