# User Profile & Settings

## Overview

The profile and settings system provides user dashboard stats, photo management, account settings, and privacy controls. The frontend is a single `/profile` page with tab navigation (Dashboard, Photos, Settings).

**Auth guard:** `auth:sanctum` (supports both session cookies from SPA and API tokens from mobile).

---

## Frontend

### Routes

| Path | Component | Behavior |
|------|-----------|----------|
| `/profile` | `Profile.vue` | Tab container with `?tab=` query param (dashboard, photos, settings) |
| `/profile/:id` | `PublicProfile.vue` | Public profile view (no auth required). Shows stats if `public_profile=true`, "private" empty state otherwise. |
| `/settings` | — | Redirects to `/profile?tab=settings` |

### Components

| Component | Purpose |
|-----------|---------|
| `views/Profile/Profile.vue` | Tab container, fetches profile data on mount |
| `views/Profile/components/ProfileDashboard.vue` | Level card, stats grid, rank, achievements, locations, global stats |
| `views/Profile/components/ProfilePhotos.vue` | Upload count, links to /uploads, /upload, /tag |
| `views/Profile/components/ProfileSettings.vue` | Account fields, preference toggles, privacy toggles, account deletion |
| `views/Profile/components/SettingsField.vue` | Reusable inline-editable text field |
| `views/Profile/components/SettingsToggle.vue` | Reusable toggle switch |
| `views/Profile/PublicProfile.vue` | Public profile page (level, stats, rank, achievements, locations) |

### Pinia Stores

| Store | Actions |
|-------|---------|
| `stores/profile.js` | `FETCH_PROFILE()` — fetches `/api/user/profile/index` |
| `stores/settings.js` | `UPDATE_SETTING(key, value)`, `TOGGLE_PRIVACY(endpoint)`, `DELETE_ACCOUNT(password)` |

### Nav.vue

- Admin links (`Admin - Queue`, `Admin - Redis`) gated by `isAdmin` computed (checks `userStore.admin` + `user.roles` array)
- `/settings` link removed (accessed via Profile page)
- Profile link points to `/profile`

---

## Backend API

### Public Profile Endpoint (no auth)

```
GET  /api/user/profile/{id}           ProfileController@show
```

Returns `{ public: true, user, stats, level, rank, achievements, locations }` if `public_profile=true`. Returns `{ public: false }` if private. Respects `show_name`/`show_username` privacy settings. Returns 404 for nonexistent users.

### Profile Endpoints (`auth:sanctum` group)

```
GET  /api/user/profile/index          ProfileController@index    (full profile — dashboard, stats, rank, achievements, locations)
GET  /api/user/profile/refresh        ProfileController@refresh  (lightweight — user fields, XP, level only; used by REFRESH_USER on app load)
GET  /api/user/profile/map            ProfileController@geojson
GET  /api/user/profile/download       ProfileController@download
GET  /api/user/profile/photos/index   UserPhotoController@index
GET  /api/user/profile/photos/filter  UserPhotoController@filter
GET  /api/user/profile/photos/previous-custom-tags  UserPhotoController@previousCustomTags
POST /api/user/profile/photos/tags/bulkTag          UserPhotoController@bulkTag
POST /api/user/profile/photos/delete  UserPhotoController@destroy
POST /api/profile/photos/remaining/{id}  PhotosController@remaining
POST /api/profile/photos/delete       PhotosController@deleteImage
POST /api/profile/upload-profile-photo   UsersController@uploadProfilePhoto (disabled — 501)
```

### Settings Endpoints (`auth:api` group — mobile)

```
POST  /api/settings/details                       UsersController@details
PATCH /api/settings/details/password              UsersController@changePassword
POST  /api/settings/privacy/update                UsersController@togglePrivacy
POST  /api/settings/phone/submit                  UsersController@phone
POST  /api/settings/phone/remove                  UsersController@removePhone
POST  /api/settings/toggle                        UsersController@togglePresence
POST  /api/settings/email/toggle                  EmailSubController@toggleEmailSub
GET   /api/settings/flags/countries               SettingsController@getCountries
POST  /api/settings/save-flag                     SettingsController@saveFlag
PATCH /api/settings                               SettingsController@update (social links)
```

### Privacy Toggle Endpoints (`auth:api` — individual toggles)

```
POST /api/settings/privacy/maps/name              ApiSettingsController@mapsName
POST /api/settings/privacy/maps/username          ApiSettingsController@mapsUsername
POST /api/settings/privacy/leaderboard/name       ApiSettingsController@leaderboardName
POST /api/settings/privacy/leaderboard/username   ApiSettingsController@leaderboardUsername
POST /api/settings/privacy/createdby/name         ApiSettingsController@createdByName
POST /api/settings/privacy/createdby/username     ApiSettingsController@createdByUsername
POST /api/settings/privacy/toggle-previous-tags   ApiSettingsController@togglePreviousTags
```

### General Setting Update (`auth:api`)

```
POST /api/settings/update    ApiSettingsController@update
```

Whitelist-validated key/value endpoint. Allowed keys: `name`, `username`, `email`, `global_flag`, `picked_up`, `previous_tags`, `emailsub`, `public_profile`. Unique checks on `email` and `username`. Legacy mobile: `items_remaining` key remaps to `picked_up` (inverted boolean).

### Account Deletion (`auth:api`)

```
POST /api/settings/delete-account    DeleteAccountController
```

Password-confirmed. Cleans up: AdminVerificationLog, cleanups, location ownership, roles, OAuth tokens, payments (reassigned), subscriptions, team_user, teams. Redis cleanup: user stats hash, tags hash, bitmap, XP/contributor ZSETs across all location scopes. Photos preserved.

---

## ProfileController@index Response

```json
{
    "user": { "id", "name", "username", "avatar", "created_at", "global_flag", "public_profile" },
    "stats": { "uploads", "litter", "xp", "streak" },
    "level": { "level", "title", "xp", "xp_into_level", "xp_for_next", "xp_remaining", "progress_percent" },
    "rank": { "global_position", "global_total", "percentile" },
    "global_stats": { "total_photos", "total_tags" },
    "achievements": { "unlocked", "total" },
    "locations": { "countries", "states", "cities" }
}
```

**Photo visibility scope:** Own-user queries (`index()`, `geojson()`, location counts) include ALL of the user's photos, including private ones (`is_public = false`). The public profile `show()` endpoint only counts and exposes public photos.

| Field | Source |
|-------|--------|
| `stats` | `resolveUserStats()` — Redis `getUserMetrics()` with uploads fallback to `Photo::where('user_id', $id)->count()` instead of stale `users.total_images` column. Shared by `index()` and `show()`. |
| `level` | `LevelService::getUserLevel($xp)` |
| `rank` | `getGlobalRank()` — Redis `ZREVRANK` on `{g}:lb:xp`, fallback to `users.xp` count |
| `global_stats` | Cached 5 min — metrics aggregate row (user_id=0), fallback to photo count |
| `achievements` | DB counts on `user_achievements` + cached `achievements` count |
| `locations` | Cached 5 min — `Photo::where(user_id)` distinct country/state/city counts (keyed by photo count for auto-invalidation) |

---

## Level System

Config-driven 12-level threshold system in `config/levels.php`.

- **Thresholds:** Flat XP values (0, 100, 500, 1000, 5000, ... 1,000,000)
- **Service:** `LevelService::getUserLevel(int $xp)` returns level info array
- **Titles:** From "Complete Noob" (level 1) to "SuperIntelligent LitterMaster" (level 12)

---

## Users Table — Settings/Profile Columns

### Identity

| Column | Type | Default | Purpose |
|--------|------|---------|---------|
| `name` | varchar(255) | — | Display name |
| `username` | varchar(255) | — | Unique handle |
| `email` | varchar(255) | — | Login credential |
| `avatar` | varchar(255) | `default.jpg` | Profile image |
| `public_profile` | tinyint | 0 | Allow others to see profile |

### Privacy (6 global toggles)

| Column | Type | Default | Purpose |
|--------|------|---------|---------|
| `show_name` | tinyint | 0 | Show name on leaderboards |
| `show_username` | tinyint | 0 | Show username on leaderboards |
| `show_name_maps` | tinyint | 0 | Show name on maps |
| `show_username_maps` | tinyint | 0 | Show username on maps |
| `show_name_createdby` | tinyint | 0 | Show name in location "Created By" |
| `show_username_createdby` | tinyint | 0 | Show username in location "Created By" |
| `prevent_others_tagging_my_photos` | tinyint | 0 | Opt out of admin tagging |

### Preferences

| Column | Type | Default | Purpose |
|--------|------|---------|---------|
| `picked_up` | tinyint | 1 | Default "picked up" state (true = litter was picked up) |
| `public_photos` | boolean | `true` | Default visibility for new photo uploads |
| `previous_tags` | int | 0 | Show previous tags when tagging |
| `emailsub` | int unsigned | 1 | Email subscription |
| `global_flag` | varchar(255) | null | Country flag for leaderboard |
| `active_team` | int unsigned | null | FK to active team |
| `settings` | json | null | Social links JSON bag |

### `settings` JSON Keys (social links)

`social_twitter`, `social_facebook`, `social_instagram`, `social_linkedin`, `social_reddit`, `social_personal`

Accessed via `$user->setting('key')` and `$user->settings(['key' => 'value'])`. Exposed as `social_links` appended attribute.

---

## Test Coverage

| File | Tests | Covers |
|------|-------|--------|
| `tests/Feature/User/ProfileIndexTest.php` | 4 | Response structure, auth required, location counts, rank total = full user count |
| `tests/Feature/User/PublicProfileTest.php` | 4 | Public profile data, private returns `public: false`, privacy settings respected, 404 for nonexistent |
| `tests/Feature/User/ProfileGeojsonTest.php` | 1 | Only verified >= ADMIN_APPROVED photos returned |
| `tests/Feature/User/SettingsProfileTest.php` | 10 | Mass assignment blocked, allowed updates, key remapping, public_profile, old routes 404, validation, duplicate email |
| `tests/Feature/User/DeleteAccountTest.php` | 4 | Redis cleanup (keys + rankings), location-scoped cleanup, photo preservation, wrong password rejection |
| `tests/Unit/Services/LevelServiceTest.php` | 7 | Level 1-3 boundaries, partial progress, high XP, max level cap at 12, all keys present |

---

## Bugs Fixed (Session 13-14)

| # | Bug | Fix |
|---|-----|-----|
| 1 | Mass assignment vulnerability in `ApiSettingsController@update` | Whitelist of 8 allowed keys with per-key validation |
| 2 | `updateSecurity` wrote to non-existent `first_name`/`user_name` columns | Method and route removed |
| 3 | Old `destroy` had no cleanup | Route removed (use `DeleteAccountController` instead) |
| 4 | Profile photo upload broken | Disabled with 501 |
| 5 | `removePhone` set `''` instead of `null` | Fixed to `null` |
| 6 | No Redis cleanup on account deletion | Added `cleanupRedis()` method |
| 7 | `geojson()` used `verified = 2` | Changed to `>= ADMIN_APPROVED->value` |
| 8 | `UserPhotoController@index` queried `verification = 0` | Changed to `verified = UNVERIFIED->value` |
| 9 | No `/profile` or `/settings` Vue routes | Added routes, built Profile.vue |
| 10 | Admin links shown to all users | Gated by `isAdmin` role check |
| 11 | Profile routes used `auth:api` (Passport only) | Changed to `auth:sanctum` (session + token) |

## Bugs Fixed (Session 15 — User Journey Audit)

| # | Bug | Fix |
|---|-----|-----|
| 1 | Uploads page double-fetched on mount | Removed `onMounted` from `Uploads.vue` (UploadsHeader already handles initial fetch) |
| 2 | UploadsPagination lost filters on page change | Fixed `fetchPhotosOnly(page)` — no longer passes `perPage` as filters arg |
| 3 | No empty state on Uploads page | Added "You haven't uploaded any photos yet" message with Upload link |
| 4 | Login modal caused full page reload | Changed `<a href="/signup">` to `<router-link>` with modal close |
| 5 | Tag submission failures were silent | Added `toast.error()` in AddTags.vue catch block |
| 6 | XP calculation ignored enum multipliers | Rewrote `AddTagsToPhotoAction::calculateXp()` to use `XpScore` enum |
