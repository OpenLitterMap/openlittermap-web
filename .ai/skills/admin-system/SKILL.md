---
name: admin-system
description: AdminController, photo approval, tag editing, deletion, MetricsService integration, admin middleware, verification queue, and admin XP.
---

# Admin System

Admin photo review: approve, edit tags, delete. Quality gate between user submissions and public data.

## Key Files

### Backend — Photo Review (Phase 1–2)
- `app/Http/Controllers/AdminController.php` — 4 methods: verify, destroy, updateDelete, getCountriesWithPhotos
- `app/Http/Controllers/Admin/AdminQueueController.php` — Paginated queue endpoint with filters + tag transform (`GET /api/admin/photos`)
- `app/Http/Controllers/Admin/AdminResetTagsController.php` — Reset tags on a photo: reverse metrics, delete PhotoTags, reset state to unverified
- `app/Http/Middleware/IsAdmin.php` — checks `hasRole('admin')` or `hasRole('superadmin')` on web guard
- `app/Events/TagsVerifiedByAdmin.php` — event with full constructor (photo_id, user_id, country_id, state_id, city_id, team_id)
- `app/Actions/Tags/AddTagsToPhotoAction.php` — v5 tag pipeline (creates PhotoTags, summary, XP)
- `app/Services/Metrics/MetricsService.php` — `deletePhoto()` for metric reversal before soft delete
- `app/Helpers/helpers.php` — `rewardXpToAdmin()`, `logAdminAction()`

### Backend — User Management, Stats, Username Moderation (Phase 3)
- `app/Http/Controllers/Admin/AdminStatsController.php` — Dashboard stats (cached 60s): queue totals, by-verification, by-country, user counts, flagged usernames
- `app/Http/Controllers/Admin/AdminUsersController.php` — 4 methods: index (list/search/filter), trust (toggle), approveAll (bulk), updateUsername (moderation)
- `app/Http/Requests/Admin/UpdateUsernameRequest.php` — Validation: 3–30 chars, alphanumeric + hyphens, unique. Superadmin auth gate.
- `app/Mail/SchoolManagerInvite.php` — Queued email sent when `school_manager` role is granted via `toggleSchoolManager()`

### Frontend
- `resources/js/stores/admin.js` — Pinia store: fetchPhotos, fetchCountries, approvePhoto, deletePhoto, updateTagsAndApprove, fetchUsers, fetchStats, toggleTrust, approveAllForUser, updateUsername
- `resources/js/views/Admin/AdminQueue.vue` — Photo review page (three-panel: filters | photo | tags)
- `resources/js/views/Admin/AdminUsers.vue` — User management page (stats cards, search, filters, table)
- `resources/js/views/Admin/components/AdminQueueHeader.vue` — Header bar with pending count, navigation, action buttons
- `resources/js/views/Admin/components/AdminQueueFilters.vue` — Filter sidebar (country, photo ID, user ID, date range)
- `resources/js/views/Admin/components/UserRow.vue` — Table row: trust toggle, approve all, username editor (superadmin gated)

### Tests (49 tests total)
- `tests/Feature/Admin/AdminVerificationTest.php` — 8 tests (approve, delete, edit+approve, retag, idempotency, auth, school exclusion)
- `tests/Feature/Admin/AdminQueueTest.php` — 12 tests (queue endpoint: filters, pagination, exclusions, auth)
- `tests/Feature/Admin/AdminResetTagsTest.php` — 4 tests (reset clears state, metrics reversal, skip approved, non-admin rejected)
- `tests/Feature/Admin/AdminStatsTest.php` — 5 tests (cache, counts, by-verification, by-country, auth)
- `tests/Feature/Admin/AdminUsersTest.php` — 9 tests (list, search, sort, trust filter, flagged filter, pagination, auth, school manager invite email on grant, no email on revoke)
- `tests/Feature/Admin/AdminTrustTest.php` — 10 tests (trust toggle, approve-all, superadmin-only, school exclusion, idempotency)
- `tests/Feature/Admin/AdminUsernameModerationTest.php` — 13 tests (username edit, flagging lifecycle, validation rules, auth)
- `readme/Admin.md` — full spec (Phase 1–3 complete)

## Invariants

1. **Approve requires non-null summary.** If `photo.summary` is null → 422. No tags = nothing to approve.
2. **Approve is atomic and idempotent.** `WHERE is_public = true AND verified < ADMIN_APPROVED`. Second approve matches 0 rows, no event fires, no XP awarded.
3. **School photos excluded.** `is_public = false` photos never enter admin queue. The `WHERE is_public = true` condition enforces this.
4. **MetricsService before soft delete.** `deletePhoto()` runs BEFORE `$photo->delete()`. If it throws, delete is aborted.
5. **Soft delete only.** `$photo->delete()` uses SoftDeletes trait. No hard deletes.
6. **Full event constructor.** `TagsVerifiedByAdmin` needs all 6 args: photo_id, user_id, country_id, state_id, city_id, team_id.
7. **No S3 deletion on approve.** Photos remain viewable after approval. S3 cleanup is deferred (Phase 2).
8. **Admin XP is conditional.** `rewardXpToAdmin()` only called when an action actually changed state (row updated).
9. **Tag edits use DB::transaction.** Delete existing PhotoTags + `AddTagsToPhotoAction::run()` + approve, all atomic.
10. **Admin middleware uses web guard.** `Auth::user()` (default = web). Tests use `actingAs($user)` with no guard arg.

## Patterns

### Approve flow
```php
// Precondition
if ($photo->summary === null) return 422;

// Atomic update
$affected = Photo::where('id', $photo->id)
    ->where('is_public', true)
    ->where('verified', '<', VerificationStatus::ADMIN_APPROVED->value)
    ->update(['verified' => VerificationStatus::ADMIN_APPROVED->value]);

// Only if row was updated
if ($affected > 0) {
    event(new TagsVerifiedByAdmin($photo->id, $photo->user_id, ...));
    rewardXpToAdmin();
}
```

### Delete flow
```php
if ($photo->processed_at !== null) {
    $this->metricsService->deletePhoto($photo); // Reverses metrics
}
// Littercoin detachment...
$photo->delete(); // Soft delete
rewardXpToAdmin();
```

### Edit + approve flow
```php
DB::transaction(function () use ($request, $photo) {
    PhotoTag::where('photo_id', $photo->id)->delete();
    $this->addTagsAction->run($photo->user_id, $photo->id, $request->tags);
});
$photo->refresh();
// Then same atomic approve as verify()
```

### Countries queue query (v5)
```php
Photo::query()
    ->where('verified', '<', VerificationStatus::ADMIN_APPROVED->value)
    ->where('is_public', true)
    ->whereNotNull('summary')
    ->groupBy('country_id');
```

### Queue endpoint pattern

```php
// GET /api/admin/photos — AdminQueueController
Photo::query()
    ->where('is_public', true)
    ->where('verified', '<', VerificationStatus::ADMIN_APPROVED->value)
    ->whereNotNull('summary')
    ->with(['user:id,name', 'countryRelation:id,country,shortcode',
            'photoTags.category', 'photoTags.object',
            'photoTags.primaryCustomTag', 'photoTags.extraTags.extraTag'])
    ->when($request->country_id, fn ($q) => $q->where('country_id', $request->country_id))
    ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
    ->when($request->photo_id, fn ($q) => $q->where('id', $request->photo_id))
    ->when($request->date_from, fn ($q) => $q->where('created_at', '>=', $request->date_from))
    ->when($request->date_to, fn ($q) => $q->where('created_at', '<=', $request->date_to))
    ->orderBy('created_at', 'asc')
    ->paginate($perPage);
// Response manually built (avoids Location accessor crash on null updated_at)
// Includes new_tags[] with hydrated PhotoTag data for frontend tag editing
```

### Stats endpoint pattern
```php
// GET /api/admin/stats — AdminStatsController (cached 60s)
Cache::remember('admin:dashboard:stats', 60, function () {
    // queue_total: is_public=true, verified=VERIFIED, summary NOT NULL
    // queue_today: same + created_at >= today
    // by_verification: grouped by VerificationStatus enum labels
    // by_country: top 20 countries with pending photos
    // total_users, users_today, flagged_usernames
});
```

### User list endpoint pattern
```php
// GET /api/admin/users — AdminUsersController@index
User::query()
    ->withCount('photos')
    ->with('roles:id,name')
    ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
        $q->where('name', 'LIKE', "%{$search}%")
          ->orWhere('username', 'LIKE', "%{$search}%")
          ->orWhere('email', 'LIKE', "%{$search}%");
    }))
    ->when($trustFilter === 'trusted', fn ($q) => $q->where('verification_required', false))
    ->when($trustFilter === 'untrusted', fn ($q) => $q->where('verification_required', true))
    ->when($flagged, fn ($q) => $q->where('username_flagged', true))
    ->orderBy($sortBy, $sortDir)
    ->paginate($perPage);
// Response includes pending_photos count per user (is_public=true, verified < ADMIN_APPROVED)
```

### Trust toggle pattern
```php
// POST /api/admin/users/{user}/trust — superadmin only
// Sets verification_required = !trusted
// Does NOT retroactively approve existing photos
// No logAdminAction() — schema needs target_type for user-target actions
```

### Approve-all pattern
```php
// POST /api/admin/users/{user}/approve-all — superadmin only, max 500
// Same atomic WHERE as verify(): is_public=true AND verified < ADMIN_APPROVED
// Fires TagsVerifiedByAdmin per photo + rewardXpToAdmin()
```

### Username moderation pattern
```php
// PATCH /api/admin/users/{user}/username — superadmin only
// UpdateUsernameRequest: 3-30 chars, alphanumeric + hyphens, unique
// Clears username_flagged after edit
// username_flagged set to true when user self-changes username (in ApiSettingsController)
```

## Routes

All under `/api/admin/` prefix with `admin` middleware:

### Photo review (admin + superadmin)

| Method | Route | Controller Method |
|--------|-------|-------------------|
| GET | `/api/admin/photos` | `AdminQueueController` (paginated queue) |
| POST | `/api/admin/verify` | `AdminController@verify` |
| POST | `/api/admin/destroy` | `AdminController@destroy` |
| POST | `/api/admin/contentsupdatedelete` | `AdminController@updateDelete` |
| POST | `/api/admin/reset-tags` | `AdminResetTagsController` (reset to unverified) |
| GET | `/api/admin/get-countries-with-photos` | `AdminController@getCountriesWithPhotos` |

### Dashboard stats (admin + superadmin)

| Method | Route | Controller Method |
|--------|-------|-------------------|
| GET | `/api/admin/stats` | `AdminStatsController` (cached 60s) |

### User management

| Method | Route | Controller Method | Auth |
|--------|-------|-------------------|------|
| GET | `/api/admin/users` | `AdminUsersController@index` | admin |
| POST | `/api/admin/users/{user}/trust` | `AdminUsersController@trust` | superadmin |
| POST | `/api/admin/users/{user}/approve-all` | `AdminUsersController@approveAll` | superadmin |
| PATCH | `/api/admin/users/{user}/username` | `AdminUsersController@updateUsername` | superadmin |
| POST | `/api/admin/users/{user}/school-manager` | `AdminUsersController@toggleSchoolManager` | superadmin |

### Deprecated routes (return 410 Gone)

| Route | Was | Replaced by |
|-------|-----|-------------|
| `/api/admin/verify-tags-as-correct` | `VerifyImageWithTagsController` | `AdminController::verify()` |
| `/api/admin/find-photo-by-id` | `FindPhotoByIdController` | `AdminQueueController` |
| `/api/admin/get-next-image-to-verify` | `GetNextImageToVerifyController` | `AdminQueueController` |
| `/api/admin/go-back-one` | `GoBackOnePhotoController` | `AdminQueueController` |

### Frontend routes

| Path | Component | Purpose |
|------|-----------|---------|
| `/admin/queue` | `AdminQueue.vue` | Photo review queue UI |
| `/admin/users` | `AdminUsers.vue` | User management + username moderation |
| `/admin/redis/:userId?` | `Redis.vue` | Redis analytics |

## Roles (Spatie, web guard)

| ID | Role | Access |
|----|------|--------|
| 1 | `superadmin` | All admin actions + trust management + username moderation + Horizon |
| 2 | `admin` | Photo review (approve, edit, delete) + user list + stats |
| 3 | `helper` | Tag editing only (bounding box annotation role) |

### Superadmin-only actions
- Toggle user trust (`POST /api/admin/users/{user}/trust`)
- Bulk approve user's photos (`POST /api/admin/users/{user}/approve-all`)
- Moderate usernames (`PATCH /api/admin/users/{user}/username`)
- View Horizon dashboard

## Common Mistakes

- **Using `auth:api` guard in tests.** Admin middleware uses default (web) guard. Use `actingAs($user)` with no guard argument.
- **Forgetting `/api` prefix.** Routes are in `routes/api.php` which adds `/api` prefix. Use `/api/admin/verify`, not `/admin/verify`.
- **Firing event for no-op approve.** Only fire `TagsVerifiedByAdmin` when `$affected > 0`.
- **Hard deleting photos.** Always `$photo->delete()` (soft delete). Never `$photo->forceDelete()`.
- **Skipping MetricsService on delete.** Must call `deletePhoto()` before soft delete if `processed_at` is set.
- **Using AddTagsTrait.** Deleted (zero consumers after BoundingBoxController retirement). Use `AddTagsToPhotoAction::run()` (v5 action in `App\Actions\Tags`). `CalculateTagsDifferenceAction` also deleted — was only used by AddTagsTrait.
- **Approving school photos.** `is_public = false` photos are excluded by the atomic WHERE. Don't add special handling.

## Common Mistakes (Queue)

- **Using `$photo->toArray()` for queue response.** The Location model's `updatedAtDiffForHumans` accessor crashes on null `updated_at`. Build the response array manually.
- **Forgetting `whereNotNull('summary')` in queue query.** Untagged photos must not appear in the admin queue.
- **Not capping `per_page`.** Always `min($request->per_page, 50)` to prevent abuse.

## Common Mistakes (User Management)

- **Allowing admin (not superadmin) to toggle trust.** Trust, approve-all, and username moderation are superadmin-only. Check `hasRole('superadmin')`.
- **Expecting trust to auto-approve.** `trust()` sets `verification_required` but does NOT approve existing photos. Use `approveAll()` separately.
- **Forgetting `username_flagged` lifecycle.** User self-change → flagged=true. Superadmin edit → flagged=false. The flag drives the `/api/admin/users?flagged=true` filter.
- **Username validation.** Via `UpdateUsernameRequest`: 3–30 chars, `/^[a-zA-Z0-9-]+$/`, unique. Don't duplicate validation in controller.
- **Stats cache key.** `admin:dashboard:stats` — 60s TTL. Don't forget to invalidate if adding manual cache busting.

## Phase Status

- **Phase 1:** COMPLETE — 4 AdminController methods, 4 deprecated controllers retired (410), AdminResetTagsController v5-fixed
- **Phase 2:** COMPLETE — Queue endpoint (`AdminQueueController`, 12 tests) + Queue UI (`AdminQueue.vue` with filters, tag editing, approve/edit/delete). Reuses existing tagging components.
- **Phase 3:** COMPLETE — User management (`AdminUsersController`: list/search/filter, trust toggle, approve-all, username moderation, school manager toggle + invite email), dashboard stats (`AdminStatsController`), username flagging system. 51 total admin tests passing across 7 test files.
- **Bbox pipeline:** RETIRED — `BoundingBoxController` returns 410 Gone on all 5 endpoints (`/api/bbox/*`). Was entirely v4 with broken `TagsVerifiedByAdmin` signature. Routes left wired for clean 410 responses.
- **Facilitator Queue:** COMPLETE — Parallel system for school team teachers. Same 3-panel layout as admin queue, team-scoped. Reuses tagging v2 components (PhotoViewer, UnifiedTagSearch, ActiveTagsList). See `readme/Teams.md` and `teams-safeguarding` skill. Key differences from admin queue: status filter (pending/approved/all) instead of country filter, Revoke replaces Reset, member stats tab, safeguarding pseudonyms.
- **Phase 4:** Future — AI pre-tagging, multi-admin claim queue, confidence scoring, batch approve endpoint, permission-granular access
