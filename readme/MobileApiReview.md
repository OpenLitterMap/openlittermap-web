# Mobile API Review

Comprehensive review of all API endpoints the React Native mobile app should consume. All endpoints use `auth:sanctum` which accepts Bearer tokens — no CSRF, no sessions needed.

**Auth header:** `Authorization: Bearer <sanctum_token>`

---

## Auth

| Method | Route | Status | Notes |
|--------|-------|--------|-------|
| POST | `/api/auth/register` | **Use** | Returns `{ token, user }`. Sanctum token + auto-login |
| POST | `/api/auth/token` | **Use** | Mobile login. Accepts `identifier`, `email`, OR `username`. Revokes existing mobile tokens. Returns `{ token, user }`. Throttled 5/min |
| POST | `/api/validate-token` | **Use** | Check if stored token is valid. Returns `{ message: 'valid' }` or 401 |
| POST | `/api/password/email` | **Use** | Password reset email. Throttled 3/min |
| POST | `/api/password/validate-token` | **Use** | Validate reset token |
| POST | `/api/password/reset` | **Use** | Complete password reset |
| GET | `/api/user` | **Deprecated** | Expensive table scan for `position`. Use `GET /api/user/profile/index` instead |
| POST | `/api/auth/login` | **SPA only** | Uses web session guard — does not work with Bearer tokens |
| POST | `/api/auth/logout` | **SPA only** | Uses web session guard |

---

## Photo Upload

| Method | Route | Status | Notes |
|--------|-------|--------|-------|
| POST | `/api/photos/submit` | **Use** | Upload only (no tags). Body: `photo` (file), `lat`, `lon`, `date`, optional `model`. Returns `{ success, photo_id }` |
| POST | `/api/photos/submit-with-tags` | **Use** | Upload + optional v4 tags. V4 tags auto-converted via `ConvertV4TagsAction` |
| POST | `/api/v3/upload` | **SPA only** | Reads GPS from EXIF — requires EXIF GPS data. Not suitable when mobile sends explicit coordinates |

**Recommended flow:** Use `POST /api/photos/submit` for upload, then `POST /api/v3/tags` to add tags separately.

---

## Tagging

| Method | Route | Status | Notes |
|--------|-------|--------|-------|
| POST | `/api/v3/tags` | **Use (v5)** | Modern CLO format. Ownership + verification gate. See payload format below |
| PUT | `/api/v3/tags` | **Use (v5)** | Replace all tags atomically. Ownership check only (allows re-tagging verified photos) |
| POST | `/api/add-tags` | **Legacy v4** | V4 format with `ConvertV4TagsAction` shim. Still works but should migrate to v3 |
| POST | `/api/v2/add-tags-to-uploaded-image` | **Legacy v4** | Same shim, different path |

### V5 Tag Payload (`POST /api/v3/tags`)

```json
{
    "photo_id": 123,
    "tags": [
        {
            "category_litter_object_id": 45,
            "litter_object_type_id": null,
            "quantity": 3,
            "picked_up": true,
            "materials": [{ "id": 16, "quantity": 3 }],
            "brands": [{ "id": 12, "quantity": 2 }],
            "custom_tags": ["dirty-bench"]
        }
    ]
}
```

Key fields:
- `category_litter_object_id` — FK to `category_litter_object` pivot (get from `GET /api/tags/all`)
- `litter_object_type_id` — nullable, for type dimension (e.g., "recyclable", "non-recyclable")
- `materials`, `brands`, `custom_tags` — optional extra tags per item
- `picked_up` — boolean, awards 5 bonus XP

### V4 Tag Payload (legacy — `POST /api/add-tags`)

```json
{
    "photo_id": 123,
    "tags": {
        "smoking": { "butts": 5, "lighters": 2 },
        "brands": { "marlboro": 3 }
    },
    "picked_up": true,
    "custom_tags": ["my_tag"]
}
```

### Building the Tag Search Index

`GET /api/tags/all` (public, no auth) returns flat arrays for building a local search index:

```json
{
    "categories": [{ "id": 1, "key": "smoking" }, ...],
    "objects": [{ "id": 1, "key": "butts" }, ...],
    "materials": [{ "id": 1, "key": "plastic" }, ...],
    "brands": [{ "id": 1, "key": "marlboro" }, ...],
    "types": [{ "id": 1, "key": "recyclable" }, ...],
    "category_objects": [{ "id": 1, "category_id": 1, "litter_object_id": 1 }, ...],
    "category_object_types": [{ "category_litter_object_id": 1, "litter_object_type_id": 1 }, ...]
}
```

Use `category_objects[].id` as `category_litter_object_id` when submitting tags.

---

## Photo Listing & Queue

| Method | Route | Status | Notes |
|--------|-------|--------|-------|
| GET | `/api/v2/photos/get-untagged-uploads` | **Use** | Untagged queue (no platform filter — returns all untagged). Returns `{ count, photos[] }` with `id`, `filename`, `remaining`. Paginated (100/page) |
| GET | `/api/v3/user/photos` | **Use** | Full photo list with v5 tags. Filters: `tagged`, `id`, `tag`, `custom_tag`, `date_from`, `date_to`. Tags returned as `new_tags` key |
| GET | `/api/v3/user/photos/stats` | **Use** | Returns `totalPhotos`, `totalTags`, `leftToTag`, `taggedPercentage` |
| GET | `/api/check-web-photos` | **Deprecated** | Superseded by `get-untagged-uploads` |
| GET | `/api/v2/photos/web/load-more` | **Deprecated** | Uses wrong column name. Do not use |

---

## Photo Deletion

| Method | Route | Status | Notes |
|--------|-------|--------|-------|
| DELETE | `/api/photos/delete` | **Use** | Body: `{ "photoId": 123 }`. Reverses metrics, deletes S3 files, soft-deletes. Returns `{ success: true }` |

---

## User Profile

| Method | Route | Status | Notes |
|--------|-------|--------|-------|
| GET | `/api/user/profile/index` | **Use** | Full dashboard: `user`, `stats`, `level`, `rank`, `global_stats`, `achievements`, `locations`, `team` |
| GET | `/api/user/profile/{id}` | **Use** | Public profile (no auth). Returns public data or `{ public: false }` |
| GET | `/api/user/profile/map` | **Use** | GeoJSON of user's verified photos |
| GET | `/api/user/profile/download` | **Use** | Triggers async CSV export via email |
| GET | `/api/user` | **Deprecated** | Expensive. Use `profile/index` |
| GET | `/api/current-user` | **Deprecated** | Uses web session guard. Will 401 with Bearer token |

---

## Settings

| Method | Route | Status | Notes |
|--------|-------|--------|-------|
| POST | `/api/settings/update` | **Use** | Key/value: `{ key, value }`. Whitelist: `name`, `username`, `email`, `global_flag`, `picked_up`, `previous_tags`, `emailsub`, `public_profile`. Legacy `items_remaining` accepted (auto-inverted to `picked_up`) |
| POST | `/api/settings/privacy/maps/name` | **Use** | Toggle `show_name_maps` |
| POST | `/api/settings/privacy/maps/username` | **Use** | Toggle `show_username_maps` |
| POST | `/api/settings/privacy/leaderboard/name` | **Use** | Toggle `show_name` |
| POST | `/api/settings/privacy/leaderboard/username` | **Use** | Toggle `show_username` |
| POST | `/api/settings/privacy/createdby/name` | **Use** | Toggle |
| POST | `/api/settings/privacy/createdby/username` | **Use** | Toggle |
| POST | `/api/settings/delete-account` | **Use** | Requires `{ password }` confirmation |
| POST | `/api/settings/details` | **Avoid** | `UsersController` uses web session guard internally |
| PATCH | `/api/settings/details/password` | **Avoid** | Same web guard issue |
| POST | `/api/settings/privacy/update` | **Avoid** | Same web guard issue |

**Rule:** Use `POST /api/settings/update` with the key/value pattern for all settings changes. Avoid `UsersController` endpoints — they have a latent web guard conflict.

---

## Teams

All team routes use `auth:sanctum` and work with Bearer tokens.

| Method | Route | Notes |
|--------|-------|-------|
| GET | `/api/teams/types` | Public — team type list |
| GET | `/api/teams/list` | Paginated team list. Returns `total_tags`, `total_images`, `total_members` |
| GET | `/api/teams/leaderboard` | Team XP leaderboard |
| GET | `/api/teams/members` | Members of a team |
| GET | `/api/teams/data` | Team details |
| GET | `/api/teams/joined` | Teams the current user belongs to |
| POST | `/api/teams/create` | Create team. Body: `name`, `identifier`, `team_type_id` |
| POST | `/api/teams/join` | Join team. Body: `identifier` |
| POST | `/api/teams/leave` | Leave team. Body: `team_id` |
| POST | `/api/teams/active` | Set active team. Body: `team_id` |
| PATCH | `/api/teams/update/{team}` | Update team details |

---

## Leaderboard & Global Stats (Public, No Auth)

| Method | Route | Notes |
|--------|-------|-------|
| GET | `/api/leaderboard` | Params: `timeFilter` (all-time, today, yesterday, this-month, last-month, this-year, last-year), `locationType`, `locationId`, `page`. Returns `users[]` with `user_id`, `name`, `username`, `xp`, `global_flag`, `rank`, `public_profile` |
| GET | `/api/global/stats-data` | Returns `total_tags`, `total_images`, `total_users`, `new_users_last_24_hours`, `new_users_last_7_days`, `new_users_last_30_days`, `new_tags_last_24_hours`, `new_tags_last_7_days`, `new_tags_last_30_days`, `new_photos_last_24_hours`, `new_photos_last_7_days`, `new_photos_last_30_days` |
| GET | `/api/levels` | XP threshold config for level display |
| GET | `/api/achievements` | `auth:sanctum` — user's unlocked achievements |

---

## Map Data (Public, No Auth)

| Method | Route | Notes |
|--------|-------|-------|
| GET | `/api/clusters` | Map clusters GeoJSON. Params: `bbox`, `zoom` |
| GET | `/api/points` | Paginated photos within bbox. Params: `bbox`, `zoom`, `page`, `per_page` |
| GET | `/api/points/{id}` | Single photo by ID |

---

## Locations (Public, No Auth)

| Method | Route | Notes |
|--------|-------|-------|
| GET | `/api/locations/global` | Global stats |
| GET | `/api/locations/{type}` | List countries/states/cities. Type: `country`, `state`, `city` |
| GET | `/api/locations/{type}/{id}` | Location detail with children |
| GET | `/api/locations/{type}/{id}/leaderboard` | Location-scoped leaderboard |
| GET | `/api/locations/{type}/{id}/categories` | Tag category breakdown |
| GET | `/api/locations/{type}/{id}/timeseries` | Time series data |
| GET | `/api/locations/{type}/{id}/tags/top` | Top tags |
| GET | `/api/locations/{type}/{id}/tags/summary` | Tag summary |

---

## Utility

| Method | Route | Notes |
|--------|-------|-------|
| GET | `/api/mobile-app-version` | Public. Returns current version + store URLs |
| GET | `/api/tags` | Public. Nested tag structure with filters |
| GET | `/api/tags/all` | Public. Flat arrays for building search index |

---

## Migration Checklist

Priority order for modernizing the mobile app:

### Phase 1 (Critical — Do Now)
- [ ] Replace `GET /api/user` with `GET /api/user/profile/index`
- [ ] Confirm using `POST /api/auth/token` for login (not `/api/auth/login`)
- [ ] Confirm `DELETE /api/photos/delete` for photo deletion

### Phase 2 (Settings)
- [ ] Replace any `UsersController` settings calls with `POST /api/settings/update`
- [ ] Use individual privacy toggle endpoints for privacy settings

### Phase 3 (Tagging — When Ready)
- [ ] Build local tag search index from `GET /api/tags/all`
- [ ] Switch from v4 `POST /api/add-tags` to v5 `POST /api/v3/tags`
- [ ] Implement tag editing via `PUT /api/v3/tags`
- [ ] Untagged queue returns all photos (platform filter removed — web and mobile photos in same queue)

### Phase 4 (Enhanced Features)
- [ ] Add team management UI
- [ ] Add leaderboard display
- [ ] Add location browsing
- [ ] Add achievement display
- [ ] Add public profile viewing

---

## Key Differences: V4 vs V5 Tags

| Aspect | V4 (legacy) | V5 (modern) |
|--------|-------------|-------------|
| Endpoint | `POST /api/add-tags` | `POST /api/v3/tags` |
| Format | Category dict: `{ "smoking": { "butts": 5 } }` | CLO array: `[{ "category_litter_object_id": 45, "quantity": 3 }]` |
| Materials | Not supported | Per-tag: `materials: [{ id, quantity }]` |
| Brands | Top-level category: `{ "brands": { "marlboro": 3 } }` | Per-tag: `brands: [{ id, quantity }]` |
| Custom tags | Top-level array: `custom_tags: ["tag"]` | Per-tag: `custom_tags: ["tag"]` |
| Type dimension | Not supported | `litter_object_type_id` (recyclable, etc.) |
| Edit/Replace | Not supported | `PUT /api/v3/tags` replaces atomically |
| Backend | `ConvertV4TagsAction` shim → v5 | Direct `AddTagsToPhotoAction` |

The v4 shim will continue working indefinitely but v5 is more expressive (materials per item, type dimension, edit support).
