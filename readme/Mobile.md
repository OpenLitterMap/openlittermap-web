# OpenLitterMap v5 — Mobile App

## Overview

The mobile app must use v3/v5 API endpoints exclusively. All legacy v1/v2/v4 endpoints and the `ConvertV4TagsAction` shim have been **removed** (2026-03-01). Old app users must reinstall with the updated version.

---

## Current State — v3 API Only

### Active endpoints for mobile

| Action | Method | Endpoint | Format |
|--------|--------|----------|--------|
| Auth (token) | `POST` | `/api/auth/token` | `{ identifier, password }` → `{ token, user, stats, level, rank, team }` |
| Validate token | `POST` | `/api/validate-token` | Bearer token → `{ message: "valid" }` |
| Upload photo | `POST` | `/api/v3/upload` | multipart; EXIF or explicit lat/lon/date |
| Add tags | `POST` | `/api/v3/tags` | CLO format (v5) |
| Replace tags | `PUT` | `/api/v3/tags` | CLO format (v5) |
| List photos | `GET` | `/api/v3/user/photos` | `?tagged=false&per_page=100` |
| Photo stats | `GET` | `/api/v3/user/photos/stats` | Aggregate counts |
| Delete photo | `POST` | `/api/profile/photos/delete` | `{ "photoid": 123 }` |
| Toggle photo visibility | `PATCH` | `/api/v3/photos/{id}/visibility` | `{ "is_public": bool }` → `{ "success": true, "is_public": bool }` |
| Update setting | `POST` | `/api/settings/update` | `{ "key": "public_photos", "value": bool }` → `{ "success": true }` |
| Tag catalog | `GET` | `/api/tags/all` | Returns full taxonomy for search index |
| Profile | `GET` | `/api/user/profile/index` | User stats, level, rank (also returned by auth/token, so not needed after login) |
| Global stats | `GET` | `/api/global/stats-data` | No auth; total tags/images/users |
| Levels | `GET` | `/api/levels` | No auth; XP thresholds and titles |

### Auth

Mobile uses Sanctum token auth. Login returns enriched profile data — no separate `GET /api/user/profile/index` call needed:

```
POST /api/auth/token  →  { token, user, stats, level, rank, team }
Authorization: Bearer <token>    // All subsequent requests
POST /api/validate-token  →  { message: "valid" }  // Only on app resume, NOT after fresh login
```

**Rate limit:** 10 attempts per minute.

`AuthTokenController` accepts `identifier`, `email`, or `username` field for backward compatibility.

**Lean response:** The auth/token response does NOT include `achievements`, `locations`, `global_stats`, or `stats.streak` — these are only returned by `GET /api/user/profile/index` for the SPA. See `readme/API.md` for exact response shape.

---

## Upload: Explicit Coordinates (Mobile Mode)

`POST /api/v3/upload` supports two modes:

- **Web (default):** Only `photo` field required. GPS + datetime extracted from EXIF.
- **Mobile:** Send `lat` + `lon` + `date` alongside `photo`. All three required for mobile mode. EXIF validation skipped. Platform set to `'mobile'`.

| Field | Type | Description |
|-------|------|-------------|
| `lat` | numeric | Latitude (-90 to 90) |
| `lon` | numeric | Longitude (-180 to 180) |
| `date` | string\|int | ISO 8601 string or **Unix timestamp in seconds** (NOT milliseconds) |
| `picked_up` | boolean | `true`=collected, `false`=left behind. Accepts `0`/`1` or `true`/`false`. Optional — uses user default if omitted. |
| `model` | string | Device model name (optional, max 255 chars) |

**Date field:** `Carbon::createFromTimestamp((int) $dateInput)` — expects **seconds**. If you have JS milliseconds, divide by 1000: `Math.floor(Date.now() / 1000)`.

Rejects `(0, 0)` coordinates. Duplicate detection (`user_id + datetime`) uses the explicit `date` field.

**HEIC is supported.** iPhone HEIC/HEIF uploads (including HEIC bytes sent with a `.jpg` extension) are accepted — detected by magic bytes, the `image`/`dimensions` validation is skipped, and the server converts HEIC → JPEG (`heif-convert`). Send the file as-is; no client-side transcoding needed.

**Response (new photo):** `{ "success": true, "photo_id": 123, ... }`

**Response (duplicate — idempotent, 200 not 422):**
`{ "success": true, "photo_id": <existing>, "already_uploaded": true, "tagged": <bool>, "xp_awarded": 0 }`

A re-upload of an already-uploaded photo returns the **existing** `photo_id` (no error, no second row, no extra XP) so a lost-response retry can recover. If `tagged` is `true`, skip tagging; otherwise tag via **`PUT /api/v3/tags`** (idempotent — `POST` appends and a retry would double-tag). This is the backend fix for the "photo id field must be an integer" loop.

## User Photos: Pagination & Filters

`GET /api/v3/user/photos` — configurable via `per_page` (default 8, max 100).

Fetch all untagged: `GET /api/v3/user/photos?tagged=false&per_page=100`

Response includes `picked_up` (`true`/`false`/`null` — null for untagged photos, derived from the first tag). The deprecated `remaining` field has been **removed** from responses — use `picked_up`.

## Delete Photo

`POST /api/profile/photos/delete` — body: `{ "photoid": 123 }` (lowercase, no underscore).

Response: `{ "message": "Photo deleted successfully!" }`

## Photo Visibility (`is_public`)

Photos can be public (visible on the global map) or private (hidden from map but metrics still count).

### User default setting

`users.public_photos` (boolean, default `true`) — applies to all new uploads unless overridden.

- **Read:** Returned in auth/token response and `GET /api/user/profile/index` as `user.public_photos`
- **Write:** `POST /api/settings/update` with `{ "key": "public_photos", "value": false }`

### Upload precedence

When uploading via `POST /api/v3/upload`, the optional `is_public` param controls visibility:

1. **School team** → always `false` (overridden by server, cannot be changed)
2. **Request `is_public` param** → used if provided
3. **`user.public_photos` default** → used if no param sent
4. **Fallback** → `true`

### Per-photo toggle

`PATCH /api/v3/photos/{id}/visibility` — toggles an individual photo's visibility after upload.

- **Request:** `{ "is_public": true|false }`
- **Response:** `{ "success": true, "is_public": true|false }`
- **403:** If photo belongs to a school team (teacher controls visibility)
- **403:** If not the photo owner

### Reading visibility state

`GET /api/v3/user/photos` returns per photo:
- `is_public` (boolean) — current visibility
- `school_team` (boolean) — if true, disable the toggle in UI (teacher-managed)

### Mobile UI recommendations

1. **Settings screen:** Toggle for "Photos public by default" (`public_photos`)
2. **Photo list:** Eye icon per photo — green if public, gray if private. Disabled if `school_team === true`
3. **Upload screen (optional):** Override toggle to set `is_public` per-upload

---

## `picked_up`

**`remaining` has been removed from API responses.** Use `picked_up` everywhere.

- **Photo-level** (`picked_up` in response root): `true`/`false`/`null`. Derived from the **first tag** — `null` for untagged photos (status unknown). `true` = litter collected, `false` = left behind.
- **Tag-level** (`new_tags[].picked_up`): nullable. `true`/`false`/`null` (not specified). Independent per tag, and the source of truth.
- The deprecated photo-level `remaining` field is no longer returned in any response (the column is hidden on the model).

---

## v5 Tag Format (CLO)

### Submission format (POST /api/v3/tags)

Materials and brands accept **both** simple ID arrays and object arrays:

```javascript
// Materials — simple IDs (quantity inherits from parent tag)
materials: [50, 51]
// OR object format
materials: [{ "id": 50 }, { "id": 51 }]

// Brands — simple IDs (quantity defaults to 1)
brands: [10]
// OR with per-brand quantity
brands: [{ "id": 10, "quantity": 3 }]

// Custom tags — always string arrays
custom_tags: ["dirty bench"]
```

### Building the search index from GET /api/tags/all

Returns 7 flat collections: `categories`, `objects`, `materials`, `brands`, `types`, `category_objects`, `category_object_types`.

The mobile app must join these to build a searchable index:

1. **Object entries:** For each object, loop through `category_objects` to get one entry per (object, category) pair with pre-resolved `cloId = category_objects[].id`
2. **Type entries:** Join `category_object_types` → `types` table. Each type entry references a parent CLO. Display as "{type.name} {object.key}" (e.g., "Beer Bottle")
3. **Standalone entries:** Brands and materials can be submitted alone (brand_only, material_only)

---

## Mobile App Refactor

### Current bugs and issues

- ☐ App sends deprecated string columns (country, county, city, display_name) — ignored by server
- ☐ Old tagging UI uses category-based layout matching old DB schema — needs v5 search/autocomplete
- ☐ No material tagging in mobile UI
- ☐ No custom tag support in mobile UI
- ☐ Result string display (deprecated — summary JSON replaces this)
- ☐ XP display may read `total_litter` or old columns
- ☐ No team support in mobile app
- ☐ No school team / safeguarding support
- ✅ Auth uses Sanctum tokens (`POST /api/auth/token`)

### Refactor phases

**Phase 1: v5 tagging UI** — Redesign tag input to match v5 schema (object search → optional materials → optional brands). Send v5 CLO format to `POST /api/v3/tags`.

**Phase 2: Bug fixes + polish** — Fix known bugs, update XP display, remove deprecated column reads.

**Phase 3: Teams + school support** — Team selection on upload, school team privacy, teacher approval flow.

---

## Removed Endpoints (2026-03-01)

The following legacy endpoints and their backing code have been deleted:

| Removed Route | Replacement |
|--------------|-------------|
| `POST /api/photos/submit` | `POST /api/v3/upload` |
| `POST /api/photos/submit-with-tags` | `POST /api/v3/upload` + `POST /api/v3/tags` |
| `POST /api/photos/upload-with-tags` | `POST /api/v3/upload` + `POST /api/v3/tags` |
| `POST /api/photos/upload/with-or-without-tags` | `POST /api/v3/upload` + `POST /api/v3/tags` |
| `DELETE /api/photos/delete` | `POST /api/profile/photos/delete` |
| `POST /api/add-tags` | `POST /api/v3/tags` |
| `POST /api/v2/add-tags-to-uploaded-image` | `POST /api/v3/tags` |
| `GET /api/v2/photos/get-untagged-uploads` | `GET /api/v3/user/photos?tagged=false` |
| `GET /api/v2/photos/web/index` | `GET /api/v3/user/photos?tagged=false` |
| `GET /api/v2/photos/web/load-more` | `GET /api/v3/user/photos?tagged=false` |
| `POST /api/upload` | `POST /api/v3/upload` |
| `GET /api/check-web-photos` | `GET /api/v3/user/photos?tagged=false` |
| `GET /api/user` (closure) | `GET /api/user/profile/index` |
| `GET /api/current-user` | `GET /api/user/profile/index` |

### Deleted files

| File | Was |
|------|-----|
| `app/Http/Controllers/ApiPhotosController.php` | Legacy mobile upload/delete |
| `app/Http/Controllers/API/AddTagsToUploadedImageController.php` | Legacy mobile tagging |
| `app/Http/Controllers/API/GetUntaggedUploadController.php` | Legacy untagged photos list |
| `app/Actions/Tags/ConvertV4TagsAction.php` | v4→v5 tag conversion shim |

---

## React Native v7 Changes

See `MOBILE_API_CHANGES.md` at the project root for the full RN v7 change log.

### Key notes

- **`filename` is a full URL.** The `filename` field in photo responses is a complete S3/CDN URL. Use it directly as an image source — do NOT prefix with a base URL.
- **Tag editing via `PUT /api/v3/tags`.** Same CLO format as `POST`. Accepts empty `tags: []` to clear all tags from a photo (resets to untagged state).
- **`new_tags` format.** Each tag includes `category`, `object`, `type`, `extra_tags`, and `picked_up` (bool). For loose/extra-tag-only tags, `category`, `object`, and `category_litter_object_id` may be null.
- **`picked_up` is cast to `(bool)`** with fallback to photo-level `picked_up`.

---

## Related Docs

- **API.md** — Full API reference (source of truth for all request/response contracts)
- **MOBILE_API_CHANGES.md** — React Native v7 API change log (project root)
- **Tags.md** — v5 tag hierarchy, summary structure, XP system
- **Upload.md** — Photo upload pipeline, MetricsService
- **Teams.md** — Teams architecture, school pipeline
