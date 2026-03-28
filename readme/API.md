# OpenLitterMap API

Base URL: `/api`

## Authentication

Most endpoints require a Bearer token (Sanctum) or active session.

- **Session auth (SPA):** `POST /api/auth/login` sets a session cookie
- **Token auth (Mobile):** `POST /api/auth/token` returns a Sanctum `token`

Include the token as: `Authorization: Bearer <token>`

All `auth:sanctum` routes accept both session cookies and Bearer tokens.

---

## Auth Endpoints

### POST /api/auth/token — Mobile Token Login

**Auth:** None (guest)
**Rate limit:** 10 attempts per minute

**Request:**
```json
{
  "identifier": "email_or_username",
  "password": "secret"
}
```

Backward compat: accepts `email` or `username` field if `identifier` is absent.
Priority: `identifier` > `email` > `username`.
Auto-detects email vs username via `filter_var()`.

**Response (200):** Returns enriched profile data so mobile can skip the separate `GET /api/user/profile/index` call. Same shape as register.
```json
{
  "token": "1|abcdef1234567890...",
  "user": {
    "id": 1, "name": null, "username": "johndoe", "email": "user@example.com",
    "avatar": "default.jpg", "created_at": "2020-01-15T10:30:00+00:00",
    "member_since": "January 2020", "global_flag": "us",
    "public_profile": true, "show_name": true, "show_username": true,
    "show_name_maps": true, "show_username_maps": true,
    "picked_up": false, "previous_tags": false, "emailsub": true,
    "prevent_others_tagging_my_photos": false, "public_photos": true
  },
  "stats": { "uploads": 42, "tags": 150, "xp": 5000, "littercoin": 250 },
  "level": {
    "level": 4, "title": "Litter Wizard", "xp": 5000,
    "xp_into_level": 0, "xp_for_next": 5000, "xp_remaining": 5000, "progress_percent": 0
  },
  "rank": { "global_position": 12, "global_total": 500, "percentile": 97.6 },
  "team": { "id": 5, "name": "Team A" }
}
```

**Note:** Mobile response is lean — does NOT include `achievements`, `locations`, `global_stats`, `stats.streak`, `stats.photo_percent`, or `stats.tag_percent`. These are only returned by `GET /api/user/profile/index` for the SPA dashboard.

**Error (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": { "identifier": ["The auth.failed message"] }
}
```

Previous tokens named `mobile` are revoked on each login (prevents token buildup).
Token is created with name `mobile`: `$user->createToken('mobile')`.

---

### POST /api/auth/login — Session Login (SPA)

**Auth:** None (guest)
**Rate limit:** 5 attempts per minute (disabled on localhost)

**Request:**
```json
{
  "identifier": "email_or_username",
  "password": "secret",
  "remember": true
}
```

**Response (200):**
```json
{
  "success": true,
  "user": { /* full user object */ }
}
```

Session is regenerated after login. `remember` sets 2-week persistent cookie.

---

### POST /api/auth/logout

**Auth:** Required (web session)
**Middleware:** `web`, `auth:web`

**Response (200):**
```json
{ "success": true }
```

---

### POST /api/auth/register

**Aliases:** `POST /api/register` (legacy mobile)
**Auth:** None (guest)

**Request:**
```json
{
  "email": "user@example.com",
  "password": "min8chars",
  "username": "optional_3to255_alphanum"
}
```

| Field | Rules |
|-------|-------|
| `email` | required, valid email, max 75, unique |
| `password` | required, min 8 chars |
| `username` | optional (auto-generated if omitted), 3-255 chars, regex `/^[a-zA-Z0-9_-]+$/`, unique |

**Response (200):** Same enriched shape as `POST /api/auth/token` (token + user + stats + level + rank + team). Stats will be zeroed for new users.
```json
{
  "token": "1|abcdef...",
  "user": { "id": 2, "username": "newuser", "email": "new@example.com", ... },
  "stats": { "uploads": 0, "tags": 0, "xp": 0, "littercoin": 0 },
  "level": { "level": 1, "title": "Noob", "progress_percent": 0, ... },
  "rank": { "global_position": 501, "global_total": 500, "percentile": 0 },
  "team": null
}
```

Side effects: welcome email (`WelcomeToOpenLitterMap`) sent, `Registered` and `UserSignedUp` events fired. `name` is always set to NULL regardless of input. Auto-generated usernames use pattern `{adjective}-{noun}-{number}` (e.g. `violently-enthusiastic-bin-overlord-5432`). Token created with name `mobile`.

---

### POST /api/validate-token — Check Token Validity

**Auth:** Required (Sanctum)

**Response (200):**
```json
{ "message": "valid" }
```

Returns 401 if token is invalid/expired.

---

### ~~GET /api/user~~ — REMOVED

> **Removed (2026-03-01).** Use `GET /api/user/profile/index` instead. Provided user model with position/XP; the profile endpoint provides the same data plus rank, level, achievements, locations — all from Redis.

### ~~GET /api/current-user~~ — REMOVED

> **Removed (2026-03-01).** Use `GET /api/user/profile/index` instead. Returned user + roles for SPA; the profile endpoint returns the same data.

---

### POST /api/password/email — Request Password Reset

**Auth:** None
**Rate limit:** 3 per minute

**Request:**
```json
{ "login": "email_or_username" }
```

**Response (200):** Always returns same message (prevents user enumeration):
```json
{ "message": "If an account with these details exists, we will send a password reset link." }
```

---

### POST /api/password/validate-token — Validate Reset Token

**Auth:** None

**Request:**
```json
{ "token": "reset_token", "email": "user@example.com" }
```

**Response:** `{ "valid": true }` (200) or `{ "valid": false }` (422).
Token is not consumed (safe to call multiple times). Tokens expire after 60 minutes.

---

### POST /api/password/reset — Complete Password Reset

**Auth:** None

**Request:**
```json
{
  "token": "reset_token",
  "email": "user@example.com",
  "password": "new_password",
  "password_confirmation": "new_password"
}
```

`password`: min 5 chars, confirmed.

**Response (200):**
```json
{
  "message": "Your password has been reset!",
  "user": { /* full user object */ }
}
```

User is auto-logged in after successful reset. Token is consumed (single-use).

---

### POST /api/settings/delete-account — Delete Account (GDPR)

**Auth:** Required (Sanctum)

**Request:**
```json
{ "password": "current_password" }
```

**Response (200):** `{ "success": true }`
**Error:** `{ "success": false, "msg": "password does not match" }`

Photos preserved as anonymous contributions (user_id set to NULL via DB CASCADE).
Cleans up: teams, metrics, Redis leaderboards, OAuth tokens, subscriptions, roles.

---

## Photo Upload

### POST /api/v3/upload — Web Photo Upload

**Auth:** Required (Sanctum)
**Content-Type:** multipart/form-data

**Request:**

| Field | Type | Rules |
|-------|------|-------|
| `photo` | file | required, jpg/png/jpeg/heif/heic/webp, max 20MB, min 1x1 |
| `lat` | numeric | optional, -90 to 90 (mobile: explicit latitude) |
| `lon` | numeric | optional, -180 to 180 (mobile: explicit longitude) |
| `date` | string/int | optional, ISO 8601 string or Unix timestamp **in seconds** (NOT milliseconds) |
| `picked_up` | boolean | optional, `true`=litter collected, `false`=left behind. Overrides user's default. Accepts `0`/`1` or `true`/`false`. |
| `model` | string | optional, device model (max 255 chars) |

**Two modes:**
- **Web (default):** Only `photo` required. GPS + datetime extracted from EXIF.
- **Mobile:** Send `lat` + `lon` + `date` alongside `photo`. All three must be present to use explicit mode. EXIF validation is skipped; coordinates come from the request fields. Platform is set to `'mobile'`.

Rejects `(0, 0)` coordinates when using explicit mode. Duplicate detection uses the explicit `date` field.

**Response (200):**
```json
{ "success": true, "photo_id": 12345 }
```

**Validation error response (422):**

`UploadPhotoRequest::failedValidation()` returns a structured error envelope (not Laravel's default `{ errors }` format):

```json
{
  "success": false,
  "error": "no_gps",
  "message": "Sorry, no GPS on this one.",
  "errors": { "photo": ["Sorry, no GPS on this one."] }
}
```

| `error` code | Condition |
|---|---|
| `no_exif` | EXIF data missing or unreadable |
| `no_datetime` | DateTime missing from EXIF |
| `duplicate` | Same user + same datetime already uploaded |
| `no_gps` | GPS data missing from EXIF (web mode) |
| `invalid_coordinates` | `(0, 0)` coordinates (mobile mode) |
| `validation_error` | Other validation failures (file type, size, etc.) |

**Note:** `PhotoTagsRequest` (POST/PUT `/api/v3/tags`) still uses Laravel's default validation response format (`{ message, errors }`), not the structured envelope above.

Side effects: S3 upload (full + bbox thumbnail), reverse geocoding via `ResolveLocationAction`, `ImageUploaded` broadcast event. No metrics/XP processing (happens at tagging time).

---

### ~~POST /api/photos/submit~~ — REMOVED

> **Removed (2026-03-01).** Use `POST /api/v3/upload` instead. Legacy mobile upload that accepted explicit lat/lon/date params. The v3 endpoint extracts coordinates from EXIF data.

### ~~POST /api/photos/submit-with-tags~~ — REMOVED

> **Removed (2026-03-01).** Use `POST /api/v3/upload` + `POST /api/v3/tags` instead. Legacy mobile upload+tag endpoint. Also removed aliases: `/api/photos/upload-with-tags`, `/api/photos/upload/with-or-without-tags`.

### ~~DELETE /api/photos/delete~~ — REMOVED

> **Removed (2026-03-01).** Use `POST /api/profile/photos/delete` instead. Legacy mobile delete endpoint.

### ~~GET /api/check-web-photos~~ — REMOVED

> **Removed (2026-03-01).** Use `GET /api/v3/user/photos?tagged=false` instead. Legacy endpoint to check for untagged photos.

---

## Tags

### GET /api/tags — Get Available Tags (Nested by Category)

**Auth:** None (public)

**Query params (all optional):**

| Param | Type | Description |
|-------|------|-------------|
| `category` | string | Filter by category key (e.g. `smoking`) |
| `object` | string | Filter by object key (partial match) |
| `materials` | string | Comma-separated material keys |
| `search` | string | Prefix search across all keys |

**Response (200):**
```json
{
  "tags": {
    "smoking": {
      "id": 1,
      "key": "smoking",
      "litter_objects": [
        {
          "id": 5,
          "key": "cigarette_butt",
          "materials": [
            { "id": 10, "key": "paper" }
          ]
        }
      ]
    }
  }
}
```

---

### GET /api/tags/all — Get All Tags (Flat Arrays)

**Auth:** None (public)

This is the primary endpoint for building a tag search UI. Returns 7 flat collections that the client must join locally to build a searchable index.

**Response (200):**
```json
{
  "categories": [
    { "id": 1, "key": "smoking" },
    { "id": 2, "key": "alcohol" },
    { "id": 3, "key": "soft_drinks" }
  ],
  "objects": [
    { "id": 5, "key": "cigarette_butt", "categories": [{ "id": 1, "key": "smoking" }] },
    { "id": 12, "key": "bottle", "categories": [{ "id": 2, "key": "alcohol" }, { "id": 3, "key": "soft_drinks" }] }
  ],
  "materials": [
    { "id": 10, "key": "plastic" },
    { "id": 11, "key": "glass" }
  ],
  "brands": [
    { "id": 1, "key": "coca_cola" },
    { "id": 2, "key": "marlboro" }
  ],
  "types": [
    { "id": 1, "key": "wine" },
    { "id": 2, "key": "beer" },
    { "id": 3, "key": "spirits" }
  ],
  "category_objects": [
    { "id": 42, "category_id": 2, "litter_object_id": 12 },
    { "id": 87, "category_id": 3, "litter_object_id": 12 }
  ],
  "category_object_types": [
    { "category_litter_object_id": 42, "litter_object_type_id": 1 },
    { "category_litter_object_id": 42, "litter_object_type_id": 2 },
    { "category_litter_object_id": 42, "litter_object_type_id": 3 }
  ]
}
```

**How to build a search index from this data:**

The 7 collections relate as follows:

```
categories ←──────── category_objects ────────→ objects
                         (CLO)                    ↑
                          ↑                       │
              category_object_types          objects.categories[]
                          ↓                  (same relationship,
                        types                 eager-loaded)
```

**Step 1: Build object entries.** Each object can belong to multiple categories. Create one searchable entry per (object, category) pair, pre-resolving the `cloId` from `category_objects`:

```
bottle (alcohol)     → cloId: 42   (from category_objects where category_id=2, litter_object_id=12)
bottle (soft_drinks) → cloId: 87   (from category_objects where category_id=3, litter_object_id=12)
cigarette_butt (smoking) → cloId: 15
```

**Step 2: Build type entries.** Types add specificity to objects. Join `category_object_types` → `types` → `category_objects` → `objects`:

```
wine   → cloId: 42, typeId: 1   (wine bottle under alcohol)
beer   → cloId: 42, typeId: 2   (beer bottle under alcohol)
spirits → cloId: 42, typeId: 3  (spirits bottle under alcohol)
```

When a user selects "wine", you submit `category_litter_object_id: 42, litter_object_type_id: 1`. This is how "bottle" becomes "wine bottle".

**Step 3: Brands and materials** are standalone — no joining needed.

**Important:** `category_object_types` has no `id` column — use composite key `(category_litter_object_id, litter_object_type_id)` for dedup.

---

### POST /api/v3/tags — Add Tags to Photo

**Auth:** Required (Sanctum)

**Request:**
```json
{
  "photo_id": 12345,
  "tags": [
    {
      "category_litter_object_id": 42,
      "litter_object_type_id": 1,
      "quantity": 2,
      "picked_up": true,
      "materials": [10, 11],
      "brands": [{ "id": 1, "quantity": 1 }],
      "custom_tags": ["found on bench"]
    }
  ]
}
```

| Field | Type | Rules | Notes |
|-------|------|-------|-------|
| `photo_id` | int | required | Must exist (not soft-deleted), owned by user |
| `tags` | array | required, min 1 | |
| `tags.*.category_litter_object_id` | int | required | FK to `category_litter_object` — resolved from `category_objects` in `/api/tags/all` |
| `tags.*.litter_object_type_id` | int/null | optional | FK to `litter_object_types` — this is what makes "bottle" → "wine bottle" |
| `tags.*.quantity` | int | required, min 1 | |
| `tags.*.picked_up` | bool/null | optional | |
| `tags.*.materials` | int[]\|object[] | optional | Plain IDs `[50, 51]` or objects `[{"id": 50}]`. Quantity inherits from parent tag. |
| `tags.*.brands` | int[]\|object[] | optional | Plain IDs `[10]` (qty=1) or objects `[{"id": 10, "quantity": 3}]` for per-brand quantity. |
| `tags.*.custom_tags` | string[] | optional | Free-text tags, max 100 chars each |

**Standalone tag types** (no `category_litter_object_id`):

```json
{
  "tags": [
    { "brand_only": true, "brand": { "id": 1, "key": "coca_cola" }, "quantity": 1, "picked_up": true },
    { "material_only": true, "material": { "id": 10, "key": "plastic" }, "quantity": 1, "picked_up": true },
    { "custom": true, "key": "broken_glass", "quantity": 1, "picked_up": true }
  ]
}
```

**Gates:**
- 403 if user doesn't own photo
- 403 if photo already verified (`verified >= 1`)

**Response (200):**
```json
{
  "success": true,
  "photoTags": [{ "id": 1, "photo_id": 12345, "category_litter_object_id": 42, "litter_object_type_id": 1, ... }]
}
```

Category is auto-resolved from `category_litter_object_id`. Generates summary, calculates XP, triggers metrics processing via `TagsVerifiedByAdmin` event if user is trusted.

---

### PUT /api/v3/tags — Replace All Tags on Photo

**Auth:** Required (Sanctum)

Same request format as POST. Key differences:
- **No verification gate** — allows re-tagging verified photos
- Deletes all existing tags + extras first
- Resets summary, XP, and verified status to 0
- Re-runs full tag pipeline (summary + XP + metrics delta)
- **Accepts empty `tags: []`** to clear all tags from a photo. Validation uses `present|array` (not `required|array|min:1`). With empty tags, photo resets to untagged state (null summary, 0 XP, verified=0).

For loose/extra-tag-only tags, `category`, `object`, and `category_litter_object_id` fields in the `new_tags` response may be null.

---

### ~~POST /api/add-tags~~ — REMOVED

> **Removed (2026-03-01).** Use `POST /api/v3/tags` with CLO format instead. This was the legacy mobile tagging endpoint that accepted v4 format (`{ smoking: { butts: 3 } }`). The v5 endpoint supports object types, materials, and brands.

---

## User Profile

### GET /api/user/profile/index — Authenticated Profile

**Auth:** Required (Sanctum)

**Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "John",
    "username": "johndoe",
    "email": "john@example.com",
    "avatar": "https://...",
    "created_at": "2020-01-15T10:30:00Z",
    "member_since": "January 2020",
    "global_flag": "us",
    "public_profile": true,
    "show_name": true,
    "show_username": true,
    "show_name_maps": true,
    "show_username_maps": true,
    "previous_tags": true,
    "emailsub": true
  },
  "stats": {
    "uploads": 100,
    "tags": 450,
    "xp": 5000,
    "streak": 7,
    "littercoin": 250,
    "photo_percent": 0.5,
    "tag_percent": 0.8
  },
  "level": {
    "level": 3,
    "title": "Litter Wizard",
    "xp": 5000,
    "xp_into_level": 0,
    "xp_for_next": 5000,
    "xp_remaining": 0,
    "progress_percent": 100
  },
  "rank": {
    "global_position": 42,
    "global_total": 500,
    "percentile": 91.6
  },
  "locations": { "countries": 5, "states": 12, "cities": 45 },
  "team": { "id": 5, "name": "Team A" }
}
```

Stats from `ResolvesUserProfile` trait — metrics table + Redis with MySQL fallback. SPA response adds `streak`, `photo_percent`, `tag_percent`, and `locations` on top of the lean core profile. `achievements` and `global_stats` removed (unused by frontend). `team` is null if no active team.

---

### GET /api/user/profile/refresh — Lightweight User Refresh

**Auth:** Required (Sanctum)

**Response (200):**
```json
{
  "user": {
    "id": 1,
    "name": "John",
    "username": "johndoe",
    "email": "john@example.com",
    "avatar": "https://...",
    "global_flag": "us",
    "picked_up": true,
    "previous_tags": true,
    "public_photos": true
  },
  "stats": { "xp": 5000 },
  "level": {
    "level": 3,
    "title": "Litter Wizard",
    "xp": 5000,
    "xp_into_level": 0,
    "xp_for_next": 5000,
    "xp_remaining": 0,
    "progress_percent": 100
  }
}
```

Lightweight endpoint for `REFRESH_USER()` — called on app load and after uploads/tagging. Returns only user fields, XP, and level. No streak calculation, no Redis stats, no rank/achievements/locations/global stats.

---

### GET /api/user/profile/{id} — Public Profile

**Auth:** None (public)

**Path Parameters:**

| Parameter | Type | Description   |
|-----------|------|---------------|
| `id`      | int  | The user's ID |

**Response (public profile):**

```json
{
  "public": true,
  "user": {
    "id": 42,
    "name": "Sean",
    "username": "seanlynch",
    "avatar": null,
    "global_flag": "ie",
    "member_since": "January 2020"
  },
  "stats": {
    "uploads": 500,
    "litter": 2000,
    "xp": 15000
  },
  "level": {
    "level": 7,
    "title": "Trashmonster",
    "xp": 15000,
    "xp_into_level": 0,
    "xp_for_next": 35000,
    "xp_remaining": 35000,
    "progress_percent": 0
  },
  "rank": {
    "global_position": 5,
    "global_total": 1457,
    "percentile": 99.7
  },
  "achievements": {
    "unlocked": 12,
    "total": 50
  },
  "locations": {
    "countries": 3,
    "states": 8,
    "cities": 15
  }
}
```

**Response (private profile):**

```json
{
  "public": false
}
```

**Notes:**
- `name` and `username` respect the user's privacy settings (`show_name`, `show_username`). They return `null` when hidden.
- Returns `404` if the user ID does not exist.

---

### GET /api/user/profile/map — User's Photo GeoJSON

**Auth:** Required (Sanctum)

**Query params (optional):**

| Param | Type | Description |
|-------|------|-------------|
| `period` | string | `created_at`, `datetime`, `updated_at` |
| `start` | string | YYYY-MM-DD |
| `end` | string | YYYY-MM-DD |

**Response:** GeoJSON FeatureCollection. Only includes `verified >= 2` (ADMIN_APPROVED). Coordinates as `[lat, lon]`. Respects `show_name_maps` and `show_username_maps` privacy settings.

---

### POST /api/user/profile/download — Request Data Export

**Auth:** Required (Sanctum)

**Query params (optional):**

| Param | Type | Description |
|-------|------|-------------|
| `dateField` | string | `created_at`, `datetime`, `updated_at` |
| `fromDate` | string | YYYY-MM-DD (default: 2017) |
| `toDate` | string | YYYY-MM-DD (default: now) |

**Response:** `{ "success": true }`

Queues CSV export, emails S3 download link when ready.

---

## User Photos

### GET /api/v3/user/photos — User's Photos (Paginated + Filterable)

**Auth:** Required (Sanctum)

**Query params (all optional):**

| Param | Type | Description |
|-------|------|-------------|
| `tagged` | bool | `true` = has summary (tagged), `false` = no summary (untagged). Uses `whereNull('summary')` |
| `picked_up` | bool | `true` = picked up only, `false` = not picked up only |
| `id` | int | Filter by photo ID |
| `id_operator` | string | Comparison operator: `=`, `>`, or `<` (default `=`) |
| `tag` | string | Filter by litter object key (LIKE search) |
| `custom_tag` | string | Filter by custom tag key (LIKE search) |
| `date_from` | string | Start date (ISO) |
| `date_to` | string | End date (ISO) |
| `per_page` | integer | Results per page (default 8, max 100) |

Pagination: Configurable via `per_page` (default 8, max 100), ordered by `created_at` DESC.

**Response (200):**
```json
{
  "photos": [{
    "id": 123,
    "filename": "https://...",
    "datetime": "2020-01-15T10:30:00Z",
    "lat": 40.7128,
    "lon": -74.0060,
    "model": "iPhone 12",
    "picked_up": false,
    "remaining": true,
    "team": { "id": 5, "name": "Team A" },
    "new_tags": [{
      "id": 1,
      "category_litter_object_id": "smoking_cigarette",
      "quantity": 3,
      "picked_up": true,
      "category": { "id": 1, "key": "smoking" },
      "object": { "id": 2, "key": "cigarette" },
      "extra_tags": [
        { "type": "brand", "quantity": 3, "tag": { "id": 10, "key": "marlboro" } },
        { "type": "material", "quantity": 3, "tag": { "id": 50, "key": "paper" } }
      ]
    }],
    "summary": ["cigarette"],
    "xp": 12,
    "total_tags": 1
  }],
  "pagination": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 8,
    "total": 40
  },
  "user": { "id": 1, "name": "...", "email": "..." }
}
```

Tags are under the `new_tags` key (v5 format with nested category/object/extra_tags). For loose/extra-tag-only tags, `category`, `object`, and `category_litter_object_id` may be null. `filename` is a full URL, usable directly as image source.

**`picked_up` vs `remaining`:** Both are returned for backward compatibility. `picked_up` is the preferred field — `remaining` is its inverse and deprecated. Per-tag `picked_up` (inside `new_tags`) is cast to `(bool)` with fallback to photo-level `picked_up`, and is independent and nullable (true/false/null).

**Additional fields per photo:** `is_public` (boolean — whether the photo is currently visible on the public map) and `school_team` (boolean — whether the photo belongs to a school team, used to gate the per-photo visibility toggle in the UI).

---

### PATCH /api/v3/photos/{id}/visibility — Toggle Per-Photo Visibility

**Auth:** `auth:sanctum` (photo owner only)

**Request:**
```json
{ "is_public": true }
```

**Response (200):**
```json
{ "success": true, "is_public": true }
```

**Errors:**
- `403` — Not the photo owner, or the photo belongs to a school team (school visibility is controlled by teacher approval, not per-photo toggle)
- `404` — Photo not found

Toggling `is_public` on a verified (`>= ADMIN_APPROVED`) photo marks its cluster tile dirty so map counts stay accurate. Metrics (XP, leaderboard) are unaffected — private-by-choice photos receive full metrics.

---

### GET /api/v3/user/photos/stats — Upload Statistics

**Auth:** Required (Sanctum)

**Response (200):**
```json
{
  "totalPhotos": 100,
  "totalTags": 450,
  "leftToTag": 10,
  "taggedPercentage": 90
}
```

---

### POST /api/user/profile/photos/delete — Bulk Delete Photos

**Auth:** Required (Sanctum)

**Request:**
```json
{
  "selectAll": false,
  "inclIds": [123, 124, 125],
  "exclIds": [],
  "filters": "{}"
}
```

Reverses metrics, removes S3 files, soft-deletes. Only deletes user's own photos.

---

### POST /api/profile/photos/delete — Delete Single Photo

**Auth:** Required (Sanctum)

**Request:**
```json
{ "photoid": 123 }
```

**Important:** The parameter is `photoid` (all lowercase, no underscore or camelCase).

**Response (200):** `{ "message": "Photo deleted successfully!" }`
**Error:** 403 if photo not owned by user. 404 if `photoid` missing or invalid.

Reverses metrics, removes S3 files, soft-deletes, decrements user XP and total_images.

---

### GET /api/user/profile/photos/index — Unverified Photos (Legacy)

**Auth:** Required (Sanctum)

Paginated list of user's unverified photos (`verified = 0`), 300 per page, ordered by `created_at` DESC.

**Response (200):**
```json
{
  "paginate": { /* Laravel paginator */ },
  "count": 50
}
```

---

### GET /api/user/profile/photos/filter — Filter User's Photos (Legacy)

**Auth:** Required (Sanctum)

**Query params:** `filters` (JSON string), `selectAll` (bool), `inclIds` (array), `exclIds` (array)

**Response (200):**
```json
{
  "count": 50,
  "paginate": { /* Laravel paginator */ }
}
```

---

### GET /api/user/profile/photos/previous-custom-tags — Previous Custom Tags

**Auth:** Required (Sanctum)

**Response (200):**
```json
["found on bench", "near park entrance"]
```

---

## Settings

### POST /api/settings/details — Update Name/Email/Username

**Auth:** Required (Sanctum)

**Request:**
```json
{ "name": "John", "email": "john@example.com", "username": "johndoe" }
```

| Field | Rules |
|-------|-------|
| `name` | min 3, max 25 |
| `email` | required, email, max 75, unique |
| `username` | required, min 3, max 75, unique |

**Response:** `{ "message": "success", "email_changed": false }`

---

### PATCH /api/settings/details/password — Change Password

**Auth:** Required (Sanctum)

**Request:**
```json
{
  "oldpassword": "current",
  "password": "new_password",
  "password_confirmation": "new_password"
}
```

`password`: min 5, confirmed.

**Response:** `{ "message": "success" }` or `{ "message": "fail" }` (wrong old password)

---

### POST /api/settings/update — Update Setting by Key/Value

**Auth:** Required (Sanctum)

**Request:**
```json
{ "key": "username", "value": "new_value" }
```

**Allowed keys and rules:**

| Key | Rules | Notes |
|-----|-------|-------|
| `name` | string, min 3, max 25 | |
| `username` | string, min 3, max 75 | unique validation |
| `email` | email, max 75 | unique validation |
| `global_flag` | nullable, string, max 10 | ISO country code |
| `picked_up` | boolean | User's default "picked up" preference |
| `previous_tags` | boolean | |
| `emailsub` | boolean | |
| `public_profile` | boolean | |

Legacy mobile: `items_remaining` key remaps to `picked_up` (inverted value).

**Response:** `{ "success": true }` or `{ "success": false, "msg": "..." }`

---

### POST /api/settings/privacy/update — Update All Privacy Flags

**Auth:** Required (Sanctum)

**Request:**
```json
{
  "show_name": true,
  "show_username": false,
  "show_name_maps": true,
  "show_username_maps": false,
  "show_name_createdby": true,
  "show_username_createdby": false,
  "prevent_others_tagging_my_photos": false
}
```

---

### Privacy Toggle Endpoints

All POST, auth required. Each toggles a single boolean and returns the new value.

| Endpoint | Toggles | Response key |
|----------|---------|-------------|
| `/api/settings/privacy/maps/name` | show_name_maps | `show_name_maps` |
| `/api/settings/privacy/maps/username` | show_username_maps | `show_username_maps` |
| `/api/settings/privacy/leaderboard/name` | show_name | `show_name` |
| `/api/settings/privacy/leaderboard/username` | show_username | `show_username` |
| `/api/settings/privacy/createdby/name` | show_name_createdby | `show_name_createdby` |
| `/api/settings/privacy/createdby/username` | show_username_createdby | `show_username_createdby` |
| `/api/settings/privacy/toggle-previous-tags` | previous_tags | `previous_tags` |
| `/api/settings/email/toggle` | emailsub | `sub` |

---

### PATCH /api/settings — Update Social Links

**Auth:** Required (Sanctum)

**Request (all optional, must be valid URLs):**
```json
{
  "social_twitter": "https://twitter.com/user",
  "social_facebook": "https://facebook.com/user",
  "social_instagram": "https://instagram.com/user",
  "social_linkedin": "https://linkedin.com/in/user",
  "social_reddit": "https://reddit.com/u/user",
  "social_personal": "https://example.com"
}
```

**Response:** `{ "message": "success" }`

---

### POST /api/settings/save-flag — Set Country Flag

**Auth:** Required (Sanctum)

**Request:** `{ "country": "us" }`
**Response:** `{ "message": "success" }`

---

### GET /api/settings/flags/countries — Available Flag Countries

**Auth:** None (public)

**Response:** Key-value pairs of `shortcode` -> `country name`.

---

### POST /api/settings/phone/submit — Set Phone Number

**Auth:** Required (Sanctum)

**Request:** `{ "phonenumber": "+1234567890" }`

---

### POST /api/settings/phone/remove — Remove Phone Number

**Auth:** Required (Sanctum)

**Response:** `{ "message": "success" }`

---

## Leaderboard

### GET /api/leaderboard

Returns ranked users by XP. Public endpoint (no auth required). Authenticated users also receive `currentUserRank`; unauthenticated users receive `currentUserRank: null`.

**Query Parameters:**

| Parameter      | Type   | Default    | Description                                                                 |
|----------------|--------|------------|-----------------------------------------------------------------------------|
| `timeFilter`   | string | `all-time` | One of: `all-time`, `today`, `yesterday`, `this-month`, `last-month`, `this-year`, `last-year` |
| `locationType` | string | —          | Filter by location scope: `country`, `state`, `city`. Must be paired with `locationId`. |
| `locationId`   | int    | —          | ID of the location to filter by. Must be paired with `locationType`.        |
| `page`         | int    | `1`        | Page number (100 results per page).                                         |

**Response:**

```json
{
  "success": true,
  "users": [
    {
      "user_id": 42,
      "public_profile": true,
      "name": "Sean",
      "username": "@seanlynch",
      "xp": "1,234",
      "global_flag": "ie",
      "social": { "twitter": "https://twitter.com/..." },
      "team": "CleanCoast",
      "rank": 1
    }
  ],
  "hasNextPage": false,
  "total": 150,
  "activeUsers": 450,
  "totalUsers": 1000,
  "currentUserRank": 42
}
```

**User object fields:**

| Field            | Type        | Description                                                     |
|------------------|-------------|-----------------------------------------------------------------|
| `user_id`        | int         | User ID. Use to link to public profile.                         |
| `public_profile` | bool        | Whether the user's profile is publicly viewable.                |
| `name`           | string      | Display name (empty string if user hides name on leaderboards). |
| `username`       | string      | `@username` (empty string if user hides username).              |
| `xp`             | string      | Formatted XP with commas (e.g. `"1,234"`).                     |
| `global_flag`    | string/null | ISO country code for flag display (e.g. `"ie"`, `"gb"`).       |
| `social`         | object/null | Social links keyed by type (`twitter`, `facebook`, `personal`). |
| `team`           | string      | Active team name (empty string if none or hidden).              |
| `rank`           | int         | Position in the leaderboard (1-indexed).                        |

**Time filters explained:**

| Filter       | Description                        |
|--------------|------------------------------------|
| `all-time`   | Cumulative XP across all time      |
| `today`      | XP earned today (UTC)              |
| `yesterday`  | XP earned yesterday (UTC)          |
| `this-month` | XP earned in the current month     |
| `last-month` | XP earned in the previous month    |
| `this-year`  | XP earned in the current year      |
| `last-year`  | XP earned in the previous year     |

**Error responses:**

- Missing one of `locationType`/`locationId`: `{ "success": false, "msg": "Both locationType and locationId required for location filtering" }`
- Invalid `locationType`: `{ "success": false, "msg": "Invalid locationType" }`
- Invalid `timeFilter`: `{ "success": false, "msg": "Invalid time filter" }`

Filters on `xp > 0`. Users with `public_profile=true` have clickable profiles at `/profile/{user_id}`.

---

## Achievements

### GET /api/achievements

**Auth:** Required (Sanctum)

**Response (200):**
```json
{
  "overview": {
    "uploads": {
      "progress": 42,
      "next_threshold": 50,
      "percentage": 84,
      "unlocked": [
        { "id": 1, "threshold": 10, "metadata": { "name": "First Steps", "icon": "rocket" } }
      ],
      "next": { "id": 2, "threshold": 50, "percentage": 84 }
    },
    "streak": { "..." : "..." },
    "total_categories": { "..." : "..." },
    "total_objects": { "..." : "..." }
  },
  "categories": [{
    "id": 1,
    "key": "smoking",
    "name": "Smoking",
    "achievement": { "..." : "..." },
    "objects": [{
      "id": 1,
      "key": "cigarette",
      "name": "Cigarette",
      "achievement": { "..." : "..." }
    }]
  }],
  "summary": { "total": 150, "unlocked": 42, "percentage": 28 }
}
```

Hierarchical: overview > categories > objects. Sorted by progress (highest first).

---

## Global Map

### GET /api/points — Map Points (GeoJSON)

**Auth:** None (public)

**Query params:**

| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `bbox` | object | required | `{left, bottom, right, top}` bounding box |
| `zoom` | int | required | Zoom level (0-22) |
| `page` | int | 1 | Page number |
| `per_page` | int | 1000 | Results per page (max 500) |
| `categories` | array | — | Category keys to filter |
| `litter_objects` | array | — | Object keys to filter |
| `materials` | array | — | Material keys to filter |
| `brands` | array | — | Brand keys to filter |
| `custom_tags` | array | — | Custom tag keys to filter |
| `from` | string | — | Start date (YYYY-MM-DD) |
| `to` | string | — | End date (YYYY-MM-DD) |
| `year` | int | — | Filter by year (overrides from/to) |
| `username` | string | — | Filter by username |

**Response (200):** GeoJSON FeatureCollection
```json
{
  "type": "FeatureCollection",
  "features": [{
    "type": "Feature",
    "geometry": { "type": "Point", "coordinates": [-74.006, 40.713] },
    "properties": {
      "id": 123,
      "datetime": "2025-02-28T10:30:00Z",
      "verified": 2,
      "picked_up": false,
      "summary": { "..." : "..." },
      "filename": "photo.jpg",
      "username": "johndoe",
      "name": "John",
      "team": "Team A",
      "social": null
    }
  }],
  "page": 1,
  "last_page": 10,
  "per_page": 1000,
  "total": 9500,
  "has_more_pages": true,
  "meta": {
    "bbox": [-74.006, 40.713, -74.005, 40.714],
    "zoom": 12,
    "generated_at": "2025-02-28T16:30:00Z"
  }
}
```

Only `is_public = true` photos. Masks identity for safeguarded teams. `filename` shown only if `verified >= 2`. Caches non-username-filtered requests for 2 minutes.

---

### GET /api/points/{id} — Single Photo Point

**Auth:** None (public)

Returns single photo data. Used as fallback when photo isn't in current GeoJSON page.

**Response (200):**
```json
{
  "id": 123,
  "lat": 40.7128,
  "lon": -74.0060,
  "datetime": "2025-02-28T10:30:00Z",
  "verified": 2,
  "filename": "photo.jpg",
  "username": "johndoe",
  "name": "John",
  "social": null,
  "flag": "ie",
  "team": "Team A",
  "summary": { ... }
}
```

Identity fields (`username`, `name`, `social`, `flag`) are `null` when team has safeguarding enabled. Privacy settings (`show_name_maps`, `show_username_maps`) are respected.

---

### GET /api/points/stats — Map Stats for Viewport

**Auth:** None (public)

Same query params as `/api/points`.

**Response (200):**
```json
{
  "data": {
    "photos": 150,
    "tags": 500,
    "categories": 8,
    "objects": 25,
    "brands": 12
  },
  "meta": {
    "bbox": [-74.006, 40.713, -74.005, 40.714],
    "zoom": 12,
    "categories": null,
    "litter_objects": null,
    "materials": null,
    "brands": null,
    "custom_tags": null,
    "from": null,
    "to": null,
    "username": null,
    "year": null,
    "generated_at": "2025-02-28T16:30:00Z",
    "cached": false
  }
}
```

---

### GET /api/clusters — Map Clusters (GeoJSON)

**Auth:** None (public)

**Query params:**

| Param | Type | Description |
|-------|------|-------------|
| `zoom` | numeric | Snapped to nearest configured zoom level |
| `bbox` | array/string | `bbox[left]`, `bbox[bottom]`, `bbox[right]`, `bbox[top]` — or comma-separated string `-180,-90,180,90` |
| `lat`, `lon` | numeric | Optional, creates bbox if no bbox provided |

**Response:** GeoJSON FeatureCollection with cluster points containing `properties.count`.

Supports ETag-based caching (`If-None-Match` header returns 304 if unchanged). Response includes `Cache-Control`, `ETag`, and `X-Cluster-Zoom` headers.

---

### GET /api/clusters/zoom-levels — Available Cluster Zoom Levels

**Auth:** None (public)

**Response (200):**
```json
{
  "zoom_levels": [2, 4, 6, 8, 10, 12],
  "global_zooms": [2, 4, 6],
  "tile_zooms": [8, 10, 12]
}
```

---

### GET /api/global/stats-data — Global Statistics

**Auth:** None (public)

World totals from the metrics table (all-time, timescale=0, location_type=Global). User growth stats from `users.created_at`. Tag and photo growth stats from daily metric buckets (timescale=1).

**Response (200):**
```json
{
  "total_tags": 150000,
  "total_images": 50000,
  "total_users": 10000,
  "new_users_today": 12,
  "new_users_last_7_days": 85,
  "new_users_last_30_days": 320,
  "new_tags_today": 156,
  "new_tags_last_7_days": 1230,
  "new_tags_last_30_days": 4870,
  "new_photos_today": 45,
  "new_photos_last_7_days": 310,
  "new_photos_last_30_days": 1250
}
```

**Controller:** `App\Http\Controllers\API\GlobalStatsController@index`
**Test:** `tests/Feature/Api/GlobalStatsTest.php`

---

### GET /api/levels — Level Thresholds

**Auth:** None (public)

Returns the XP threshold config for all levels. Used by mobile to render level progression UI.

**Response (200):**
```json
{
  "0": { "title": "Complete Noob" },
  "100": { "title": "Still A Noob" },
  "500": { "title": "Post-Noob" },
  "1000": { "title": "Litter Wizard" },
  ...
}
```

**Test:** `tests/Feature/Api/LevelsEndpointTest.php`

---

## Locations

### GET /api/locations/global — Global Stats + Country List

**Auth:** None (public)

---

### GET /api/locations/{type} — List Locations by Type

**Auth:** None (public)
**Types:** `country`, `state`, `city`

**Query params (optional):**

| Param | Type | Description |
|-------|------|-------------|
| `period` | string | `today`, `yesterday`, `this_month`, `last_month`, `this_year` |
| `year` | int | Custom year (2015-current) |
| `month` | int | Custom month 1-12 (requires year) |

**Response (200):**
```json
{
  "stats": {
    "photos": 50000,
    "tags": 150000,
    "xp": 2500000,
    "contributors": 5000,
    "countries": 110,
    "total_users": 10000
  },
  "activity": {
    "today": { "photos": 150, "tags": 500, "xp": 15000 },
    "this_month": { "photos": 3000, "tags": 10000, "xp": 300000 }
  },
  "locations": [{
    "id": 1,
    "name": "United States",
    "shortcode": "US",
    "total_images": 20000,
    "total_tags": 60000,
    "xp": 1000000,
    "total_members": 2000,
    "pct_tags": 40.0,
    "pct_photos": 40.0,
    "avg_tags_per_person": 30.0,
    "avg_photos_per_person": 10.0,
    "created_at": "2015-01-01 00:00:00",
    "updated_at": "2025-02-28 10:30:00",
    "created_by": "John Doe",
    "last_updated_at": "2025-02-28 10:30:00",
    "last_updated_by": "Jane Smith"
  }],
  "location_type": "country",
  "breadcrumbs": [{ "name": "World", "type": "global", "id": null }]
}
```

Response keys are `locations` and `location_type` (not `children`/`children_type`).

---

### GET /api/locations/{type}/{id} — Location Detail + Children

Same query params as index. Returns `location`, `stats`, `meta`, `activity`, `locations` (children), `location_type`, `breadcrumbs`.

---

### GET /api/locations/{type}/{id}/categories — Location Category Breakdown

### GET /api/locations/{type}/{id}/timeseries — Location Time Series

### GET /api/locations/{type}/{id}/leaderboard — Location Leaderboard

### Location Tag Endpoints (all under `/api/locations/{type}/{id}/tags/`)

| Endpoint | Description |
|----------|-------------|
| `/top` | Top tags at this location |
| `/summary` | Tag summary |
| `/by-category` | Tags grouped by category |
| `/cleanup` | Cleanup data |
| `/trending` | Trending tags |

---

### Legacy: GET /api/v1/locations/{type}/{id}

Same as above, constrained to `country|state|city` types and numeric IDs.

---

## Teams

### GET /api/teams/types — Team Types (Public, no auth)

**Response:** `{ "success": true, "types": [{ "id": 1, "team": "School" }, ...] }`

Returns team types ordered by `id` descending.

---

### POST /api/teams/create — Create Team

**Auth:** Required (Sanctum)

Creates a new team. The user must have `remaining_teams > 0`. School teams require the `school_manager` role.

**Request (community team):**
```json
{
  "name": "Cork Litter Pickers",
  "identifier": "CorkLP2026",
  "teamType": 1
}
```

**Request (school team — requires `school_manager` role):**
```json
{
  "name": "St Mary's 5th Class",
  "identifier": "StMarys2026",
  "teamType": 2,
  "contact_email": "teacher@school.ie",
  "county": "Cork",
  "academic_year": "2025/2026",
  "class_group": "5th Class",
  "participant_sessions_enabled": true,
  "max_participants": 30,
  "logo": "(file upload, image, max 2MB)"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | Yes | 3-100 chars, unique |
| `identifier` | string | Yes | 3-100 chars, unique (join code) |
| `teamType` | int | Yes | Must match `team_types.id` (get from `GET /api/teams/types`) |
| `contact_email` | email | School only | Required for school teams |
| `county` | string | School only | Required for school teams |
| `academic_year` | string | School only | Optional, max 20 chars |
| `class_group` | string | School only | Optional, max 100 chars |
| `participant_sessions_enabled` | boolean | School only | Optional, default false |
| `max_participants` | int | School only | Optional, 1-500 |
| `logo` | file | School only | Optional, image, max 2MB |

**Response (200):** `{ "success": true, "team": { ... } }`
**Error (200):** `{ "success": false, "msg": "max-created" }` — user has no remaining team slots
**Error (403):** Regular user tried to create school team
**Error (422):** Validation errors (missing/invalid fields, duplicate name/identifier)

**Controller:** `App\Http\Controllers\API\TeamsController@create`
**Test:** `tests/Feature/Teams/CreateTeamTest.php`

---

### POST /api/teams/join — Join Team

**Auth:** Required (Sanctum)

**Request:** `{ "identifier": "team_code" }`
**Response:** `{ "success": true, "team": { ... }, "activeTeam": { ... } }`
**Error:** `{ "success": false, "msg": "already-joined" }`

---

### POST /api/teams/leave — Leave Team

**Auth:** Required (Sanctum)

**Request:** `{ "team_id": 1 }`
**Response:** `{ "success": true, "team": { ... }, "activeTeam": { ... } }`
**Errors (403):** `not-a-member`, `you-are-last-member`

User cannot be the only member.

---

### POST /api/teams/active — Set Active Team

**Auth:** Required (Sanctum)

**Request:** `{ "team_id": 1 }`
**Response:** `{ "success": true, "team": { ... } }`
**Errors:** `{ "success": false, "message": "team-not-found" | "not-a-member" }`

---

### POST /api/teams/inactivate — Deactivate All Teams

**Auth:** Required (Sanctum)

Sets active team to null.

---

### PATCH /api/teams/update/{team} — Update Team

**Auth:** Required (team leader only)

**Response:** `{ "success": true, "team": { ... } }`
**Error (403):** `{ "success": false, "message": "member-not-allowed" }`

---

### GET /api/teams/joined — User's Joined Teams

**Auth:** Required (Sanctum)

**Response:** Raw array of team objects (user's teams collection).

---

### GET /api/teams/list — List User's Teams

**Auth:** Required (Sanctum)

**Response (200):**
```json
{
  "success": true,
  "teams": [
    {
      "id": 1,
      "name": "My Team",
      "identifier": "abc123",
      "type_name": "community",
      "total_members": 5,
      "total_tags": 1200,
      "total_images": 300,
      "created_at": "2025-01-15T10:00:00.000000Z",
      "updated_at": "2026-02-28T14:30:00.000000Z"
    }
  ]
}
```

---

### GET /api/teams/members?team_id=X — Team Members

**Auth:** Required (Sanctum)

**Response:**
```json
{
  "success": true,
  "total_members": 25,
  "result": [{ "id": 123, "name": "Student 1", ... }]
}
```

School teams apply safeguarding: deterministic pseudonyms ("Student 1", "Student 2", etc.).

---

### GET /api/teams/data?team_id=X&period=all — Team Dashboard Data

**Auth:** Required (Sanctum)

| Param | Type | Description |
|-------|------|-------------|
| `team_id` | int | required (0 = all user's teams) |
| `period` | string | `today`, `week`, `month`, `year`, `all` (default) |

**Response:**
```json
{
  "photos_count": 150,
  "litter_count": 500,
  "members_count": 25,
  "verification": {
    "unverified": 10,
    "verified": 20,
    "admin_approved": 50,
    "bbox_applied": 30,
    "bbox_verified": 25,
    "ai_ready": 15
  }
}
```

---

### GET /api/teams/leaderboard — Teams Leaderboard

**Auth:** Required (Sanctum)

Teams ranked by `total_tags` (descending). Only teams with `leaderboards=true` shown.

**Response (200):**
```json
{
  "success": true,
  "teams": [
    {
      "id": 1,
      "name": "Top Team",
      "type_name": "community",
      "total_members": 25,
      "total_tags": 5000,
      "total_images": 1200,
      "created_at": "2024-06-01T00:00:00.000000Z",
      "updated_at": "2026-02-28T14:30:00.000000Z"
    }
  ]
}
```

---

### POST /api/teams/leaderboard/visibility — Toggle Leaderboard Visibility

**Auth:** Required (team leader)

**Request:** `{ "team_id": 1 }`
**Response:** `{ "success": true, "visible": true }` (where `visible` = team's `leaderboards` field)
**Error (403):** `{ "success": false, "message": "member-not-allowed" }`

---

### POST /api/teams/settings — Update Team Privacy

**Auth:** Required (Sanctum)

**Request:**
```json
{
  "team_id": 1,
  "settings": {
    "show_name_maps": true,
    "show_username_maps": true,
    "show_name_leaderboards": false,
    "show_username_leaderboards": false
  }
}
```

Use `"all": true` instead of `team_id` to apply to all user's teams.

**Response:** `{ "success": true }`
**Error (403):** `{ "message": "Not a member of this team." }`

---

### GET /api/teams/photos?team_id=X&status=pending — Team Photos

**Auth:** Required (team member)

| Param | Type | Description |
|-------|------|-------------|
| `team_id` | int | required |
| `status` | string | `pending`, `approved`, `all` (default) |
| `page` | int | page number |

**Response:**
```json
{
  "success": true,
  "photos": {
    "data": [{
      "id": 123,
      "filename": "photo.jpg",
      "is_public": false,
      "verified": 1,
      "team_approved_at": null,
      "user": { "id": 42, "name": "Student 1", "username": null },
      "new_tags": [
        {
          "id": 456,
          "category_litter_object_id": 42,
          "litter_object_type_id": 1,
          "quantity": 3,
          "picked_up": true,
          "category": { "id": 2, "key": "alcohol" },
          "object": { "id": 12, "key": "bottle" },
          "extra_tags": [
            { "type": "brand", "quantity": 1, "tag": { "id": 5, "key": "heineken" } },
            { "type": "material", "quantity": 1, "tag": { "id": 11, "key": "glass" } }
          ]
        }
      ]
    }],
    "total": 50,
    "current_page": 1
  },
  "stats": { "total": 150, "pending": 50, "approved": 100 }
}
```

The `new_tags` array contains CLO-based tags with full category/object/extra_tags — same format used by the web frontend's `hydrateTagsForPhoto()`. Student names are masked to pseudonyms when safeguarding is active and viewer is not the team leader.

---

### GET /api/teams/photos/{photo} — Single Team Photo

**Auth:** Required (team member)

**Response:** `{ "success": true, "photo": { ..., "new_tags": [...] } }`

Same `new_tags` format as the index endpoint.

**Errors:** `{ "success": false, "message": "not-a-team-photo" }` (404), `{ "success": false, "message": "not-a-member" }` (403)

---

### GET /api/teams/photos/member-stats?team_id=X — Per-Student Stats

**Auth:** Required (team leader / `manage school team` permission)

Returns stats for each team member (excluding leader). Applies safeguarding pseudonyms when enabled.

**Response:**
```json
{
  "success": true,
  "members": [
    {
      "user_id": 42,
      "name": "Student 1",
      "username": null,
      "total_photos": 15,
      "pending": 3,
      "approved": 12,
      "litter_count": 87,
      "last_active": "2026-02-28 14:30:00"
    }
  ]
}
```

When safeguarding is off, `name` and `username` show real values.

---

### PATCH /api/teams/photos/{photo}/tags — Update Team Photo Tags

**Auth:** Required (team leader / `manage school team` permission)

Accepts the same CLO-based format as `POST /api/v3/tags`. Deletes existing tags, resets summary/xp/verified, calls `AddTagsToPhotoAction` to recreate.

**Request:**
```json
{
  "tags": [
    {
      "category_litter_object_id": 42,
      "litter_object_type_id": 1,
      "quantity": 3,
      "picked_up": true,
      "materials": [{ "id": 10, "quantity": 1 }],
      "brands": [{ "id": 5, "quantity": 1 }],
      "custom_tags": [{ "tag": "stained", "quantity": 1 }]
    }
  ]
}
```

**Response:** `{ "success": true, "photo": { ..., "new_tags": [...] } }`

---

### POST /api/teams/photos/approve — Approve Photos

**Auth:** Required (team leader / `manage school team` permission)

**Request:**
```json
{ "team_id": 1, "photo_ids": [123, 124] }
```
Or: `{ "team_id": 1, "approve_all": true }`

**Response:** `{ "success": true, "approved_count": 3, "message": "3 photos approved and published." }`

Idempotent (WHERE `is_public = 0`). Sets `is_public=true`, fires `TagsVerifiedByAdmin` for metrics.

---

### POST /api/teams/photos/revoke — Revoke Photo Approval

**Auth:** Required (team leader / `manage school team` permission)

**Request:**
```json
{ "team_id": 1, "photo_ids": [123, 124] }
```
Or: `{ "team_id": 1, "revoke_all": true }`

**Response:** `{ "success": true, "revoked_count": 2, "message": "2 photos revoked." }`

Idempotent (WHERE `is_public = true`). Reverses metrics, sets `is_public=false`, `verified=1`.

---

### DELETE /api/teams/photos/{photo}?team_id=X — Delete Team Photo

**Auth:** Required (team leader / `manage school team` permission)

**Response:**
```json
{
  "success": true,
  "message": "Photo deleted.",
  "stats": { "total": 149, "pending": 49, "approved": 100 }
}
```

Reverses metrics, removes S3 files, soft-deletes.

---

### GET /api/teams/photos/map?team_id=X — Team Photo Map Points

**Auth:** Required (team member)

Returns max 5000 points with `id`, `lat`, `lng`, `tags`, `verified`, `is_public`, `date`.

---

### GET /api/teams/clusters/{team} — Team Clusters (GeoJSON)

**Auth:** Required (Sanctum)

**Query params:** `zoom`, `bbox`

---

### GET /api/teams/points/{team} — Team Points (GeoJSON)

**Auth:** Required (team member)

**Query params:** `bbox`, `layers`

---

### POST /api/teams/download — Download Team Data

**Auth:** Required (Sanctum)

**Request:** `{ "team_id": 1 }`
**Response:** `{ "success": true }`
**Error:** `{ "success": false, "message": "not-a-member" }`

Queues background export job.

---

## Participant Sessions

### POST /api/participant/session — Enter Session

**Auth:** None (public)

**Request:** `{ "token": "64-char-session-code" }`

**Response (200):**
```json
{
  "success": true,
  "session": {
    "display_name": "Student 1",
    "slot_number": 1,
    "team_name": "St. Mary's 5th Class",
    "team_logo": "school-logos/abc.jpg"
  }
}
```

**Error (401):** `{ "success": false, "message": "Invalid or expired session code." }`

---

### GET /api/teams/{team}/participants — List Participant Slots

**Auth:** Required (Sanctum, team leader only)

**Response (200):**
```json
{
  "success": true,
  "participants": [
    {
      "id": 1,
      "slot_number": 1,
      "display_name": "Student 1",
      "is_active": true,
      "last_active_at": "2026-03-01T10:00:00Z",
      "photo_count": 5
    }
  ]
}
```

Note: `session_token` is hidden from JSON responses. Only revealed on create and reset-token.

---

### POST /api/teams/{team}/participants — Create Participant Slots

**Auth:** Required (Sanctum, team leader only)

**Request:** `{ "count": 5 }` or `{ "slots": [{ "display_name": "Alice" }, { "display_name": "Bob" }] }`

**Response (200):**
```json
{
  "success": true,
  "participants": [
    { "id": 1, "slot_number": 1, "display_name": "Student 1", "session_token": "abc...64chars" }
  ]
}
```

Tokens are only returned on creation and reset.

**Error (422):** Max participants exceeded or participant sessions not enabled.

---

### POST /api/teams/{team}/participants/{id}/deactivate — Deactivate Slot

**Auth:** Required (Sanctum, team leader only)

**Response (200):** `{ "success": true, "message": "Participant deactivated." }`

---

### POST /api/teams/{team}/participants/{id}/activate — Activate Slot

**Auth:** Required (Sanctum, team leader only)

**Response (200):** `{ "success": true, "message": "Participant activated." }`

---

### POST /api/teams/{team}/participants/{id}/reset-token — Reset Token

**Auth:** Required (Sanctum, team leader only)

**Response (200):** `{ "success": true, "session_token": "new-64-char-token" }`

---

### DELETE /api/teams/{team}/participants/{id} — Delete Slot

**Auth:** Required (Sanctum, team leader only)

Hard deletes the participant. Photos get `participant_id = NULL` (FK SET NULL).

**Response (200):** `{ "success": true, "message": "Participant deleted." }`

---

### GET /api/participant/photos — List Own Photos

**Auth:** `X-Participant-Token` header

**Response (200):**
```json
{
  "success": true,
  "photos": { "data": [...], "current_page": 1, "last_page": 1 }
}
```

---

### DELETE /api/participant/photos/{photo} — Delete Own Photo

**Auth:** `X-Participant-Token` header

Only allowed before teacher approval (`team_approved_at IS NULL`).

**Response (200):** `{ "success": true, "message": "Photo deleted." }`
**Error (403):** Not your photo
**Error (422):** Cannot delete approved photos

---

## Community & Map Data

### GET /api/community/stats — Community Statistics

**Auth:** None (public)

**Response (200):**
```json
{
  "photosPerMonth": 3000,
  "litterTagsPerMonth": 10000,
  "usersPerMonth": 50,
  "statsByMonth": {
    "photosByMonth": [100, 200, 300],
    "usersByMonth": [10, 20, 30],
    "periods": ["Jan 2024", "Feb 2024", "Mar 2024"]
  }
}
```

---

### GET /api/mobile-app-version — Mobile App Version

**Auth:** None (public)

**Response (200):**
```json
{
  "ios": {
    "url": "https://apps.apple.com/us/app/openlittermap/id1475982147",
    "version": "6.1.0"
  },
  "android": {
    "url": "https://play.google.com/store/apps/details?id=com.geotech.openlittermap",
    "version": "6.1.0"
  }
}
```

---

### GET /api/history/paginated — Paginated Tagging History

**Auth:** Optional (changes filter behavior)

**Query params:**

| Param | Type | Description |
|-------|------|-------------|
| `loadPage` | int | Page number (default: 1) |
| `filterCountry` | string | Country filter or `'all'` (default) |
| `filterDateFrom` | string | Start date filter |
| `filterDateTo` | string | End date filter |
| `filterTag` | string | Search in summary JSON |
| `filterCustomTag` | string | Search custom tags |
| `paginationAmount` | int | Results per page |

**Response (200):** `{ "success": true, "photos": { /* paginated */ } }`

If authenticated: shows user's own photos. If unauthenticated: shows `verified >= 2` and `is_public = true` photos only.

---

### GET /api/countries/names — Country Name List

**Auth:** None (public)

**Response (200):**
```json
{
  "success": true,
  "countries": [
    { "id": 1, "country": "United States", "shortcode": "US", "manual_verify": true }
  ]
}
```

Only returns countries with `manual_verify = true` (or shortcode `pr`).

---

### GET /api/global/points — Global Map Points (Legacy)

**Auth:** None (public)

GeoJSON endpoint for the global map. Filters: `is_public = true`. Supports `bbox`, `layers`, `fromDate`, `toDate`, `year`, `username` query params.

---

### GET /api/global/art-data — Litter Art Data (Deprecated)

**Auth:** None (public)

Returns GeoJSON of photos with `art_id != null`, `verified >= 2`, `is_public = true`.

---

### GET /api/global/search/custom-tags — Search Custom Tags

**Auth:** None (public)

**Query params:** `search` (required, string prefix)

**Response (200):**
```json
{ "success": true, "tags": ["plastic wrapper", "plastic bottle"] }
```

Returns top 20 matching custom tags ordered by frequency.

---

### GET /api/tags-search — Display Tags on Map

**Auth:** None (public)

**Query params:** `custom_tag`, `custom_tags` (comma-separated), `brand`

Returns GeoJSON FeatureCollection of matching photos (`is_public = true`, max 5000).

---

### POST /api/download — Download Location Data

**Auth:** Optional

**Request:**
```json
{ "locationType": "city", "locationId": 42, "email": "user@example.com" }
```

`email` is optional if authenticated (uses auth user's email). Queues CSV export job, emails download link.

**Response:** `{ "success": true }` or `{ "success": false }`

---

## Cleanups

### POST /api/cleanups/create — Create Cleanup Event

**Auth:** Required

**Request:**
```json
{
  "name": "Beach Cleanup",
  "date": "2025-03-15",
  "lat": 40.7128,
  "lon": -74.0060,
  "time": "10:00 AM",
  "description": "Annual beach cleanup event",
  "invite_link": "beach-cleanup-2025"
}
```

| Field | Rules |
|-------|-------|
| `name` | required, min 5 |
| `date` | required |
| `lat` | required |
| `lon` | required |
| `time` | required, min 3 |
| `description` | required, min 5 |
| `invite_link` | required, unique, min 1 |

**Response:** `{ "success": true, "cleanup": { ... } }`

Creator is automatically joined.

---

### GET /api/cleanups/get-cleanups — Get All Cleanups (GeoJSON)

**Auth:** None (public)

**Response:** `{ "success": true, "geojson": { /* GeoJSON FeatureCollection */ } }`

---

### POST /api/cleanups/{inviteLink}/join — Join Cleanup

**Auth:** Optional (returns error message if unauthenticated)

**Response:** `{ "success": true, "cleanup": { ... } }`
**Errors:** `{ "success": false, "msg": "unauthenticated" | "already joined" | "cleanup not found" }`

---

### POST /api/cleanups/{inviteLink}/leave — Leave Cleanup

**Auth:** Required

**Response:** `{ "success": true }`
**Errors:** `{ "success": false, "msg": "not found" | "cannot leave" | "already left" }`

Creator cannot leave their own cleanup.

---

### GET /api/city — Get City Map Data (Legacy)

**Auth:** None (public)

**Query params:** `city` (required, URL-decoded name), `min` / `max` (dates, format `d-m-Y`), `hex` (optional, default 100)

**Response (200):**
```json
{
  "center_map": [40.7128, -74.0060],
  "map_zoom": 13,
  "litterGeojson": { "type": "FeatureCollection", "features": [...] },
  "hex": 100
}
```

Filters: `is_public = true`, `verified > 0`.

---

### POST /api/littercoin/merchants — Become a Merchant

**Auth:** None specified in route

Registers interest as a Littercoin merchant.

---

### GET /api/locations/world-cup — World Cup Data

**Auth:** None (public)

Returns location data for the World Cup leaderboard.

---

### POST /api/settings/toggle — Toggle Picked Up

**Auth:** Required (Sanctum)

@deprecated — Use `POST /api/settings/update` with `key: "picked_up"` instead.

Toggles the `picked_up` boolean for the user.

**Response:** `{ "message": "success", "picked_up": true }`

---

### POST /api/profile/photos/remaining/{id} — Toggle Photo Remaining Flag

**Auth:** Required (Sanctum)

Toggles the `remaining` field on a specific photo.

**Response:** `{ "success": true }`

---

### POST /api/user/profile/photos/tags/bulkTag — Bulk Tag Photos (Deprecated)

**Auth:** Required (Sanctum)
**Status:** 410 Gone

**Response:** `{ "message": "Use POST /api/v3/tags for tagging" }`

---

### POST /api/profile/upload-profile-photo — Upload Profile Photo (Stub)

**Auth:** Required (Sanctum)
**Status:** 501 Not Implemented

Not yet implemented.

---

## Admin Endpoints

Admin endpoints under `/api/admin/` require the `admin` middleware (`hasRole('admin')` or `hasRole('superadmin')`). Internal use — not for mobile clients.

### GET /api/admin/photos — Photo Review Queue

**Auth:** Admin middleware (admin or superadmin)

**Query params:**

| Param | Type | Description |
|-------|------|-------------|
| `country_id` | int | Filter by country |
| `user_id` | int | Filter by uploader |
| `photo_id` | int | Find specific photo |
| `date_from` | date | Created after (YYYY-MM-DD) |
| `date_to` | date | Created before (YYYY-MM-DD) |
| `per_page` | int | Results per page (default 20, max 50) |
| `page` | int | Page number |

**Response (200):**
```json
{
  "success": true,
  "photos": {
    "data": [
      {
        "id": 123,
        "user_id": 42,
        "filename": "photos/abc123.jpg",
        "country_id": 1,
        "state_id": 5,
        "city_id": 10,
        "verified": 1,
        "summary": {"smoking": {"cigarette_butt": 3}},
        "total_tags": 3,
        "xp": 18,
        "created_at": "2025-02-27T10:00:00.000000Z",
        "user": {"id": 42, "name": "John"},
        "country_relation": {"id": 1, "country": "United States", "shortcode": "us"},
        "new_tags": [
          {
            "category_litter_object_id": 45,
            "litter_object_type_id": null,
            "category": "smoking",
            "object": "cigarette_butt",
            "quantity": 3,
            "picked_up": false,
            "extra_tags": []
          }
        ]
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 342
  },
  "stats": {"total_pending": 342}
}
```

**Query:** `is_public=true`, `verified < ADMIN_APPROVED`, `summary NOT NULL`, ordered by `created_at ASC`.

---

### POST /api/admin/verify — Approve Photo

**Auth:** Admin middleware

**Request:**
```json
{ "photoId": 123 }
```

**Response (200):**
```json
{ "success": true, "approved": true }
```

Returns `"approved": false` if already approved (idempotent). Returns 422 if `summary` is null.

---

### POST /api/admin/contentsupdatedelete — Edit Tags + Approve

**Auth:** Admin middleware

**Request:**
```json
{
  "photoId": 123,
  "tags": [
    {
      "category_litter_object_id": 45,
      "quantity": 3,
      "picked_up": true,
      "materials": [{"id": 1, "quantity": 1}],
      "brands": [{"id": 5, "quantity": 1}],
      "custom_tags": ["tag_text"]
    }
  ]
}
```

**Response (200):**
```json
{ "success": true, "approved": true, "photo": {...} }
```

Wrapped in `DB::transaction()`: deletes existing PhotoTags, creates new via `AddTagsToPhotoAction`, then approves.

---

### POST /api/admin/destroy — Delete Photo

**Auth:** Admin middleware

**Request:**
```json
{ "photoId": 123 }
```

**Response (200):**
```json
{ "success": true }
```

Calls `MetricsService::deletePhoto()` before soft delete (if `processed_at` set).

---

### POST /api/admin/reset-tags — Reset Tags

**Auth:** Admin middleware

**Request:**
```json
{ "photoId": 123 }
```

**Response (200):**
```json
{ "success": true }
```

Reverses metrics, deletes PhotoTags, resets `verified=0`, `summary=null`, `xp=0`. Skips already-approved photos.

---

### GET /api/admin/get-countries-with-photos — Countries with Pending

**Auth:** Admin middleware

**Response (200):** Array of `{id, country, total}` — countries with pending public photos.

---

### GET /api/admin/stats — Dashboard Stats

**Auth:** Admin middleware
**Cache:** 60 seconds (`admin:dashboard:stats`)

**Response (200):**
```json
{
  "success": true,
  "stats": {
    "queue_total": 342,
    "queue_today": 15,
    "by_verification": {
      "Unverified": 50,
      "Verified": 292,
      "Admin Approved": 0
    },
    "by_country": {
      "United States": 128,
      "Ireland": 42
    },
    "total_users": 5420,
    "users_today": 3,
    "flagged_usernames": 7
  }
}
```

`by_verification` uses `VerificationStatus::label()` enum labels. `by_country` shows top 20.

---

### GET /api/admin/users — List Users

**Auth:** Admin middleware

**Query params:**

| Param | Type | Description |
|-------|------|-------------|
| `search` | string | Search name/username/email (LIKE) |
| `sort_by` | string | `created_at` (default), `photos_count`, `xp` |
| `sort_dir` | string | `asc` or `desc` (default) |
| `trust_filter` | string | `all` (default), `trusted`, `untrusted` |
| `flagged` | bool | Show only `username_flagged=true` users |
| `per_page` | int | Results per page (default 25, max 100) |
| `page` | int | Page number |

**Response (200):**
```json
{
  "success": true,
  "users": {
    "data": [
      {
        "id": 1,
        "name": "John Doe",
        "username": "john-doe",
        "email": "john@example.com",
        "created_at": "2025-01-15",
        "photos_count": 42,
        "xp": 500,
        "verification_required": true,
        "pending_photos": 5,
        "roles": ["user"],
        "is_trusted": false,
        "username_flagged": false
      }
    ],
    "current_page": 1,
    "per_page": 25,
    "total": 250
  }
}
```

`pending_photos` = public photos where `verified < ADMIN_APPROVED`.

---

### POST /api/admin/users/{user}/trust — Toggle Trust

**Auth:** Superadmin only (403 for admin/helper)

**Request:**
```json
{ "trusted": true }
```

**Response (200):**
```json
{
  "user_id": 42,
  "trusted": true,
  "verification_required": false
}
```

Sets `verification_required = !trusted`. Does NOT retroactively approve existing photos.

---

### POST /api/admin/users/{user}/approve-all — Bulk Approve

**Auth:** Superadmin only (403 for admin/helper)

**Response (200):**
```json
{ "approved_count": 15 }
```

Approves all pending public photos for user (max 500). Same atomic WHERE + `TagsVerifiedByAdmin` event as `verify()`.

---

### PATCH /api/admin/users/{user}/username — Moderate Username

**Auth:** Superadmin only (403 for admin/helper)

**Request:**
```json
{ "username": "new-username" }
```

**Response (200):**
```json
{
  "user_id": 42,
  "username": "new-username",
  "previous_username": "old-flagged-name"
}
```

Validation: 3–30 chars, alphanumeric + hyphens, unique. Clears `username_flagged`.

---

## Bounding Box Endpoints

Bbox endpoints under `/api/bbox/` require the `can_bbox` middleware. Used for bounding box annotation workflow. Includes: index, create, skip, update tags, verify.

---

## ~~Mobile Endpoints (v2)~~ — REMOVED

> **All v2 mobile endpoints removed (2026-03-01).** Use v3 equivalents:
> - Untagged photos: `GET /api/v3/user/photos?tagged=false`
> - Upload: `POST /api/v3/upload`
> - Tag: `POST /api/v3/tags`
> - Delete: `POST /api/profile/photos/delete`
>
> Removed endpoints: `GET /api/v2/photos/get-untagged-uploads`, `GET /api/v2/photos/web/index`, `GET /api/v2/photos/web/load-more`, `POST /api/v2/add-tags-to-uploaded-image`, `POST /api/upload`.

---

## Reference

### Verification Pipeline

| Value | Status | Meaning |
|-------|--------|---------|
| 0 | UNVERIFIED | Uploaded, no tags |
| 1 | VERIFIED | Tagged (school students land here, awaiting teacher approval) |
| 2 | ADMIN_APPROVED | Verified by admin/trusted user OR teacher-approved |
| 3 | BBOX_APPLIED | Bounding boxes drawn |
| 4 | BBOX_VERIFIED | Bounding boxes verified |
| 5 | AI_READY | Ready for OpenLitterAI training |

### Level System

| XP Threshold | Level | Title |
|-------------|-------|-------|
| 0 | 0 | Complete Noob |
| 100 | 1 | Still A Noob |
| 500 | 2 | Post-Noob |
| 1,000 | 3 | Litter Wizard |
| 5,000 | 4 | Trash Warrior |
| 10,000 | 5 | Early Guardian |
| 15,000 | 6 | Trashmonster |
| 50,000 | 7 | Force of Nature |
| 100,000 | 8 | Planet Protector |
| 200,000 | 9 | Galactic Garbagething |
| 500,000 | 10 | Interplanetary |
| 1,000,000 | 11 | SuperIntelligent LitterMaster |

### XP Scoring

| Action | XP per unit |
|--------|------------|
| Upload | 5 |
| Object tag | 1 (special overrides exist) |
| Brand | 3 |
| Material | 2 |
| Custom tag | 1 |

Brands use their own `quantity`. Materials and custom_tags use parent tag's `quantity`.

### Error Response Patterns

Controllers use two error field names inconsistently:
- `msg` — Used by: auth endpoints, team join, legacy photo endpoints
- `message` — Used by: team member/settings endpoints, team photos, newer endpoints

Both return `success: false` with the error string.

### Auth Architecture

- **SPA (web):** Session-based via `auth:web` + Sanctum cookie
- **Mobile:** Stateless Sanctum tokens via `Authorization: Bearer {token}`
- **Dual guard:** Most routes use `auth:sanctum` (supports both session and token)
- Token login revokes previous tokens (prevents buildup)
- Registration returns both session + token (immediate use from either client)
