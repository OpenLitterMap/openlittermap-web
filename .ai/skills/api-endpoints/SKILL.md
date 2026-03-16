---
name: api-endpoints
description: REST API endpoints, route structure, auth guards, request/response contracts, error patterns, and the full API surface for web SPA and mobile clients.
---

# API Endpoints

All API routes live in `routes/api.php`. The API serves two clients: the Vue 3 SPA (session auth) and a mobile app (Bearer token auth). Current version is v3. All legacy v1/v2 endpoints have been removed (2026-03-01).

## Key Files

- `routes/api.php` — All route definitions (~146 active routes)
- `readme/API.md` — **Comprehensive endpoint documentation** with request/response contracts for every endpoint (2080+ lines). READ THIS FIRST before modifying any API endpoint.
- `app/Http/Controllers/Auth/AuthTokenController.php` — Mobile token login (Sanctum)
- `app/Http/Controllers/Auth/LoginController.php` — SPA session login
- `app/Http/Controllers/Auth/RegisterController.php` — User registration (both clients)
- `app/Http/Controllers/Uploads/UploadPhotoController.php` — v3 upload (web EXIF + mobile explicit coords)
- `app/Http/Controllers/API/Tags/PhotoTagsController.php` — v3 tag CRUD (POST add, PUT replace)
- `app/Http/Controllers/API/Tags/GetTagsController.php` — Public tag catalog
- `app/Http/Controllers/User/ProfileController.php` — Profile (auth + public)
- `app/Http/Controllers/User/Photos/UsersUploadsController.php` — v3 user photos (paginated, filterable)
- `app/Http/Controllers/Leaderboard/LeaderboardController.php` — Leaderboard (time-filtered, location-scoped)
- `app/Http/Controllers/Points/PointsController.php` — Map points GeoJSON
- `app/Http/Controllers/Points/PointsStatsController.php` — Map viewport stats
- `app/Http/Controllers/Clusters/ClusterController.php` — Map clusters GeoJSON (ETag cached)
- `app/Http/Controllers/Location/LocationController.php` — Location hierarchy API
- `app/Http/Controllers/Location/TagController.php` — Location tag analytics
- `app/Http/Controllers/API/TeamsController.php` — Team CRUD + membership
- `app/Http/Controllers/Teams/TeamPhotosController.php` — Team photo management + school approval
- `app/Http/Controllers/Teams/TeamsLeaderboardController.php` — Teams leaderboard
- `app/Http/Controllers/Teams/TeamsClusterController.php` — Team map clusters/points
- `app/Http/Controllers/API/DeleteAccountController.php` — GDPR account deletion
- `app/Http/Controllers/UsersController.php` — Settings, privacy toggles, details
- `app/Http/Controllers/ApiSettingsController.php` — Privacy settings (maps/leaderboard/createdby)
- `app/Http/Controllers/Achievements/AchievementsController.php` — User achievements
- `app/Http/Controllers/API/GlobalStatsController.php` — World totals (total_tags, total_images, total_users, new_users_*)
- `app/Http/Controllers/CommunityController.php` — Community stats
- `app/Http/Controllers/Cleanups/` — 4 cleanup controllers (create, list, join, leave)
- `app/Http/Requests/` — Form request validation classes (one per endpoint that needs validation)

## Route Groups

| Prefix | Middleware | Purpose |
|--------|-----------|---------|
| `/api/v3` | `auth:sanctum` | Current API (upload, tags, user photos) |
| `/api` (public) | none | Tags catalog, points, stats, locations, clusters, leaderboard |
| `/api/auth` | varies | Login, register, logout, password reset |
| `/api` (auth) | `auth:sanctum` | Profile, settings, achievements |
| `/api/teams` | `auth:sanctum` | Team CRUD, photos, approval, leaderboard |
| `/api/admin` | `admin` | Admin queue, verify, reset |
| `/api/bbox` | `can_bbox` | Bounding box annotation |
| `/api/participant` | varies | School participant session endpoints |

## Invariants

1. **Dual auth: session + token.** All `auth:sanctum` routes accept both SPA session cookies and mobile Bearer tokens. The SPA uses `POST /api/auth/login` (session), mobile uses `POST /api/auth/token` (Sanctum token).
2. **Token name is `mobile`.** `AuthTokenController` creates tokens named `mobile` and revokes previous `mobile` tokens on each login (prevents buildup).
3. **Registration always sets `name = NULL`.** Ignores any `name` field in the request. Auto-generates username if omitted (pattern: `{adjective}-{noun}-{number}`).
4. **`readme/API.md` is the source of truth.** Every endpoint's exact request/response contract is documented there. Always consult it before modifying an endpoint.
5. **Error responses are inconsistent.** Some controllers return `{ "msg": "..." }`, others return `{ "message": "..." }`. This is a known legacy issue — match the existing pattern for each controller.
6. **Public endpoints must filter `is_public = true`.** All map/points/global/community endpoints use `Photo::public()` scope or explicit `where('is_public', true)`.
7. **v3 is the current API version.** New endpoints go in the v3 group. All legacy v1/v2 endpoints have been removed.
8. **Location API uses `locations`/`location_type` keys.** Not `children`/`children_type`. The `{type}` parameter accepts `country`, `state`, or `city`.
9. **Consistent API field naming convention.** All list/leaderboard endpoints (teams, locations, global stats) use: `total_tags`, `total_photos`, `total_members`, `created_at`, `updated_at`. Never use old names like `total_litter`, `total_images`, `tags`, `photos`, `contributors`.
9. **Points API returns `page` (not `current_page`).** Frontend normalizes to `current_page` in `pointsHelper.getPaginationData()`.
10. **Delete account is GDPR-compliant.** Photos are preserved as anonymous contributions (`user_id` set to NULL via DB CASCADE). Redis leaderboards and per-user metrics are cleaned up.

## Patterns

### Auth flow (Mobile)

```
POST /api/auth/token  →  { token, user }
// Include on all subsequent requests:
Authorization: Bearer <token>
// Validate token is still valid:
POST /api/validate-token  →  { message: "valid" }
```

### Auth flow (SPA)

```
GET /sanctum/csrf-cookie        →  Sets XSRF-TOKEN cookie
POST /api/auth/login            →  Sets session cookie, returns { success, user }
GET /api/user/profile/index     →  Returns user + stats + level + rank
POST /api/auth/logout           →  Destroys session
```

### Photo lifecycle endpoints

```
POST  /api/v3/upload              →  Upload photo (web: EXIF; mobile: explicit lat/lon/date)
GET   /api/v3/user/photos         →  List user's photos (paginated, filterable, per_page up to 100)
                                      Response includes `is_public` (boolean) and `school_team` (boolean)
                                      for each photo. Owner sees all photos including private ones.
GET   /api/v3/user/photos/stats   →  Aggregate counts (totalPhotos, totalTags, leftToTag)
POST  /api/v3/tags                →  Add tags to untagged photo
PUT   /api/v3/tags                →  Replace all tags on tagged photo (edit mode, accepts empty tags: [])
PATCH /api/v3/photos/{id}/visibility →  Toggle is_public for a single photo (owner only, auth:sanctum).
                                      Returns { is_public: bool }. Blocked for school team photos (403).
                                      PhotoObserver marks dirty tiles on change.
POST  /api/profile/photos/delete  →  Delete single photo { "photoid": 123 } (soft delete)
```

### Leaderboard query parameters

```
GET /api/leaderboard?timeFilter=all-time&locationType=country&locationId=1&page=1
// timeFilter: all-time | today | yesterday | this-month | last-month | this-year | last-year
// locationType: country | state | city (optional)
// locationId: numeric ID (required if locationType set)
// All time filters use MySQL metrics table. Per page hardcoded to 100.
// Public endpoint (no auth required). Optional auth adds currentUserRank.
```

### Team photo management (school teams)

```
GET    /api/teams/photos?team_id=X&status=pending   →  List photos (with new_tags CLO format)
GET    /api/teams/photos/{photo}?team_id=X           →  Single photo (with new_tags)
GET    /api/teams/photos/member-stats?team_id=X      →  Per-student stats (leader only, safeguarding)
GET    /api/teams/photos/map?team_id=X               →  Map points (up to 5000)
POST   /api/teams/photos/approve   { photo_ids, team_id }  →  Approve (fires TagsVerifiedByAdmin)
POST   /api/teams/photos/revoke    { photo_ids, team_id }  →  Revoke approval (reverses metrics)
PATCH  /api/teams/photos/{photo}/tags  { tags: [...] }     →  Edit tags (CLO format, leader/school_manager)
DELETE /api/teams/photos/{photo}?team_id=X                  →  Delete (reverses metrics first)

# Participant management (auth:sanctum, team leader only)
GET    /api/teams/{team}/participants                       →  List slots with photo_count
POST   /api/teams/{team}/participants  { count: N }        →  Create slots in bulk
POST   /api/teams/{team}/participants/{id}/deactivate       →  Revoke session
POST   /api/teams/{team}/participants/{id}/activate         →  Re-enable session
POST   /api/teams/{team}/participants/{id}/reset-token      →  Regenerate token (returns new token)
DELETE /api/teams/{team}/participants/{id}                   →  Hard delete slot

# Participant session (public/token auth)
POST   /api/participant/session  { token: "64-char" }      →  Validate token, return session info (public)
POST   /api/participant/upload                              →  Upload photo (X-Participant-Token header)
POST   /api/participant/tags     { photo_id, tags }        →  Tag own photo (X-Participant-Token header)
GET    /api/participant/photos                              →  List own photos (X-Participant-Token header)
DELETE /api/participant/photos/{photo}                       →  Delete own pre-approval photo
```

### Public profile (no auth required)

```
GET /api/user/profile/{id}
// Returns stats, level, rank, achievements, recent locations
// Respects privacy: returns { public: false } if profile is private
// Respects show_name/show_username flags
```

### User photos filtering

```
GET /api/v3/user/photos?tagged=false&per_page=100&page=1&picked_up=true
// tagged: true | false (omit for all)
// picked_up: true | false (omit for all) — filters by photo-level picked_up status
// per_page: 1-100 (default 8)
// Untagged = WHERE summary IS NULL (NOT doesntHave('photoTags'))
// Returns: { photos: [...], pagination: { current_page, last_page, per_page, total }, user }
```

Response includes `picked_up` (boolean, never null) and `remaining` (deprecated inverse). Use `picked_up`. Also includes `is_public` (boolean) and `school_team` (boolean) — use these to show visibility state and to gate the per-photo toggle (`PATCH /api/v3/photos/{id}/visibility` is blocked for `school_team = true`).

**`new_tags` response shape:** Each tag includes `category_litter_object_id`, `litter_object_type_id`, `quantity`, `picked_up` (bool, cast with fallback to photo-level), `category` (object or null), `object` (object or null), `extra_tags` (array). For loose/extra-tag-only tags, `category`, `object`, and `category_litter_object_id` are null. `filename` field on photo is a full URL, usable directly as image source.

**PUT /api/v3/tags accepts empty tags.** `ReplacePhotoTagsRequest` validates `tags` as `present|array` (not `required|array|min:1`). Sending `tags: []` clears all tags from a photo (resets summary, XP, verified to untagged state).

### GeoJSON response format (points/clusters)

```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "geometry": { "type": "Point", "coordinates": [lon, lat] },
      "properties": { "id": 1, "summary": {...}, "username": "..." }
    }
  ]
}
```

### ETag caching (clusters)

```
GET /api/clusters?zoom=5&bbox=-180,-90,180,90
// Response includes ETag header
// Client sends If-None-Match on next request
// Returns 304 Not Modified if unchanged
```

## Common Mistakes

- **Not reading `readme/API.md` before modifying an endpoint.** The full request/response contract is documented there. Changing a response shape without updating the docs breaks the mobile agent.
- **Using `auth:api` instead of `auth:sanctum`.** Passport guards (`auth:api`) are legacy. New routes use `auth:sanctum` which supports both session and token auth.
- **Returning `'tags'` instead of `'new_tags'` from UsersUploadsController.** The frontend reads `photo.new_tags` for tag counts. Wrong key = broken UI.
- **Omitting `litter_object_type_id` from `new_tags` response.** `UsersUploadsController::getNewTags()` must include it so the frontend can preserve the type dimension on edit round-trips.
- **Forgetting to update `readme/API.md` when changing an endpoint.** The API docs must stay in sync with actual controller behavior.
- **Adding public endpoints without `is_public` filtering.** Any query that returns photo data to unauthenticated users MUST use `Photo::public()` scope.
- **Comparing VerificationStatus enum to int in controllers.** Use `->value` for comparisons: `$photo->verified->value >= VerificationStatus::ADMIN_APPROVED->value`.
- **Including `geom` in API responses.** Binary spatial data — keep it in Photo model's `$hidden` array.
- **Using `doesntHave('photoTags')` or `WHERE verified = 0` for untagged filter.** Use `whereNull('summary')` — summary is set by GeneratePhotoSummaryService when tags are added, regardless of verification status.
- **Assuming consistent error response keys.** Some controllers use `msg`, others use `message`. Check the specific controller before asserting response keys in tests.
- **Missing `team_id` query parameter on team endpoints.** Most team endpoints require `?team_id=X` — forgetting it returns 422 or wrong team's data.
- **Not handling the `flag` field on points responses.** `GET /api/points/{id}` returns a `flag` field from the user's settings. Mobile clients display this.
- **Using removed legacy endpoints.** All v1/v2 endpoints (`/api/photos/submit`, `/api/add-tags`, `/api/v2/*`) were removed 2026-03-01. Mobile uses v3 endpoints only.
- **Using old category/object strings in team tag edits.** `PATCH /api/teams/photos/{photo}/tags` uses CLO format (`category_litter_object_id`), same as `POST /api/v3/tags`. Not the old `{ category, object }` string format.
- **Forgetting `new_tags` in team photo responses.** Both `index()` and `show()` return `new_tags` with CLO IDs + extra_tags for the facilitator queue tag editor.
- **Reading `remaining` instead of `picked_up`.** `remaining` is deprecated (inverse boolean). Use `picked_up` (boolean, never null at photo level). Per-tag `new_tags[].picked_up` is separate and nullable (true/false/null).
- **Sending Unix milliseconds for upload `date` field.** Backend expects **seconds**: `Carbon::createFromTimestamp((int) $dateInput)`. JS `Date.now()` returns milliseconds — divide by 1000.
- **Wrong delete param name.** `POST /api/profile/photos/delete` expects `photoid` (all lowercase, no underscore). Not `photoId` or `photo_id`.
