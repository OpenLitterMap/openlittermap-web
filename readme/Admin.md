# OpenLitterMap v5 — Admin System

## Overview

Admins review photos from untrusted users, edit/approve tags, delete bad uploads, and manage user trust levels. The admin system is the quality gate between user submissions and public data.

**Scale context:** OLM currently has 1–3 admins. The spec below is designed for that reality. Multi-admin concurrency, claim queues, and workload distribution are noted as future considerations but not implemented.

---

## Roles & Permissions

Uses Spatie Laravel Permission 6 on `web` guard.

### Existing roles

| ID | Role | Access level |
|----|------|-------------|
| 1 | `superadmin` | Full access — all admin actions + trust management + Horizon |
| 2 | `admin` | Photo review — approve, edit tags, delete |
| 3 | `helper` | Tag editing only — `update tags` permission, no delete/trust |

### Existing permissions

| ID | Permission | Granted to |
|----|-----------|------------|
| 1 | `update tags` | superadmin, admin, helper |
| 2 | `create boxes` | superadmin, admin |
| 3 | `update boxes` | superadmin, admin, helper |
| 4 | `view horizon` | superadmin |
| 5 | `verify boxes` | superadmin, admin |

### New permissions needed (Phase 2)

| Permission | Granted to | Purpose |
|-----------|------------|---------|
| `approve photos` | superadmin, admin | Approve photos in queue |
| `delete photos` | superadmin, admin | Delete photos from queue |
| `manage user trust` | superadmin | Toggle `verification_required` on users |

**Phase 1:** Continue using the existing `admin` middleware (checks `hasRole('admin')` or `hasRole('superadmin')`). Permission-granular access is Phase 2.

### Authorization boundaries

- Admins **cannot** view or act on school team photos (`is_public = false`). School photos go through teacher approval via the **Facilitator Queue** — a parallel 3-panel UI with the same tagging components, scoped to the teacher's team. See `readme/Teams.md` → Facilitator Queue.
- Admins **can** re-edit tags on already-approved photos (re-triggers MetricsService via fingerprint delta).
- Admins **can** delete any photo (including approved), but MetricsService reversal runs first.

---

## Verification State Machine

### VerificationStatus enum values

| Value | Name | Meaning | Who sets it |
|-------|------|---------|-------------|
| 0 | `UNVERIFIED` | Uploaded, no tags yet | System (on upload) |
| 1 | `VERIFIED` | Tagged by user | `AddTagsToPhotoAction` |
| 2 | `ADMIN_APPROVED` | Verified by admin, trusted user, or teacher | Admin/trusted auto-verify/teacher |
| 3 | `BBOX_APPLIED` | Bounding boxes drawn | OpenLitterAI pipeline |
| 4 | `BBOX_VERIFIED` | Bounding boxes verified | Admin |
| 5 | `AI_READY` | Ready for model training | Admin |

### Allowed transitions (admin actions)

```
UNVERIFIED (0) ── approve ───→ ADMIN_APPROVED (2)  (untrusted non-school user tagged photo)
VERIFIED (1) ──── approve ───→ ADMIN_APPROVED (2)  (school student tagged photo)
UNVERIFIED (0) ── edit+approve → ADMIN_APPROVED (2)
VERIFIED (1) ──── edit+approve → ADMIN_APPROVED (2)
any status ─── delete ──────→ soft deleted (deleted_at set)
ADMIN_APPROVED+ ── edit tags ──→ ADMIN_APPROVED (stays, re-processed via delta)
```

**Note:** Untrusted non-school users' photos stay at `UNVERIFIED (0)` after tagging (only `verification` is set to 0.1, `verified` is never changed). School students' photos are set to `VERIFIED (1)`. Both appear in the admin queue via `WHERE verified < ADMIN_APPROVED AND summary IS NOT NULL`.

### Prohibited

- `is_public = false` photos **never** enter the admin queue
- Photos with `summary IS NULL` (untagged) cannot be approved — admin must add tags first

### Relationship to `processed_at`

| State | `verified` | `processed_at` | In metrics? |
|-------|-----------|----------------|-------------|
| Uploaded, no tags | 0 | null | No |
| Tagged by untrusted user | 1 | null | No — awaiting admin |
| Admin approved | 2 | set by MetricsService | Yes |
| Admin re-edited tags | 2 | updated (fingerprint delta) | Yes (corrected) |
| Soft deleted | any | cleared by MetricsService | No (reversed) |

---

## Current State — Phase 1 COMPLETE, Phase 2 (Queue) COMPLETE, Phase 3 (Users/Stats) COMPLETE

### Phase 1: AdminController (4 methods fixed)

| Method | Status |
|--------|--------|
| `verify()` | Atomic approve, full event constructor, null summary → 422 |
| `destroy()` | MetricsService reversal + soft delete, no ImageDeleted event |
| `updateDelete()` | DB::transaction tag replace via AddTagsToPhotoAction, atomic approve |
| `getCountriesWithPhotos()` | VerificationStatus enum, is_public filter, summary not null |

User-facing delete endpoints also fixed: `PhotosController@deleteImage` and `ApiPhotosController@deleteImage` — dead `ImageDeleted` event removed, `processed_at` null check before MetricsService, soft delete.

### Phase 2: Admin Queue UI + Endpoint

**Backend:** `AdminQueueController` (`GET /api/admin/photos`) — paginated pending photos with filters (country_id, user_id, photo_id, date_from, date_to), eager-loaded PhotoTag relationships, and `new_tags[]` transform for frontend tag editing. Response built manually (not `toArray()`) to avoid Location model accessor crash on null `updated_at`. 11 tests in `AdminQueueTest.php`.

**Frontend:** `/admin/queue` route — three-panel layout reusing existing tagging components:
- **Left:** Filter sidebar (`AdminQueueFilters.vue`) — country select, photo ID, user ID, date range
- **Center:** Photo viewer (reuses `PhotoViewer.vue`)
- **Right:** Tag panel with pre-loaded existing tags (reuses `UnifiedTagSearch`, `ActiveTagsList`, `TagCard`)
- **Header:** (`AdminQueueHeader.vue`) — pending count, prev/next navigation, Approve/Save Edits/Delete buttons
- **Store:** `admin.js` Pinia store — calls existing admin endpoints (verify, destroy, contentsupdatedelete)

Tag hydration converts API `new_tags` format → `TagCard` format. Edit detection via JSON comparison enables "Save Edits" only when tags differ from loaded state.

---

## v5 Admin Features

### 1. Photo Review Queue

#### Queue query

```php
Photo::where('verified', '<', VerificationStatus::ADMIN_APPROVED)
    ->where('is_public', true)           // ENFORCED: excludes school/private
    ->whereNotNull('summary')            // only tagged photos
    ->whereNull('deleted_at')            // excludes soft-deleted
    ->orderBy('created_at', 'asc')       // oldest first
```

**Hard rule:** The `is_public = true` filter is non-negotiable. School team photos are invisible to the admin queue regardless of verification status.

#### Filtering

| Filter | Parameter | Query addition |
|--------|-----------|----------------|
| Country | `country_id` | `WHERE country_id = ?` |
| State | `state_id` | `WHERE state_id = ?` |
| City | `city_id` | `WHERE city_id = ?` |
| User | `user_id` | `WHERE user_id = ?` |

No filtering by verification float values (0.1 etc.) — v5 uses enum values only. The queue shows all photos where `verified < ADMIN_APPROVED`.

#### Skip (MVP — single admin)

Frontend-only. Skipped photo IDs tracked in browser session state. Not persisted server-side.

**Declared limitation:** This works for 1–3 admins. If multiple admins work the queue simultaneously, they may review the same photo. The atomic `WHERE verified < ADMIN_APPROVED` on approve makes this safe (second admin gets `approved_count: 0`, not a double-process). For workload distribution across many admins, a server-side claim system would be needed — that's Phase 3.

#### Batch approve

```
POST /api/admin/photos/batch-approve
{ photo_ids: [1, 2, 3, ...] }
```

**Limits:** Max 200 photo_ids per request. Each fires `TagsVerifiedByAdmin`. Uses the same atomic `WHERE is_public = true AND verified < ADMIN_APPROVED` pattern as `TeamPhotosController::approve()`.

### 2. Approve

```
POST /api/admin/photos/{photo}/approve
```

**Idempotency rule:** Atomic update with `WHERE verified < ADMIN_APPROVED AND is_public = true`. If the photo was already approved (by this admin, another admin, or a trust change), the WHERE matches zero rows and no event fires. Response returns `{ success: true, approved: false, reason: 'already_approved' }`.

**Flow:**
1. Precondition check: `summary` must not be null → 422 if null
2. Atomic update: `verified = ADMIN_APPROVED` WHERE `verified < ADMIN_APPROVED AND is_public = true`
3. If row updated: fire `TagsVerifiedByAdmin($photo->id, $photo->user_id, $photo->country_id, $photo->state_id, $photo->city_id, $photo->team_id)`
4. `rewardXpToAdmin()` — only if row was updated (no XP for no-op)
5. `logAdminAction()`

**S3 policy: No deletion on approve.** Photos remain viewable after approval. The v4 behaviour of replacing filenames with `/assets/verified.jpg` is removed.

### 3. Edit Tags + Approve

```
PATCH /api/admin/photos/{photo}/tags
{ tags: [...v5 format...] }
```

**Transaction boundary:** Tag deletion + creation + summary regeneration wrapped in `DB::transaction()`.

**Flow:**
1. `DB::transaction()`:
   a. Delete existing PhotoTags for this photo
   b. Create new PhotoTags via `AddTagsToPhotoAction::run()` (resolves string keys → FK IDs)
   c. `GeneratePhotoSummaryService` runs (summary + XP written)
2. Set `verified = ADMIN_APPROVED`
3. Fire `TagsVerifiedByAdmin` → MetricsService
    - If `processed_at` was null: MetricsService does a "create" (first processing)
    - If `processed_at` was set: MetricsService computes fingerprint delta (corrects metrics)
4. `rewardXpToAdmin()`
5. `logAdminAction()`

**Post-conditions:**
- `photo.summary` is not null
- `photo.xp` matches summary calculation
- PhotoTags reflect the new tag set
- `verified == ADMIN_APPROVED`

**Tag format:** Same as `TeamPhotosController::updateTags()` — accepts category/object as string keys, resolves to FK IDs via `Category::where('key', ...)` and `LitterObject::where('key', ...)`.

### 4. Delete Photo

```
DELETE /api/admin/photos/{photo}
```

**Hard sequencing rule:** `MetricsService::deletePhoto()` runs BEFORE `$photo->delete()`. If metrics reversal fails, the delete is aborted (exception propagates).

**Flow:**
1. If `processed_at` is not null: `MetricsService::deletePhoto($photo)` — reverses all metrics
2. Detach Littercoin if linked (`littercoin.photo_id = null`)
3. `$photo->delete()` — soft delete (SoftDeletes trait, row persists with `deleted_at`)
4. `rewardXpToAdmin()`
5. `logAdminAction()`

**S3 policy: Deferred deletion.** S3 image cleanup runs as a queued job, not synchronously. Images are retained for 30 days after soft delete for audit purposes. This can be implemented as a scheduled command that purges S3 files for photos soft-deleted > 30 days ago.

**No `ImageDeleted` event.** MetricsService handles all metric reversal directly. The old event dispatch is removed.

### 5. User Trust Management

```
POST /api/admin/users/{user}/trust
{ trusted: true }
```

**Access:** `superadmin` role only.

**Flow:**
1. Set `user.verification_required = !trusted`
2. `logAdminAction()` with `target_type: 'user'`

Trust changes do NOT retroactively approve existing photos. If the admin wants to approve the user's backlog, they use approve-all as a separate action:

```
POST /api/admin/users/{user}/approve-all
```

Same as batch-approve, filtered to `WHERE user_id = ? AND verified < ADMIN_APPROVED AND is_public = true AND summary IS NOT NULL`. Max 500 per call.

---

## Admin XP Policy

| Action | XP awarded | Condition |
|--------|-----------|-----------|
| Approve | 1 | Only if photo was actually approved (WHERE matched) |
| Edit + approve | 1 + tag XP bonus | Only if photo was actually approved |
| Delete | 1 | Always (admin did work) |
| Trust change | 0 | Administrative action, no XP |
| Batch approve | 1 per photo approved | Sum of actual approvals |

Admin XP appears in the **global all-time leaderboard** only. It does not appear in time-filtered leaderboards (no per-user metrics rows written). This is by design — admin verification XP is supplementary, not competitive. See Leaderboards.md for details on `rewardXpToAdmin()`.

**Anti-abuse:** Idempotent approve (atomic WHERE) prevents double-awarding. Soft-deleted photos cannot be re-approved. `rewardXpToAdmin()` only called when an action actually changed state.

---

## Audit Logging

`logAdminAction()` already exists and is called from all admin methods. The spec requires:

### Required fields

| Field | Source | Notes |
|-------|--------|-------|
| `admin_id` | `auth()->id()` | Who performed the action |
| `photo_id` | `$photo->id` | Target photo (or null for user actions) |
| `action` | Route method name | `verify`, `destroy`, `updateDelete`, `trust`, `batchApprove` |
| `tag_updates` | Diff array | Before/after tag summary (for edit actions) |
| `route` | Current route | Full method name via `Route::getCurrentRoute()` |

**Phase 2 additions:** `target_type` (photo/user), `target_id`, before/after `verified` status, XP awarded. For now, the existing `logAdminAction($photo, $actionMethod, $tagUpdates)` signature is sufficient.

---

## API Routes

All under `admin` middleware (checks `hasRole('admin')` or `hasRole('superadmin')`). Auth guard: same as rest of API (resolve in auth guard audit — currently `auth:api`).

### Implemented routes

| Method | Route | Action | Controller | Auth |
|--------|-------|--------|------------|------|
| GET | `/api/admin/photos` | Paginated queue with filters | `AdminQueueController` | admin |
| POST | `/api/admin/verify` | Approve photo | `AdminController@verify` | admin |
| POST | `/api/admin/destroy` | Delete photo | `AdminController@destroy` | admin |
| POST | `/api/admin/contentsupdatedelete` | Edit tags + approve | `AdminController@updateDelete` | admin |
| GET | `/api/admin/get-countries-with-photos` | Countries with pending counts | `AdminController@getCountriesWithPhotos` | admin |

### Phase 3 routes (IMPLEMENTED)

| Method | Route | Action | Controller | Auth |
|--------|-------|--------|------------|------|
| GET | `/api/admin/stats` | Dashboard stats | `AdminStatsController` | admin |
| GET | `/api/admin/users` | List/search/filter users | `AdminUsersController@index` | admin |
| POST | `/api/admin/users/{user}/trust` | Toggle trust | `AdminUsersController@trust` | superadmin |
| POST | `/api/admin/users/{user}/approve-all` | Bulk approve user's photos | `AdminUsersController@approveAll` | superadmin |
| PATCH | `/api/admin/users/{user}/username` | Moderate username | `AdminUsersController@updateUsername` | superadmin |
| POST | `/api/admin/users/{user}/school-manager` | Toggle school_manager role | `AdminUsersController@toggleSchoolManager` | superadmin |

### Error responses

| Code | Meaning |
|------|---------|
| 401 | Not authenticated |
| 403 | Not admin / not superadmin (for trust endpoints) |
| 404 | Photo or user not found |
| 422 | Validation error (null summary on approve, invalid tag payload, batch over 200) |

No 409 Conflict — idempotent approve returns success with `approved: false` instead.

---

## Dashboard Stats

```
GET /api/admin/stats
```

```json
{
    "queue_total": 342,
    "by_country": [
        { "country_id": 105, "country": "Ireland", "count": 42 },
        { "country_id": 1, "country": "United States", "count": 128 }
    ],
    "by_verification": {
        "unverified": 50,
        "verified": 292
    }
}
```

`by_verification` uses enum names only — no float values (0.1 etc.).

---

## Database Indices

### Queue query

```sql
CREATE INDEX idx_admin_queue ON photos (is_public, verified, deleted_at, created_at);
```

### Queue with location filter

```sql
CREATE INDEX idx_admin_queue_country ON photos (is_public, verified, country_id, created_at);
```

### Stats (country grouping)

The stats query groups by `country_id` — the queue index covers this. No separate index needed at OLM scale.

---

## What to Reuse

| Component | Status | Notes |
|-----------|--------|-------|
| `AddTagsToPhotoAction` | ✅ Use as-is | v5 tag pipeline — creates PhotoTags, summary, XP |
| `GeneratePhotoSummaryService` | ✅ Use as-is | Called by AddTagsToPhotoAction |
| `MetricsService::processPhoto()` | ✅ Use as-is | Called via TagsVerifiedByAdmin event |
| `MetricsService::deletePhoto()` | ✅ Use as-is | Called before soft delete |
| `TagsVerifiedByAdmin` event | ✅ Use as-is | Full constructor with location IDs |
| `rewardXpToAdmin()` | ✅ Use as-is | Fixed — updates MySQL + Redis |
| `TeamPhotosController::approve()` pattern | ✅ Reference | Atomic approve with idempotent WHERE |
| `TeamPhotosController::updateTags()` pattern | ✅ Reference | String key → FK resolution |
| `logAdminAction()` | ✅ Use as-is | Audit logging |

## What to Delete (Phase 1)

| Target | Reason |
|--------|--------|
| `use AddTagsTrait` in AdminController | Writes to v4 category tables |
| S3 deletion calls in `verify()` and `updateDelete()` | Photos should remain viewable |
| `$photo->filename = '/assets/verified.jpg'` | No longer replacing images |
| `ImageDeleted` event dispatch in `destroy()` | MetricsService handles reversal directly |
| Manual `$user->total_images` decrement in `destroy()` | MetricsService handles this |

## What to Delete (Post-migration cleanup)

| Target | Reason |
|--------|--------|
| `AddTagsTrait` file | No remaining callers after Phase 1 |
| `CalculateTagsDifferenceAction` | Dead code once `destroy()` uses MetricsService |
| `getCountriesWithPhotos()` | Replaced by `GET /api/admin/stats` in Phase 2 |

---

## Migration Path

### Phase 1: Fix critical bugs (pre-LitterWeek) — COMPLETE

All 4 methods fixed. 7 tests passing.

1. ✅ **`verify()`** — null summary → 422. Atomic `WHERE is_public=true AND verified < ADMIN_APPROVED`. Full `TagsVerifiedByAdmin` constructor. `rewardXpToAdmin()` only on actual approval. Removed: S3 deletion, filename replacement, `DeletePhotoAction`.
2. ✅ **`destroy()`** — `MetricsService::deletePhoto()` before soft delete (if `processed_at` set). `$photo->delete()` (soft delete). Littercoin detachment preserved. Removed: `CalculateTagsDifferenceAction`, `$user->total_images` decrement, `ImageDeleted` event.
3. ✅ **`updateDelete()`** — `DB::transaction`: delete PhotoTags + `AddTagsToPhotoAction::run()` (summary + XP). Atomic approve. Full event constructor. Removed: `AddTagsTrait`, S3 deletion, filename replacement.
4. ✅ **`getCountriesWithPhotos()`** — `verified` enum (`< ADMIN_APPROVED`), `is_public=true`, `whereNotNull('summary')`.
5. ✅ **Constructor** — Injected `MetricsService` + `AddTagsToPhotoAction`. Removed `DeletePhotoAction`, `DeleteTagsFromPhotoAction`, `CalculateTagsDifferenceAction`, `use AddTagsTrait`.
6. ✅ **Tests** — `tests/Feature/Admin/AdminVerificationTest.php` (7 tests, 31 assertions).

### Phase 2: Admin Queue UI + Endpoint — COMPLETE

Queue browsing with full tag editing, approve, and delete actions. 11 tests in `AdminQueueTest.php`.

**Backend:**
- ✅ **`AdminQueueController`** — `GET /api/admin/photos` — paginated queue with filters (country_id, user_id, photo_id, date_from, date_to), eager-loaded PhotoTag data, `new_tags[]` transform. Response built manually to avoid Location accessor crash. Replaces `GetNextImageToVerifyController` + `GoBackOnePhotoController`.
- ✅ **Route** — Added to existing admin group in `routes/api.php`
- ✅ **Tests** — `AdminQueueTest.php`: exclusions (approved, private, untagged, soft-deleted), filters (country, photo ID, date range), pagination, auth

**Frontend:**
- ✅ **`AdminQueue.vue`** — Three-panel layout: filters | photo viewer | tag editor. Reuses `PhotoViewer`, `UnifiedTagSearch`, `ActiveTagsList`, `TagCard` from tagging system.
- ✅ **`AdminQueueHeader.vue`** — Pending count, photo info, prev/next nav, Approve/Save Edits/Delete buttons
- ✅ **`AdminQueueFilters.vue`** — Country select, photo ID, user ID, date range, apply/reset
- ✅ **`admin.js` Pinia store** — fetchPhotos, fetchCountries, approvePhoto, deletePhoto, updateTagsAndApprove (calls existing admin endpoints)
- ✅ **Router** — `/admin/queue` route with auth middleware
- ✅ **Nav** — "Admin - Queue" link in Menu dropdown

### Phase 3: User Management, Stats, Username Moderation — COMPLETE

**Backend:**

- ✅ **`AdminStatsController`** — `GET /api/admin/stats` — cached 60s. Returns: `queue_total`, `queue_today`, `by_verification` (enum labels), `by_country` (top 20), `total_users`, `users_today`, `flagged_usernames`. Cache key: `admin:dashboard:stats`.
- ✅ **`AdminUsersController@index`** — `GET /api/admin/users` — paginated user list with search (name/username/email), sort (created_at/photos_count/xp), trust filter (all/trusted/untrusted), flagged filter, per-page (max 100). Response includes: id, name, username, email, created_at, photos_count, xp, verification_required, pending_photos, roles, is_trusted, username_flagged.
- ✅ **`AdminUsersController@trust`** — `POST /api/admin/users/{user}/trust` — superadmin only. Sets `verification_required = !trusted`. Does NOT retroactively approve existing photos.
- ✅ **`AdminUsersController@approveAll`** — `POST /api/admin/users/{user}/approve-all` — superadmin only. Approves all pending public photos for user (max 500). Same atomic WHERE + event firing as `AdminController::verify()`.
- ✅ **`AdminUsersController@updateUsername`** — `PATCH /api/admin/users/{user}/username` — superadmin only. Updates username and clears `username_flagged`. Validation: 3–30 chars, alphanumeric + hyphens, unique. Via `UpdateUsernameRequest` form request.
- ✅ **`AdminUsersController@toggleSchoolManager`** — `POST /api/admin/users/{user}/school-manager` — superadmin only. Grants or revokes `school_manager` role. On grant, queues `SchoolManagerInvite` email with CTAs for uploading and creating a school team.
- ✅ **Username flagging system** — User self-change of username sets `username_flagged = true`. Superadmin edit clears flag. `GET /api/admin/users?flagged=true` filters to flagged only. Stats include `flagged_usernames` count.

**Frontend:**

- ✅ **`AdminUsers.vue`** — `/admin/users` route. Stats cards (queue, users, flagged names, top country), search bar, trust/flagged filters, sortable table with inline actions.
- ✅ **`UserRow.vue`** — Table row component. Trust toggle (superadmin), Approve All button (superadmin), inline username editor (superadmin), flagged badge.
- ✅ **Store** — `admin.js` Pinia store extended: `fetchUsers`, `fetchStats`, `toggleTrust`, `approveAllForUser`, `updateUsername`.
- ✅ **Nav** — "Admin - Users" link in dropdown.
- ✅ **Tests** — 49 total admin tests across 6 files:
  - `AdminVerificationTest.php` (8 tests)
  - `AdminQueueTest.php` (12 tests)
  - `AdminResetTagsTest.php` (4 tests)
  - `AdminUsersTest.php` (9 tests)
  - `AdminTrustTest.php` (10 tests)
  - `AdminUsernameModerationTest.php` (13 tests — username edit, flagging, validation, auth)
  - `AdminStatsTest.php` (5 tests — cache structure, counts, auth)

### Phase 4: Future

- AI-assisted pre-tagging (OpenLitterAI)
- Multi-admin claim queue with TTL
- Confidence scoring for auto-approval
- Admin workload distribution
- Review audit trail / history
- Permission-granular access (`approve photos`, `delete photos`, `manage user trust` via Spatie)
- Batch approve endpoint (`POST /api/admin/photos/batch-approve`)

---

## Implementation Priority

Phase 1 (admin actions), Phase 2 (queue UI), and Phase 3 (user management, stats, username moderation, school manager toggle) are complete. 51 admin tests passing.

School photos bypass admin entirely (teacher approval via facilitator queue). Community trusted users bypass admin (auto-verify). The admin queue is for individual untrusted users uploading outside of teams.

Phase 4 (AI pre-tagging, claim queue, batch approve) is not yet blocking public launch.

---

## Related Docs

| Document | Covers |
|----------|--------|
| **Upload.md** | TagsVerifiedByAdmin pipeline, EventServiceProvider |
| **SchoolPipeline.md** | Teacher approval (separate from admin) |
| **Teams.md** | Trust model, is_trusted flag |
| **Metrics.md** | MetricsService processPhoto/deletePhoto |
| **Tags.md** | v5 tag format, summary JSON, XP |
| **Leaderboards.md** | rewardXpToAdmin() scope and behaviour |
| **PostMigrationCleanup.md** | AddTagsTrait and category tables to delete |
