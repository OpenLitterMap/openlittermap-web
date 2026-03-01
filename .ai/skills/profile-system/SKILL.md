---
name: profile-system
description: ProfileController, ApiSettingsController, DeleteAccountController, user stats (Redis + MySQL fallback), LevelService, privacy toggles, account deletion with Redis cleanup, and the Profile Vue page.
---

# Profile System

User profile dashboard, settings management, privacy controls, and account deletion. Stats aggregated from Redis with MySQL fallback for pre-v5 users. Fully audited for v5 — all controllers, stores, tests confirmed correct. 35 tests across 7 files. No user tag editing (admin-only). User photo delete flows call MetricsService::deletePhoto() before soft delete.

## Key Files

- `app/Http/Controllers/User/ProfileController.php` — Dashboard data (index), GeoJSON (geojson), CSV export (download)
- `app/Http/Controllers/ApiSettingsController.php` — Privacy toggles, whitelisted setting updates, legacy key remapping
- `app/Http/Controllers/API/DeleteAccountController.php` — Account deletion with Redis cleanup across all location scopes
- `app/Http/Controllers/User/UserPhotoController.php` — Bulk tag, bulk delete, filter, previous custom tags
- `app/Http/Controllers/User/Photos/UsersUploadsController.php` — Paginated photo listing with v5 tag structure
- `app/Services/LevelService.php` — XP-threshold based levels (12 levels, config-driven via `config/levels.php`)
- `app/Services/Redis/RedisMetricsCollector.php` — `getUserMetrics()` returns uploads/xp/litter/streak from Redis
- `app/Services/Redis/RedisKeys.php` — Cluster-safe key generation for all scopes
- `resources/js/stores/profile.js` — Pinia store: FETCH_PROFILE() from `/api/user/profile/index`
- `resources/js/stores/settings.js` — Pinia store: UPDATE_SETTING, TOGGLE_PRIVACY, DELETE_ACCOUNT
- `resources/js/views/Profile/Profile.vue` — Tab container (Dashboard, Photos, Settings) with query-param routing
- `resources/js/views/Profile/components/ProfileDashboard.vue` — Level card, stats grid, rank, achievements, locations, team
- `resources/js/views/Profile/components/ProfilePhotos.vue` — Upload count, links to /uploads, /upload, /tag
- `resources/js/views/Profile/components/ProfileSettings.vue` — Account fields, preference toggles, privacy toggles, delete account
- `resources/js/views/Profile/components/SettingsField.vue` — Inline-editable text field with save/cancel
- `resources/js/views/Profile/components/SettingsToggle.vue` — Toggle switch component
- `tests/Feature/User/ProfileIndexTest.php` — 4 tests (structure, auth, location counts, rank total)
- `tests/Feature/User/PublicProfileTest.php` — 4 tests (public profile data, private returns, privacy settings, 404)
- `tests/Feature/User/ProfileGeojsonTest.php` — 1 test (geojson returns only admin-approved photos, uses `summary` JSON not `result_string`)
- `tests/Feature/User/SettingsProfileTest.php` — 10 tests (whitelist, validation, legacy remapping, old routes)
- `tests/Feature/User/DeleteAccountTest.php` — 4 tests (Redis cleanup, photo preservation, password validation)
- `tests/Feature/User/UserPhotoBulkDeleteTest.php` — 5 tests (soft-delete, counters, ownership, metrics, selectAll)
- `tests/Feature/Photos/WebDeletePhotoTest.php` — 5 tests (single delete, ownership, counters, metrics reversal)

## Invariants

1. **All profile routes use `auth:sanctum`.** Not `auth:api`. Use `actingAs($user)` in tests (no guard argument).
2. **Redis-first with MySQL fallback.** `RedisMetricsCollector::getUserMetrics()` returns Redis data; ProfileController falls back to `$user->total_images`, `$user->total_litter`, `$user->xp` when Redis returns 0.
3. **Rank from Redis ZSET with MySQL fallback.** `ZREVRANK` on `{g}:lb:xp`; if false, count users with more XP via `User::where('xp', '>', $xp)->count() + 1`.
4. **Level updated on profile view.** If `$user->level != calculated`, `$user->save()` syncs it.
5. **Settings whitelist enforced server-side.** `ApiSettingsController::ALLOWED_SETTINGS = ['name', 'username', 'email', 'global_flag', 'picked_up', 'previous_tags', 'emailsub', 'public_profile']`. Any other key returns 422.
6. **Legacy key remapping.** `items_remaining` → `picked_up` with inverted boolean (backward compat for old mobile clients).
7. **Photos preserved on account deletion.** User hard-deleted, photos remain (public contribution to map).
8. **Redis cleanup is comprehensive.** Removes user from XP and contributor rankings for every location scope (global, country, state, city), plus user stats hash, tags hash, and streak bitmap.
9. **Privacy toggles are boolean columns.** `show_name_maps`, `show_username_maps`, `show_name_createdby`, `show_username_createdby` on User model. Controllers toggle and return new value.
10. **Photo deletion reverses metrics.** `MetricsService::deletePhoto()` called for processed photos before soft-delete. Decrements `user.xp` and `user.total_images`.
11. **GeoJSON uses `summary` JSON, not `result_string`.** `ProfileController@geojson` returns `properties.summary` (v5 JSON array), not `properties.result_string` (deprecated v4 string). Frontend `popup.js` expects `summary`.

## Routes

### SPA routes — `auth:sanctum` (session cookies + Sanctum tokens)

```
# Profile dashboard
GET    /api/user/profile/index                    → ProfileController@index
GET    /api/user/profile/map                      → ProfileController@geojson
GET    /api/user/profile/download                 → ProfileController@download

# Photo management (SPA)
GET    /api/user/profile/photos/index             → UserPhotoController@index
GET    /api/user/profile/photos/filter            → UserPhotoController@filter
GET    /api/user/profile/photos/previous-custom-tags → UserPhotoController@previousCustomTags
POST   /api/user/profile/photos/tags/bulkTag      → UserPhotoController@bulkTag
POST   /api/user/profile/photos/delete            → UserPhotoController@destroy

# Single-photo delete (SPA legacy route, also auth:sanctum)
POST   /api/profile/photos/delete                 → PhotosController@deleteImage

# Settings (SPA — new endpoints)
POST   /api/settings/update                       → ApiSettingsController@update
POST   /api/settings/delete-account               → DeleteAccountController

# Privacy toggles (SPA)
POST   /api/settings/privacy/maps/name            → ApiSettingsController@mapsName
POST   /api/settings/privacy/maps/username        → ApiSettingsController@mapsUsername
POST   /api/settings/privacy/leaderboard/name     → ApiSettingsController@leaderboardName
POST   /api/settings/privacy/leaderboard/username  → ApiSettingsController@leaderboardUsername
POST   /api/settings/privacy/createdby/name       → ApiSettingsController@createdByName
POST   /api/settings/privacy/createdby/username    → ApiSettingsController@createdByUsername
POST   /api/settings/privacy/toggle-previous-tags  → ApiSettingsController@togglePreviousTags
```

### Legacy mobile routes — `auth:api` (Passport tokens)

```
# Settings (mobile — legacy endpoints, separate from SPA)
POST   /api/settings/details                      → UsersController@details
PATCH  /api/settings/details/password             → UsersController@changePassword
POST   /api/settings/privacy/update               → UsersController@togglePrivacy
POST   /api/settings/phone/submit                 → UsersController@phone
POST   /api/settings/phone/remove                 → UsersController@removePhone
POST   /api/settings/toggle                       → UsersController@togglePresence
POST   /api/settings/email/toggle                 → EmailSubController@toggleEmailSub
GET    /api/settings/flags/countries              → SettingsController@getCountries
POST   /api/settings/save-flag                    → SettingsController@saveFlag
PATCH  /api/settings                              → SettingsController@update

# Photo delete (mobile)
DELETE /api/photos/delete                         → ApiPhotosController@deleteImage
```

### v3 routes — `auth:api,web` (both guards)

```
GET    /api/v3/user/photos                        → UsersUploadsController@index
GET    /api/v3/user/photos/stats                  → UsersUploadsController@stats
```

## Patterns

### ProfileController@index response

```php
return [
    'user' => [id, name, username, avatar, created_at, member_since, global_flag, public_profile],
    'stats' => [uploads, litter, xp, streak, littercoin, photo_percent, tag_percent],
    'level' => [level, title, xp_into_level, xp_for_next, xp_remaining, progress_percent],
    'rank' => [global_position, global_total, percentile],
    'global_stats' => [total_photos, total_litter],
    'achievements' => [unlocked, total],
    'locations' => [countries, states, cities],
    'team' => [id, name] | null,
];
```

### Redis + MySQL fallback pattern

```php
$metrics = RedisMetricsCollector::getUserMetrics($userId);
$uploads = $metrics['uploads'] ?: (int) $user->total_images;
$litter  = $metrics['litter']  ?: (int) $user->total_litter;
$xp      = $metrics['xp']      ?: (int) $user->xp;
```

### Rank calculation

```php
$globalXpKey = RedisKeys::xpRanking(RedisKeys::global());
$rank = Redis::zRevRank($globalXpKey, (string) $userId);
if ($rank !== false) {
    $globalPosition = $rank + 1; // 0-indexed → 1-indexed
} else {
    $globalPosition = User::where('xp', '>', $xp)->count() + 1;
}
```

### Level progression (config-driven thresholds)

```
Level 1:  0 XP     — Complete Noob
Level 2:  100 XP   — Less of a Noob
Level 3:  500 XP   — Post-Noob
Level 4:  1000 XP  — Litter Wizard
Level 5:  5000 XP  — Trash Warrior
...
Level 12: 1000000 XP — SuperIntelligent LitterMaster
```

Config: `config/levels.php`. Service: `LevelService::getUserLevel($xp)` returns level info array. User model `next_level` accessor calls LevelService.

### Account deletion Redis cleanup

```php
// Determine all location scopes from user's photos
$photos = Photo::where('user_id', $userId)->get();
$scopes = [RedisKeys::global()];
foreach ($photos as $photo) {
    if ($photo->country_id) $scopes[] = RedisKeys::country($photo->country_id);
    if ($photo->state_id)   $scopes[] = RedisKeys::state($photo->state_id);
    if ($photo->city_id)    $scopes[] = RedisKeys::city($photo->city_id);
}
$scopes = array_unique($scopes);

// Remove from all ranking ZSETs
foreach ($scopes as $scope) {
    Redis::zRem(RedisKeys::xpRanking($scope), (string) $userId);
    Redis::zRem(RedisKeys::contributorRanking($scope), (string) $userId);
}

// Delete user-specific keys
$userScope = RedisKeys::user($userId);
Redis::del(RedisKeys::stats($userScope));
Redis::del("{$userScope}:tags");
Redis::del(RedisKeys::userBitmap($userId));
```

### Frontend tab routing

```javascript
// Profile.vue uses query params for tab persistence
const route = useRoute();
const router = useRouter();
const activeTab = computed(() => route.query.tab || 'dashboard');

function switchTab(tab) {
    router.replace({ query: { tab } });
}
```

### Settings store sync pattern

```javascript
// settings.js syncs back to userStore after update
async UPDATE_SETTING(key, value) {
    const { data } = await axios.post('/api/settings/update', { key, value });
    if (data.success) {
        const userStore = useUserStore();
        if (userStore.user[key] !== undefined) {
            userStore.user[key] = value;
        }
    }
}
```

## Common Mistakes

- **Mixing up SPA vs mobile auth guards.** SPA profile/settings routes use `auth:sanctum` (session cookies). Legacy mobile routes use `auth:api` (Passport tokens). They are separate route groups — don't merge them. Sanctum does NOT validate Passport tokens.
- **Using `Auth::guard('api')->user()` in SPA controllers.** Use `Auth::user()` — Sanctum resolves the user from session or token automatically. `Auth::guard('api')` returns null for session-authenticated SPA users.
- **Using `actingAs($user, 'api')` in tests for SPA routes.** SPA routes use `auth:sanctum`. Use `actingAs($user)` with no guard argument. Using `'api'` guard in test + `auth:sanctum` on route = 401.
- **Uploading via `/api/photos/submit` in web-guard tests.** That route uses `auth:api` (Passport). If your test uses `actingAs($user)` (web guard), the upload silently fails. Use `Photo::factory()` to create test photos instead.
- **Not falling back to MySQL for pre-v5 users.** Redis stats hash is empty for users who uploaded before v5. Always check `$metrics['uploads'] ?: (int) $user->total_images`.
- **Comparing level with wrong XP.** Levels are threshold-based (not cumulative): 0, 100, 500, 1000, 5000, etc. Use `LevelService::getUserLevel()`, don't calculate manually.
- **Forgetting `ZREVRANK` returns `false` not `null`.** PHP Redis returns `false` for missing members. Check `$rank !== false`.
- **Hard-deleting photos on account deletion.** Photos are public contributions and must be preserved. Only the User record is hard-deleted.
- **Allowing mass assignment of protected fields.** `is_admin`, `verification_required`, etc. are NOT in `ALLOWED_SETTINGS`. The whitelist check prevents privilege escalation.
- **Not reversing metrics on photo deletion.** `MetricsService::deletePhoto()` must run for processed photos (those with `processed_at`) before soft-delete.
- **Forgetting inverted boolean for `picked_up`.** Mobile sends `picked_up=false` meaning `items_remaining=true`. The controller inverts the value.
- **Adding constructor middleware to controllers in `auth:sanctum` route groups.** Route group handles auth — constructor `$this->middleware('auth')` is redundant and can conflict. `PhotosController` had this bug (fixed).
