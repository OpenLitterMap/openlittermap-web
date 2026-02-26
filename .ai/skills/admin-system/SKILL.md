---
name: admin-system
description: AdminController, photo approval, tag editing, deletion, MetricsService integration, admin middleware, verification queue, and admin XP.
---

# Admin System

Admin photo review: approve, edit tags, delete. Quality gate between user submissions and public data.

## Key Files

- `app/Http/Controllers/AdminController.php` — 4 methods: verify, destroy, updateDelete, getCountriesWithPhotos
- `app/Http/Controllers/Admin/AdminQueueController.php` — Paginated queue endpoint with filters + tag transform (`GET /api/admin/photos`)
- `app/Http/Middleware/IsAdmin.php` — checks `hasRole('admin')` or `hasRole('superadmin')` on web guard
- `app/Events/TagsVerifiedByAdmin.php` — event with full constructor (photo_id, user_id, country_id, state_id, city_id, team_id)
- `app/Actions/Tags/AddTagsToPhotoAction.php` — v5 tag pipeline (creates PhotoTags, summary, XP)
- `app/Services/Metrics/MetricsService.php` — `deletePhoto()` for metric reversal before soft delete
- `app/Http/Controllers/Admin/AdminResetTagsController.php` — Reset tags on a photo: reverse metrics, delete PhotoTags, reset state to unverified
- `app/Helpers/helpers.php` — `rewardXpToAdmin()`, `logAdminAction()`
- `tests/Feature/Admin/AdminVerificationTest.php` — 8 tests (approve, delete, edit+approve, retag, idempotency, auth, school exclusion)
- `tests/Feature/Admin/AdminQueueTest.php` — 12 tests (queue endpoint: filters, pagination, exclusions, auth)
- `tests/Feature/Admin/AdminResetTagsTest.php` — 4 tests (reset clears state, metrics reversal, skip approved, non-admin rejected)
- `readme/Admin.md` — full spec (Phase 1 complete, Phase 2 queue UI complete)

### Frontend (Admin Queue UI)

- `resources/js/stores/admin.js` — Pinia store: fetchPhotos, fetchCountries, approvePhoto, deletePhoto, updateTagsAndApprove
- `resources/js/views/Admin/AdminQueue.vue` — Main review page (three-panel: filters | photo | tags)
- `resources/js/views/Admin/components/AdminQueueHeader.vue` — Header bar with pending count, navigation, action buttons
- `resources/js/views/Admin/components/AdminQueueFilters.vue` — Filter sidebar (country, photo ID, user ID, date range)

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

## Routes

All under `/api/admin/` prefix with `admin` middleware:

| Method | Route | Controller Method |
|--------|-------|-------------------|
| GET | `/api/admin/photos` | `AdminQueueController` (paginated queue) |
| POST | `/api/admin/verify` | `verify()` |
| POST | `/api/admin/destroy` | `destroy()` |
| POST | `/api/admin/contentsupdatedelete` | `updateDelete()` |
| POST | `/api/admin/reset-tags` | `AdminResetTagsController` (reset to unverified) |
| GET | `/api/admin/get-countries-with-photos` | `getCountriesWithPhotos()` |

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
| `/admin/redis/:userId?` | `Redis.vue` | Redis analytics |

## Roles (Spatie, web guard)

| ID | Role | Access |
|----|------|--------|
| 1 | `superadmin` | All admin actions |
| 2 | `admin` | Photo review (approve, edit, delete) |
| 3 | `helper` | Tag editing only |

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

## Phase Status

- **Phase 1:** COMPLETE — 4 AdminController methods, 4 deprecated controllers retired (410), AdminResetTagsController v5-fixed
- **Phase 2:** COMPLETE — Queue endpoint (`AdminQueueController`, 12 tests) + Queue UI (`AdminQueue.vue` with filters, tag editing, approve/edit/delete). Reuses existing tagging components. 24 total admin tests passing.
- **Bbox pipeline:** RETIRED — `BoundingBoxController` returns 410 Gone on all 5 endpoints (`/api/bbox/*`). Was entirely v4 with broken `TagsVerifiedByAdmin` signature. Routes left wired for clean 410 responses.
- **Phase 3:** Future — AI pre-tagging, multi-admin claim queue, confidence scoring, batch approve, trust management, permission-granular access
