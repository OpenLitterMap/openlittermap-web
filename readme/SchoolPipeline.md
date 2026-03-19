# OpenLitterMap v5 — School Approval Pipeline

## Overview

School team photos follow a gated pipeline where student data is private until a teacher (school_manager) approves it. This prevents unapproved data from appearing on the public map or in aggregate metrics.

```
Student uploads photo
  → PhotoObserver sets is_public = false
  → Upload metrics SKIPPED (school team check — not is_public)
  → No XP, no leaderboard entry, no metrics row

Student tags photo
  → AddTagsToPhotoAction generates summary + XP on photo model
  → Photo stays at verified = VERIFIED (1)
  → TagsVerifiedByAdmin does NOT fire (team not trusted)
  → Photo invisible on public map, excluded from all aggregates

Teacher approves
  → TeamPhotosController sets is_public = true, verified = ADMIN_APPROVED (2)
  → TagsVerifiedByAdmin fires → MetricsService::processPhoto() → doCreate()
  → doCreate() writes 1 upload + full XP (upload + tag) + increments users.xp
  → SchoolDataApproved fires → notification broadcast
  → Photo visible on public map, counted in metrics
```

**Why this matters:** If any step is missing, the photo either leaks early or counts for nothing.

---

## The Pipeline, Step by Step

### Step 1: Student Tags Photo

The student tags a photo on a school team. `AddTagsToPhotoAction` runs:

```
AddTagsToPhotoAction::run()
├── Create PhotoTag records (category_id, litter_object_id, quantity)
├── GeneratePhotoSummaryService → writes photo.summary JSON
├── Calculate XP → writes photo.xp
└── updateVerification()
    ├── Team is NOT trusted → verified = VERIFIED (1)
    └── TagsVerifiedByAdmin does NOT fire
```

**Critical invariant:** Summary and XP MUST be generated here, regardless of trust level. If summary generation is gated behind the trust check, the photo reaches the approval controller with a null summary. MetricsService reads null, extracts zero metrics, and the photo counts for nothing.

### Step 2: Photo is Private

`PhotoObserver::creating()` detects the school team and sets `is_public = false`:

```php
// PhotoObserver.php
public function creating(Photo $photo): void
{
    if ($photo->team_id) {
        $team = Team::find($photo->team_id);
        if ($team && $team->isSchool()) {
            $photo->is_public = false;
        }
    }
}
```

All public-facing queries use `Photo::public()` scope or `where('is_public', true)`, so this photo is invisible to the world.

### Step 3: No Metrics Fire

Two gates prevent metrics from leaking:

1. **Upload gate:** `UploadPhotoController` checks `$photo->team->isSchool()` (NOT `is_public`) and skips upload metrics entirely for school photos. This distinction is critical: private-by-choice photos (where a non-school user has `public_photos=false`) still receive immediate upload XP — only school photos defer. No upload count, no upload XP, no leaderboard entry for school photos.
2. **Tag gate:** Because the school team has `is_trusted = false`, `AddTagsToPhotoAction::updateVerification()` stops at `VERIFIED (1)`. `TagsVerifiedByAdmin` does NOT fire. `processPhoto()` never runs.

Result: `processed_at` stays null. No location totals, no leaderboard entries, no XP credits in aggregate data.

This is intentional — aggregate data cannot leak before teacher review.

### Step 4: Teacher Sees Pending Photos

The teacher views pending photos via `GET /api/teams/photos?team_id=X&status=pending`.

`TeamPhotosController::index()` returns photos with `is_public = false` and `team_approved_at IS NULL`. Student names are masked if safeguarding is enabled (see Teams.md).

### Step 5: Teacher Approves

`POST /api/teams/photos/approve` with `{ team_id, photo_ids: [...] }` or `{ team_id, approve_all: true }`.

```
TeamPhotosController::approve()
├── Build query: team photos WHERE is_public = false
├── Pluck IDs of photos to approve
├── Atomic UPDATE inside DB::transaction()
│   ├── is_public = true
│   ├── verified = ADMIN_APPROVED (2)
│   ├── team_approved_at = now()
│   └── team_approved_by = teacher.id
├── For each approved photo:
│   └── event(new TagsVerifiedByAdmin(...))  ← triggers MetricsService
└── event(new SchoolDataApproved(team, teacher, count))
```

### Step 6: MetricsService Processes

`TagsVerifiedByAdmin` fires with the photo's location data. `ProcessPhotoMetrics` listener calls `MetricsService::processPhoto()`, which:

1. Reads photo.summary JSON
2. Extracts tag counts by category, object, material, brand
3. Upserts into the `metrics` table (all timescales x all location scopes)
4. Updates Redis aggregates via `RedisMetricsCollector`

The photo now counts in Ireland's litter total, Cork's stats, leaderboards, contributor rankings, etc.

### Step 7: Photo Appears on Public Map

With `is_public = true` and `verified >= ADMIN_APPROVED`, the photo passes the `Photo::public()` scope and appears in:

- Global cluster map
- Location-specific views (country/state/city)
- Points API
- Public data downloads

---

## Idempotency

Approving an already-approved photo is a no-op. The `WHERE is_public = false` clause in the atomic UPDATE means re-approval cannot double-process:

```php
$affectedRows = Photo::whereIn('id', $approvedIds)
    ->where('is_public', false)  // Already-approved photos filtered out
    ->update([...]);
```

Events only fire for `$affectedRows > 0`. A second approval request for the same photos returns `approved_count: 0` and dispatches nothing.

### Step 8: Teacher Revokes Approval (Optional)

`POST /api/teams/photos/revoke` — un-publishes previously approved photos.

```
TeamPhotosController::revoke()
├── Build query: team photos WHERE is_public = true AND team_approved_at IS NOT NULL
├── For each processed photo:
│   └── MetricsService::deletePhoto()  ← reverses metrics
├── Atomic UPDATE:
│   ├── is_public = false
│   ├── verified = VERIFIED (1)
│   ├── team_approved_at = null
│   └── team_approved_by = null
└── Return revoked_count
```

After revoke, the photo returns to Step 2 (private, no metrics). It can be re-approved later.

### Step 9: Teacher Deletes Photo (Optional)

`DELETE /api/teams/photos/{photo}` — permanently removes a photo.

```
TeamPhotosController::destroy()
├── If processed: MetricsService::deletePhoto() → reverses metrics + users.xp
├── DeletePhotoAction → S3 cleanup
└── Photo::delete() → soft-delete
```

### Safeguarding on Global Map

When school team photos are approved and appear on the global map, student identity is masked:

```php
// PointsController::formatFeatures()
if ($photo->team_id && $photo->team && $photo->team->hasSafeguarding()) {
    $properties['name'] = null;
    $properties['username'] = null;
    $properties['social'] = null;
    $properties['flag'] = null;
}
```

The map popup shows "Contributed by [Team Name]" instead of the student's name or flag.

---

## What Can Go Wrong

### 1. Summary is null at approval time

**Cause:** `AddTagsToPhotoAction` skipped summary generation for non-trusted teams.

**Effect:** MetricsService reads null summary, extracts zero metrics. Photo appears on the map but doesn't count in any totals.

**Detection:** `TeamPhotosController::approve()` logs a warning: `"Approving photo {id} with null summary — metrics will be incomplete"`.

**Prevention:** Summary generation must run regardless of trust level. Tested in `SchoolApprovalPipelineTest::test_step1`.

### 2. School team marked as trusted

**Cause:** Manual DB change or migration bug sets `is_trusted = true`.

**Effect:** `TagsVerifiedByAdmin` fires immediately on tagging (before teacher review). MetricsService processes the photo. Aggregate data leaks — Ireland's litter count increases before the teacher even sees the data. The photo is still hidden from the map (`is_public = false`), but totals, leaderboards, and XP are corrupted.

**Prevention:** School teams must always have `is_trusted = false`. Tested in `TeamPhotosTest::test_approval_fires_tags_verified_for_metrics`.

### 3. Public query missing is_public filter

**Cause:** A controller or scope returns photos without checking `is_public = true`.

**Effect:** Private school photos leak onto the public map.

**Prevention:** All public-facing queries use `Photo::public()` scope or `where('is_public', true)`. Audited across 10 controllers/traits.

### 4. Approval without TagsVerifiedByAdmin

**Cause:** Approval controller sets `is_public = true` but doesn't dispatch the event.

**Effect:** Photo appears on the map but never enters MetricsService. Location totals, leaderboards, and Redis aggregates are never updated.

**Prevention:** Tested in `SchoolApprovalPipelineTest::test_step4` and `TeamPhotosTest::test_approval_fires_tags_verified_for_metrics`.

---

## Verification Status Reference

| Value | Name | Meaning |
|-------|------|---------|
| 0 | UNVERIFIED | Uploaded, no tags |
| 1 | VERIFIED | Tagged by user (school students land here) |
| 2 | ADMIN_APPROVED | Verified by admin/trusted user OR teacher-approved |
| 3 | BBOX_APPLIED | Bounding boxes drawn |
| 4 | BBOX_VERIFIED | Bounding boxes verified |
| 5 | AI_READY | Ready for OpenLitterAI training |

For school teams, the jump from 1 → 2 happens at teacher approval, not at tagging time.

---

## Test Coverage

The pipeline is tested end-to-end in two complementary test files:

### `SchoolApprovalPipelineTest` (7 tests)
Step-by-step validation with Category/LitterObject records and event assertions:
1. Student tags → summary generated, photo private
2. Private photo excluded from public scope
3. TagsVerifiedByAdmin NOT fired before approval
4. Teacher approval → photo public, metrics events fire
5. Approved photo visible in public scope
6. Double approval is idempotent
7. Student names masked in photo list

### `SchoolPhotoPipelineTest` (4 tests)
Full pipeline flow + edge cases:
1. Complete tag → approve → public flow in one test
2. Double approval idempotency
3. Points API never leaks private photos
4. Safeguarding masks identity throughout pipeline

### `TeamPhotosTest` (35 tests)
Controller-level tests for all photo endpoints:
- Privacy defaults (school vs community)
- Photo listing with status filters
- Approval flow (specific, all, student-blocked)
- Tag editing with CLO format (leader/school_manager only)
- **new_tags format:** index and show return CLO-based `new_tags` with category/object/extra_tags
- **Member stats:** per-student counts (photos, pending, approved, litter), safeguarding pseudonyms, leader-only auth
- Map points (members only)
- Dashboard verification breakdown
- MetricsService event integration
- Public scope exclusion
- Safeguarding in photo lists
- **Delete:** teacher can delete, metrics reversed, student blocked (403)
- **Revoke:** makes photos private, reverses metrics, idempotent, student blocked (403)
- **Global map safeguarding:** student name/username masked, team name preserved

### `SchoolLifecycleTest` (12 tests)
Full lifecycle tests with real MetricsService and Redis (no mocks):
- Upload does NOT award XP for school photos (school team check gate — `team->isSchool()`)
- Tagging does not process tag metrics
- Teacher approval processes full XP (upload + tag) via doCreate()
- Teacher edits tags then approves
- Teacher deletes unapproved photo (no metrics to reverse)
- Teacher deletes approved photo (full metrics reversal)
- Revoke reverses metrics, re-approve restores (doCreate increments users.xp)
- Multi-student batch approval with correct individual XP
- Double approval idempotency
- Participant session upload attributed to facilitator
- Participant full cycle (upload → tag → approve)
- School photo excluded from public scope before approval

### `SchoolTeamLifecycleTest` (7 tests)
Team creation + join + approval lifecycle integration.

---

## Related Docs

| Document | Covers |
|----------|--------|
| **Teams.md** | Team types, permissions, safeguarding, API routes |
| **Metrics.md** | How MetricsService processes approved photos |
| **Upload.md** | When TagsVerifiedByAdmin fires in the normal (non-school) flow |
| **Tags.md** | Summary JSON structure that MetricsService reads |
