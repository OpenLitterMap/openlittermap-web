# User Photo Visibility — Design Spec

**Date:** 2026-03-16
**Branch:** `upgrade/tagging-2025`
**Status:** Approved

## Overview

Add a user-level `public_photos` default setting and a per-photo visibility toggle. Users can control whether their photos appear on the global map while still receiving full XP and leaderboard credit. School team photos remain governed by the school pipeline (teacher approval).

## Key Decisions

- **Private-by-choice photos process metrics immediately.** XP, leaderboard credit, and stats are awarded at upload and tag time regardless of `is_public`. The photo simply doesn't appear in `Photo::public()` queries (global map, points API, admin queue, clustering).
- **School photos still defer metrics.** The existing school pipeline is unchanged — metrics are deferred until teacher approval.
- **The metrics gate changes from `is_public` to school team check.** The current gate `if ($photo->is_public !== false)` in `UploadPhotoController:170` conflates user-chosen privacy with school deferral. It must change to check if the photo belongs to a school team.
- **Precedence:** School team (`is_public = false`, enforced) > request explicit value > user's `public_photos` default > `true`.

## Database

### Migration: Add `public_photos` to `users` table

```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('public_photos')->default(true)->after('public_profile');
});
```

No changes to `photos` table — `is_public` column already exists.

## Backend Changes

### 1. UploadPhotoController — Apply user default + fix metrics gate

**File:** `app/Http/Controllers/Uploads/UploadPhotoController.php`

Set `is_public` on the photo before creation:
```
Precedence: request value → user default → true
```

1. If request explicitly sends `is_public`, use that value.
2. Else use `$user->public_photos`.
3. PhotoObserver `creating` hook overrides to `false` for school teams (existing behavior, unchanged).

Fix metrics gate (line 170): Change from:
```php
if ($photo->is_public !== false) {
```
To:
```php
$isSchoolPhoto = $photo->team_id && ($team = Team::find($photo->team_id)) && $team->isSchool();
if (!$isSchoolPhoto) {
```

This follows the same inline pattern used in `PhotoObserver::creating()`. Private-by-choice photos get immediate upload XP while school photos continue to defer.

Add `'is_public' => 'sometimes|boolean'` to `UploadPhotoRequest` validation rules so the request can optionally send it.

### 2. ApiSettingsController — Add to whitelist

**File:** `app/Http/Controllers/ApiSettingsController.php`

Add `'public_photos' => 'boolean'` to the `ALLOWED_SETTINGS` array. The existing `POST /settings/update` endpoint handles the rest.

### 3. ProfileController — Return in response

**File:** `app/Http/Controllers/User/ProfileController.php`

Add `'public_photos' => (bool) $user->public_photos` to the `index()` response user object.

### 4. Per-Photo Visibility Endpoint

**Route:** `PATCH /api/v3/photos/{id}/visibility`
**Middleware:** `auth:sanctum`
**Controller:** `UsersUploadsController` — natural home since it already manages the user's photo list

Request body:
```json
{ "is_public": true }
```

Logic:
1. Find photo, verify `$photo->user_id === auth()->id()`.
2. If photo belongs to a school team, return 403 ("School team photos are managed by the team leader").
3. Update `$photo->is_public`.
4. Save — PhotoObserver `saving` hook marks dirty tiles for verified photos.
5. Return success + updated `is_public` value.

### 5. PhotoObserver — Add `is_public` to dirty tile tracking

The existing `creating` hook already forces `is_public = false` for school teams (unchanged).

The `saved` hook (line 80) currently only marks tiles dirty for changes to `['lat', 'lon', 'verified', 'tile_key']`. Add `'is_public'` to this list so that toggling visibility on a verified photo correctly invalidates the cluster cache:

```php
if ($photo->wasChanged(['lat', 'lon', 'verified', 'tile_key', 'is_public'])) {
```

Without this, toggling a verified photo private would leave it in cached cluster GeoJSON until the next `clustering:update --all` run.

### 6. User Model

No `$fillable` change needed — `ApiSettingsController` uses direct property assignment (`$user->$key = $value; $user->save()`), not mass assignment. This is consistent with how other boolean settings like `picked_up` work.

### 7. ProfileController — Own-user queries include private photos

`ProfileController::geojson()` (line 80) and the location count queries (lines 160, 254) filter on `is_public = true`. This means users who set photos private won't see them on their own profile map or in their location counts.

**Decision:** The authenticated user's own `index()` location counts should include ALL their photos (remove `is_public` filter for self). The `geojson()` method already only serves the authenticated user, so it should also include their private photos. The public `show()` method keeps the `is_public` filter since it serves other users viewing the profile.

## Known Limitations

- **No bulk visibility toggle.** Per-photo toggle only. Bulk operations planned for later.

## Frontend Changes

### 1. ProfileSettings.vue — Default Photo Visibility toggle

Add a "Photos Public by Default" toggle in the Privacy section, following the same pattern as existing toggles (e.g., "Public Profile"). Calls `POST /settings/update` with `{ public_photos: bool }`.

### 2. Uploads Page — Per-photo visibility icon

Add an eye icon toggle per photo in the uploads list. Open eye = public, closed eye = private. Calls `PATCH /api/v3/photos/{id}/visibility`. Disabled (greyed out) for school team photos with tooltip explaining why.

## What Does NOT Change

- **School pipeline:** PhotoObserver forces `is_public = false` for school teams. Teacher approval sets `is_public = true`. Metrics defer until approval. All unchanged.
- **TagsVerifiedByAdmin event:** Fires for all non-school users regardless of `is_public`. No changes.
- **Photo::public() scope:** Continues to filter `where('is_public', true)`. Private-by-choice photos excluded from map, points API, admin queue, clustering. No changes.
- **Clustering:** Only processes photos where `is_public = true` and `verified >= ADMIN_APPROVED`. PhotoObserver updated to mark tiles dirty on `is_public` change.
- **Leaderboard privacy:** Handled separately by existing `show_name`/`show_username` settings. Unrelated to photo visibility.

## Test Cases

1. **Upload with user default `public_photos = false`:** Photo created with `is_public = false`. Upload XP awarded immediately. Photo does not appear in `Photo::public()` queries.
2. **Upload with user default `public_photos = false` but request sends `is_public = true`:** Request value wins. Photo is public.
3. **School student with `public_photos = true`:** School override wins. Photo created with `is_public = false`. Metrics deferred.
4. **School participant leaves team, uploads again:** User's `public_photos` preference applies. No school override.
5. **Per-photo toggle public → private:** `is_public` updated. Dirty tiles marked. Photo disappears from map on next load. XP unchanged.
6. **Per-photo toggle on school photo:** Returns 403.
7. **Settings update `public_photos`:** Persists to user. Next upload uses new default.
8. **Private photo gets tagged:** `TagsVerifiedByAdmin` fires. Metrics processed. User appears on leaderboard. Photo stays off map.
