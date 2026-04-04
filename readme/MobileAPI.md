# Mobile API Reference

Endpoints the React Native app uses (or should use). All authenticated endpoints use `Authorization: Bearer <sanctum_token>`.

Base URL configured in `actions/types.js` via `react-native-config`.

---

## Auth

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| POST | `/api/auth/token` | `auth_reducer.js` → `userLogin` | Active |
| POST | `/api/register` | `auth_reducer.js` → `createAccount` | Active (alias for `/api/auth/register`) |
| POST | `/api/validate-token` | `auth_reducer.js` → `checkValidToken` | Active |
| POST | `/api/password/email` | `auth_reducer.js` → `sendResetPasswordRequest` | Active |

### Login — `POST /api/auth/token`

```json
// Request
{ "identifier": "email_or_username", "password": "secret" }

// Response 200
{
    "token": "1|abcdef...",
    "user": {
        "id": 1, "email": "...", "username": "...",
        "xp_redis": 5000, "position": 12, "level": 3,
        "total_images": 42,
        "next_level": { "level": 4, "title": "Litter Wizard", "progress_percent": 50, ... }
    }
}
```

Accepts `identifier`, `email`, or `username` field (priority: identifier > email > username). Throttled 5/min. Revokes previous `mobile` tokens.

### Register — `POST /api/register`

Alias for `POST /api/auth/register`. Both routes hit the same controller.

```json
// Request
{ "email": "...", "password": "min8chars", "username": "optional" }

// Response 200
{ "token": "1|abcdef...", "user": { ... } }
```

Username auto-generated if omitted (pattern: `adjective-noun-number`). Token created with name `mobile`.

### Validate Token — `POST /api/validate-token`

Returns `{ "message": "valid" }` on 200. Returns 401 if token is invalid/expired.

---

## User Profile

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| GET | `/api/user/profile/index` | `auth_reducer.js` → `fetchUser` | **Active** (migrated from `GET /api/user`) |
| GET | `/api/user` | — | **Deprecated** — expensive position table scan |
| GET | `/api/user/profile/{id}` | Phase 4 | Public profiles |
| GET | `/api/user/profile/map` | Phase 4 | User map GeoJSON |

### Profile Index — `GET /api/user/profile/index`

Single call returns everything the profile screen needs. Stats come from Redis with MySQL fallback (fast).

```json
{
  "user": {
    "id": 1, "name": "...", "username": "...", "email": "...",
    "avatar": null, "global_flag": "ie", "member_since": "January 2020",
    "picked_up": true,
    "public_profile": true, "show_name": true, "show_username": true,
    "show_name_maps": true, "show_username_maps": true,
    "previous_tags": true, "emailsub": true
  },
  "stats": {
    "uploads": 100, "litter": 450, "xp": 5000,
    "streak": 7, "littercoin": 250,
    "photo_percent": 0.5, "tag_percent": 0.8
  },
  "level": {
    "level": 3, "title": "Litter Wizard",
    "xp": 5000, "xp_into_level": 0, "xp_for_next": 5000,
    "xp_remaining": 0, "progress_percent": 100
  },
  "rank": {
    "global_position": 42, "global_total": 500, "percentile": 91.6
  },
  "global_stats": {
    "total_photos": 20000, "total_litter": 56000
  },
  "achievements": { "unlocked": 15, "total": 30 },
  "locations": { "countries": 5, "states": 12, "cities": 45 },
  "team": { "id": 5, "name": "Team A" }
}
```

`team` is `null` if no active team. `picked_up` is the user's default preference for new photos (`true` = litter was picked up).

**State mapping:** The `fetchUser.fulfilled` handler in `auth_reducer.js` flattens the nested response into the state model screens expect:

| API Response | State Field |
|---|---|
| `user.*` | Spread directly (includes settings, privacy flags) |
| `stats.xp` | `user.xp_redis` |
| `stats.uploads` | `user.total_images` |
| `stats.litter` | `user.totalTags` |
| `stats.littercoin` | `user.totalLittercoin` |
| `stats.streak` | `user.streak` |
| `rank.global_position` | `user.position` |
| `rank.percentile` | `user.percentile` |
| `level.level` | `user.level` |
| `level.title` | `user.levelTitle` |
| `level.progress_percent` | `user.targetPercentage` |
| `level.xp_remaining` | `user.xpRequired` |
| `team.id` | `user.active_team` |
| `team` | `user.team` |
| `achievements` | `user.achievements` |
| `locations` | `user.locations` |

### Public Profile — `GET /api/user/profile/{id}` (Phase 4)

No auth required. Returns another user's public profile.

```json
// Public profile (public_profile = true)
{
  "public": true,
  "user": {
    "id": 1,
    "name": "John Doe",
    "username": "@johndoe",
    "avatar": "https://...",
    "global_flag": "ie",
    "member_since": "January 2020"
  },
  "stats": { "uploads": 150, "litter": 412, "xp": 5200 },
  "level": {
    "level": 3, "title": "Litter Wizard",
    "xp": 5200, "xp_into_level": 200, "xp_for_next": 5000,
    "xp_remaining": 4800, "progress_percent": 4.0
  },
  "rank": { "global_position": 42, "global_total": 2850, "percentile": 98.5 },
  "achievements": { "unlocked": 8, "total": 24 },
  "locations": { "countries": 12, "states": 18, "cities": 45 }
}

// Private profile (public_profile = false)
{ "public": false }
```

- `name`/`username` respect privacy settings — `null` if `show_name`/`show_username` is false
- 404 if user not found

### Profile Map — `GET /api/user/profile/map` (Phase 4)

Auth required. Returns GeoJSON for the authenticated user's photos.

**Important:** Coordinates are `[lat, lon]` — NOT the standard GeoJSON `[lon, lat]`. The mobile app must swap these when rendering on a map.

---

## Photo Upload

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| POST | `/api/photos/upload/with-or-without-tags` | `images_reducer.js` → `uploadImage` | Active |
| POST | `/api/photos/submit` | Not used | Simpler — upload only, no tag fields |

All three upload aliases (`/api/photos/submit-with-tags`, `/api/photos/upload-with-tags`, `/api/photos/upload/with-or-without-tags`) hit the same controller.

### Upload — `POST /api/photos/upload/with-or-without-tags`

```
Content-Type: multipart/form-data

photo: <file>
lat: 51.925
lon: -7.872
date: 1770561192 (Unix timestamp)
picked_up: 0|1
model: "iPhone"
```

Response: `{ "success": true, "photo_id": 515917 }`

Errors: `"error-3"` (generic), `"photo-already-uploaded"`, `"invalid-coordinates"`

---

## Tagging

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| POST | `/api/v3/tags` | `images_reducer.js` → `postTagsToPhoto` | Active (v5) |
| PUT | `/api/v3/tags` | Phase 3 | **Full replace** (not merge) |
| POST | `/api/v2/add-tags-to-uploaded-image` | `images_reducer.js` → `uploadTagsToWebImage` | Legacy v4 — migrate to v3 |

### Post Tags — `POST /api/v3/tags`

```json
// Request (v5 CLO format — preferred)
{
  "photo_id": 515917,
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

// Request (legacy v4 format — still accepted)
{
  "photo_id": 515917,
  "tags": [
    {
      "category": "smoking",
      "object": "butts",
      "quantity": 3,
      "picked_up": true,
      "materials": [10, 11],
      "brands": [{ "id": 1, "quantity": 1 }],
      "custom_tags": ["found near café"]
    }
  ]
}

// Response 200
{
  "success": true,
  "photoTags": [
    {
      "id": 312673,
      "photo_id": 515917,
      "category_litter_object_id": 42,
      "category_id": 2,
      "litter_object_id": 12,
      "litter_object_type_id": 1,
      "quantity": 2,
      "picked_up": true,
      "created_at": "2026-03-01T12:34:56.000000Z",
      "updated_at": "2026-03-01T12:34:56.000000Z"
    }
  ]
}
```

**Validation rules:**
- `photo_id` — required, integer, must exist in photos table (not soft-deleted)
- `tags` — required, array, min 1 item
- `tags.*.category_litter_object_id` — integer, exists in `category_litter_object` table
- `tags.*.litter_object_type_id` — nullable integer, exists in `litter_object_types` table
- `tags.*.quantity` — integer, min 1 (defaults to 1)
- `tags.*.picked_up` — nullable boolean
- `tags.*.materials` — array of material IDs
- `tags.*.brands` — array of `{ id, quantity }` objects
- `tags.*.custom_tags` — array of strings

**Gates:** 403 if not owned, 403 if already verified (`verified >= 1`).

**Side effects:** Generates photo summary, calculates XP, fires `TagsVerifiedByAdmin` for leaderboard credit.

### Replace Tags — `PUT /api/v3/tags` (Phase 3)

Same request/response format as POST. Key differences:

- **Full replace, not merge** — deletes ALL existing tags first, resets XP/verification, then adds new tags
- **No verification gate** — allows re-tagging photos in any state (POST rejects verified photos)
- Wrapped in `DB::transaction()` — delete+reset+add is atomic
- The mobile app must send the **complete** set of tags, not just changes

### How to Build the Tag Search Index

Use `GET /api/tags/all` to fetch 7 flat arrays, then join client-side:

```json
{
  "categories":    [{ "id": 1, "key": "smoking" }, ...],
  "objects":       [{ "id": 5, "key": "cigarette_butt", "categories": [{ "id": 1, "key": "smoking" }] }, ...],
  "materials":     [{ "id": 10, "key": "plastic" }, ...],
  "brands":        [{ "id": 1, "key": "coca_cola" }, ...],
  "types":         [{ "id": 1, "key": "wine", "name": "Wine" }, ...],
  "category_objects":      [{ "id": 42, "category_id": 2, "litter_object_id": 12 }, ...],
  "category_object_types": [{ "category_litter_object_id": 42, "litter_object_type_id": 1 }, ...]
}
```

**To tag a "wine bottle":**
1. Search `objects` for "bottle" → `{ id: 12, key: "bottle" }`
2. Find `category_objects` where `litter_object_id = 12` → `[{ id: 42, category_id: 2 }, ...]`
3. Pick the relevant category (alcohol = id 2) → `category_litter_object_id = 42`
4. Check `category_object_types` for `(42, type_id)` → type 1 = wine
5. Submit: `{ category_litter_object_id: 42, litter_object_type_id: 1, quantity: 1 }`

**Notes:**
- `category_object_types` has no `id` column — use composite key `(category_litter_object_id, litter_object_type_id)`
- Not all objects have types — `litter_object_type_id` is nullable
- Cache `GET /api/tags/all` locally for 7 days

### XP Calculation

After tagging, `photo.xp` is auto-calculated:
- Base upload: **+5 XP**
- Per litter object: **+1 XP** (special: `dumping_small` +10, `dumping_medium` +25, `dumping_large` +50, `bags_litter` +10)
- Per brand: **+3 XP**
- Per material: **+2 XP**
- Per custom tag: **+1 XP**

---

## Photo Queue

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| GET | `/api/v2/photos/get-untagged-uploads` | `images_reducer.js` → `getUntaggedImages` | Active |

### Untagged Queue — `GET /api/v2/photos/get-untagged-uploads`

Returns all untagged photos for the authenticated user. Paginated (100/page). No platform filter — web and mobile uploads appear in the same queue.

```json
// Response 200
{
  "count": 5,
  "photos": [
    {
      "id": 123,
      "filename": "https://s3-bucket.amazonaws.com/.../abc123.jpg",
      "remaining": 1
    }
  ]
}
```

- `filename` = full S3 URL, use directly as image source
- `remaining`: 1 = litter left, 0 = picked up

**How "untagged" is detected:** Uses `whereNull('summary')` — the `summary` column is set by `GeneratePhotoSummaryService` when tags are added, regardless of verification status. This correctly excludes photos tagged by untrusted users (whose `verified` stays at 0 for map visibility reasons but who do have a summary).

**Breaking change (2026-03-01):** The `?platform=web|mobile` query parameter and `platform` response field have been removed. All untagged photos are now returned in one list. If the mobile app was filtering by platform, remove that filter.

---

## Photo Deletion

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| DELETE | `/api/photos/delete` | `images_reducer.js` → `deleteWebImage` | Active |

### Delete Photo — `DELETE /api/photos/delete`

```json
// Request body (canonical — photoId in body, not query params)
{ "photoId": 123 }

// Response 200
{ "success": true }
```

Reverses metrics (XP, total_images decremented with `max(0, ...)` guard), removes S3 files, soft-deletes. 403 if not owned.

---

## Upload History

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| GET | `/api/v3/user/photos` | `my_uploads_reducer.js` → `fetchUploads` | **Active** (migrated from `/history/paginated`) |
| GET | `/api/v3/user/photos/stats` | Phase 4 | Upload statistics |

### User Photos — `GET /api/v3/user/photos`

Auth required (Sanctum). Paginated (8 per page), ordered by `created_at` DESC.

**Query params (all optional):**

| Param | Type | Description |
|-------|------|-------------|
| `page` | int | Page number (default 1) |
| `tagged` | bool | `true` = has summary (tagged), `false` = no summary (untagged) |
| `tag` | string | Filter by litter object key (LIKE search) |
| `custom_tag` | string | Filter by custom tag key (LIKE search) |
| `date_from` | string | Start date (ISO) |
| `date_to` | string | End date (ISO) |

```json
// Response 200
{
  "photos": [{
    "id": 123,
    "filename": "https://...",
    "datetime": "2020-01-15T10:30:00Z",
    "lat": 40.7128, "lon": -74.0060,
    "model": "iPhone 12",
    "remaining": true,
    "team": { "id": 5, "name": "Team A" },
    "new_tags": [{
      "id": 1,
      "category_litter_object_id": 42,
      "litter_object_type_id": 1,
      "quantity": 3,
      "picked_up": true,
      "category": { "id": 1, "key": "smoking" },
      "object": { "id": 2, "key": "cigarette" },
      "extra_tags": []
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

Tags are under `new_tags` (v5 format with nested category/object/extra_tags). The reducer converts `new_tags` to `result_string` format for backward-compatible display in the MyUploads screen.

### Upload Stats — `GET /api/v3/user/photos/stats` (Phase 4)

Auth required.

```json
{
  "totalPhotos": 150,
  "totalTags": 412,
  "leftToTag": 23,
  "taggedPercentage": 85
}
```

- `taggedPercentage` is an integer 0-100
- `leftToTag` = photos with `summary IS NULL` (not yet tagged)

---

## Global Stats

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| GET | `/api/global/stats-data` | `stats_reducer.js` → `getStats` | Active |

Public, no auth. Returns: `total_tags`, `total_images`, `total_users`, `new_users_last_24_hours`, `new_users_last_7_days`, `new_users_last_30_days`, `new_tags_last_24_hours`, `new_tags_last_7_days`, `new_tags_last_30_days`, `new_photos_last_24_hours`, `new_photos_last_7_days`, `new_photos_last_30_days`.

---

## Leaderboard

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| GET | `/api/leaderboard` | `leaderboards_reducer.js` → `getLeaderboardData` | Active |

Public, no auth required. Authenticated users also receive `currentUserRank`; unauthenticated receive `null`.

### Query Parameters

| Param | Type | Values | Default |
|-------|------|--------|---------|
| `timeFilter` | string | `all-time`, `today`, `yesterday`, `this-month`, `last-month`, `this-year`, `last-year` | `all-time` |
| `locationType` | string | `global`, `country`, `state`, `city` | `global` |
| `locationId` | int | ID of the location | — |
| `page` | int | Page number (100 per page) | `1` |

**Important:** `locationType` and `locationId` must BOTH be provided together. Sending one without the other returns an error.

```json
// Response 200
{
  "success": true,
  "users": [
    {
      "user_id": 42,
      "public_profile": true,
      "name": "Sean",
      "username": "@seanlynch",
      "xp": 1234,
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

// Error response
{ "success": false, "msg": "Both locationType and locationId required" }
```

**Field notes:**
- `xp` — integer (format client-side with `toLocaleString()`)
- `name`/`username` — empty string `""` if user hides them via privacy settings
- `username` has `@` prefix added by the backend
- `team` — empty string `""` if no active team
- `social` — JSON object or `null`
- Zero-XP users are excluded from results
- Tied XP users ordered by `user_id` ascending (deterministic)

---

## Levels

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| GET | `/api/levels` | `screens/profile/helpers/xpLevels.js` | Active |

Returns XP thresholds for level display. Cached locally for 7 days.

---

## Locations

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| GET | `/api/locations/country` | `locations_reducer.js` → `fetchCountries` | Active |
| GET | `/api/locations/{type}/{id}` | `locations_reducer.js` → `fetchLocationChildren` | Active |

Public, no auth. Types: `country`, `state`, `city`.

---

## Settings

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| POST | `/api/settings/update` | `settings_reducer.js` → `saveSettings` | **Preferred** — unified key/value endpoint |
| POST | `/api/settings/privacy/{endpoint}` | `settings_reducer.js` → `toggleSettingsSwitch` | Active — individual toggles |
| PATCH | `/api/settings` | `settings_reducer.js` → `saveSocialAccounts` | Active — social links only |
| PATCH | `/api/settings/details/password` | `settings_reducer.js` → `changePassword` | Active — password changes |
| POST | `/api/settings/delete-account` | `settings_reducer.js` → `deleteAccount` | Active |

### Update Setting — `POST /api/settings/update`

```json
// Request
{ "key": "username", "value": "new_value" }

// Response 200
{ "success": true }

// Response 422 (validation error)
{ "success": false, "msg": "This email is already taken." }
```

**Allowed keys and validation:**

| Key | Type | Validation | Notes |
|-----|------|-----------|-------|
| `name` | string | min:3, max:25 | |
| `username` | string | min:3, max:30, alphanumeric + hyphen, unique | Flags for admin review |
| `email` | string | valid email, max:75, unique | |
| `global_flag` | string | nullable, max:10 | Country shortcode |
| `picked_up` | boolean | true/false | User's default "picked up" preference |
| `previous_tags` | boolean | true/false | Show previous tags on next photo |
| `emailsub` | boolean | true/false | Email subscription |
| `public_profile` | boolean | true/false | Enable public profile page |

Legacy key `items_remaining` is still accepted — remaps to `picked_up` with inverted value. New code should use `picked_up` directly.

### Privacy Toggles — `POST /api/settings/privacy/{endpoint}`

Each endpoint toggles the boolean and returns the new value.

| Endpoint | Setting | Response |
|----------|---------|----------|
| `maps/name` | `show_name_maps` | `{ "show_name_maps": false }` |
| `maps/username` | `show_username_maps` | `{ "show_username_maps": false }` |
| `leaderboard/name` | `show_name` | `{ "show_name": false }` |
| `leaderboard/username` | `show_username` | `{ "show_username": false }` |
| `createdby/name` | `show_name_createdby` | `{ "show_name_createdby": false }` |
| `createdby/username` | `show_username_createdby` | `{ "show_username_createdby": false }` |
| `toggle-previous-tags` | `previous_tags` | `{ "previous_tags": true }` |

### Social Links — `PATCH /api/settings`

```json
// Request
{
  "social_twitter": "https://twitter.com/user",
  "social_instagram": "https://instagram.com/user"
}

// Response 200
{ "message": "success" }
```

All social fields must be valid URLs. Allowed: `social_twitter`, `social_facebook`, `social_instagram`, `social_linkedin`, `social_reddit`, `social_personal`.

### Password Change — `PATCH /api/settings/details/password`

```json
// Request
{
  "oldpassword": "current_password",
  "password": "new_password",
  "password_confirmation": "new_password"
}

// Response 200
{ "message": "success" }  // or { "message": "fail" } if old password wrong
```

Min 5 chars for new password. No 4xx on wrong old password — always 200 with `"fail"`.

### Delete Account — `POST /api/settings/delete-account`

```json
// Request
{ "password": "current_password" }

// Response 200
{ "success": true }
```

Deletes user data, revokes tokens, cleans up Redis. Irreversible.

### Deprecated Settings Endpoints (DO NOT USE)

These `UsersController` endpoints have a latent web-guard conflict and are deprecated:

| Route | Use Instead |
|-------|-------------|
| `POST /api/settings/details` | `POST /api/settings/update` with individual key/value |
| `POST /api/settings/privacy/update` | Individual `POST /api/settings/privacy/{endpoint}` toggles |
| `POST /api/settings/toggle` | `POST /api/settings/update` with `key: "picked_up"` |

---

## Teams

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| POST | `/api/teams/create` | `team_reducer.js` → `createTeam` | Active |
| POST | `/api/teams/join` | `team_reducer.js` → `joinTeam` | Active |
| POST | `/api/teams/leave` | `team_reducer.js` → `leaveTeam` | Active |
| POST | `/api/teams/active` | `team_reducer.js` → `changeActiveTeam` | Active |
| POST | `/api/teams/inactivate` | `team_reducer.js` → `inactivateTeam` | Active |
| GET | `/api/teams/list` | `team_reducer.js` → `getUserTeams` | Active |
| GET | `/api/teams/members` | `team_reducer.js` → `getTeamMembers` | Active |
| GET | `/api/teams/leaderboard` | `team_reducer.js` → `getTopTeams` | Active |

---

## Utility

| Method | Route | Mobile File | Status |
|--------|-------|-------------|--------|
| GET | `/api/mobile-app-version` | `shared_reducer.js` → `checkAppVersion` | Active |

---

## Backend Changes Log

### 2026-03-01

1. **Leaderboard XP is now an integer** — `xp` field was `"1,234"` (string), now `1234` (int). Format client-side.
2. **`items_remaining` renamed to `picked_up`** — `picked_up = true` means "litter was picked up". Settings endpoint still accepts legacy `items_remaining` key (remapped + inverted).
3. **`GET /api/history/paginated` now requires auth** — moot since mobile already migrated.
4. **UsersController middleware fixed** — removed constructor `middleware('auth')` that conflicted with Sanctum tokens. All routes now use `auth:sanctum` consistently.
5. **UsersController endpoints deprecated** — `details`, `togglePrivacy`, `togglePresence` marked deprecated with pointers to `ApiSettingsController` equivalents.
6. **XP key fixes** — special XP objects (`dumping_small/medium/large`, `bags_litter`) now correctly apply bonus XP.
7. **CSRF fix** — removed `'web'` from v3 route group middleware.
8. **Untagged queue fix** — `GET /api/v2/photos/get-untagged-uploads` now uses `whereNull('summary')` instead of `WHERE verified = 0`. Tagged photos no longer appear in the untagged queue (was caused by untrusted users keeping `verified=0` after tagging). The `?platform=web|mobile` filter and `platform` response field have been removed — all untagged photos appear in one queue.
9. **Tagged/untagged filter fix** — `GET /api/v3/user/photos?tagged=true|false` and `GET /api/v3/user/photos/stats` `leftToTag` now use summary null check instead of verification status.

---

## Migration Roadmap

### Phase 1: Fix What's Broken (Done)
- [x] CSRF fixed on `POST /api/v3/tags`
- [x] Replace `GET /api/user` with `GET /api/user/profile/index` in `fetchUser`
- [x] Update `auth_reducer.js` to destructure new nested response shape
- [x] Migrate `fetchUploads` from unguarded `/history/paginated` to `GET /api/v3/user/photos`
- [x] Fix `deleteWebImage` to send `photoId` in body only (not query params)

### Phase 2: Settings Safety (Backend Done)
- [x] Backend: Fixed UsersController middleware conflict (removed web guard)
- [x] Backend: Deprecated overlapping UsersController endpoints
- [x] Backend: All settings endpoints tested with Sanctum token auth (18 tests)
- [ ] Mobile: Audit all settings calls use `POST /api/settings/update` (not `UsersController`)
- [ ] Mobile: Switch any `items_remaining` usage to `picked_up` directly
- [ ] Mobile: Handle leaderboard XP integer change (format client-side)
- [ ] Mobile: `PATCH /api/settings` for social links — works with Sanctum, no migration needed

### Phase 3: V5 Tagging Complete
- [x] Backend: `POST /api/v3/tags` accepts both v4 and v5 formats
- [x] Backend: `PUT /api/v3/tags` for full tag replacement (verified)
- [x] Backend: `GET /api/tags/all` returns all 7 arrays for search index
- [ ] Mobile: Migrate `uploadTagsToWebImage` (v4) to `postTagsToPhoto` (v5 CLO format)
- [ ] Mobile: Build tag search index from `GET /api/tags/all` (join 7 arrays into `entriesByCloId`)
- [ ] Mobile: Implement tag editing via `PUT /api/v3/tags` — send ALL tags (full replace)
- [ ] Mobile: Add materials, brands, custom tags to tagging UI

### Phase 4: Enhanced Features
- [x] Backend: Public profiles (`GET /api/user/profile/{id}`) — verified
- [x] Backend: Location-scoped leaderboards — verified
- [x] Backend: Profile map (`GET /api/user/profile/map`) — verified
- [x] Backend: Upload stats (`GET /api/v3/user/photos/stats`) — verified
- [ ] Mobile: Public profile screen (navigate from leaderboard when `public_profile=true`)
- [ ] Mobile: Location-scoped leaderboards (add `locationType` + `locationId` picker)
- [ ] Mobile: Achievements display (data in profile/index: `achievements.unlocked`, `achievements.total`)
- [ ] Mobile: User photo map — **coordinates are `[lat, lon]` not `[lon, lat]`**
- [ ] Mobile: Upload stats in profile (call `GET /api/v3/user/photos/stats`)
