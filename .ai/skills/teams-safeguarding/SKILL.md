---
name: teams-safeguarding
description: Teams, school teams, team photos, approval flow, TeamPhotosController, privacy, is_public, PhotoObserver, MasksStudentIdentity, and safeguarding.
---

# Teams & Safeguarding

School teams enforce a private-by-default pipeline. Photos are invisible to the public until a teacher approves them. This protects minors and ensures data quality.

## Key Files

### Backend
- `app/Http/Controllers/Teams/TeamPhotosController.php` — Photo listing (with `new_tags`), approval, CLO-based tag editing, map points, delete, revoke, member stats
- `app/Observers/PhotoObserver.php` — Sets `is_public = false` on school team photo creation
- `app/Traits/MasksStudentIdentity.php` — Masks student names as "Student N"
- `app/Models/Teams/Team.php` — `isSchool()`, `isLeader()`, `hasSafeguarding()`, `hasParticipantSessions()`
- `app/Models/Teams/TeamType.php` — `team` column: `'school'` or `'community'`
- `app/Models/Teams/Participant.php` — Participant slot model (token, activation)
- `app/Http/Middleware/ParticipantAuth.php` — Token auth middleware for participant workspace
- `app/Http/Controllers/Teams/ParticipantController.php` — Facilitator CRUD for participant slots
- `app/Http/Controllers/Teams/ParticipantSessionController.php` — Token validation + session entry
- `app/Http/Controllers/Teams/ParticipantPhotoController.php` — Participant's own photos
- `app/Actions/Teams/CreateTeamAction.php` — Team creation with school-specific fields
- `app/Http/Requests/Teams/CreateTeamRequest.php` — Validation + `school_manager` role check
- `app/Mail/SchoolManagerInvite.php` — Queued email sent when `school_manager` role is granted (two CTAs: Upload + Create Team)
- `app/Events/SchoolDataApproved.php` — Private broadcast on `team.{id}` channel
- `app/Listeners/NotifyTeamOfApproval.php` — Notifies team members after approval

### Frontend (Facilitator Queue)
- `resources/js/views/Teams/FacilitatorQueue.vue` — 3-panel layout (filters | PhotoViewer | tag editor)
- `resources/js/views/Teams/components/FacilitatorQueueHeader.vue` — Navigation, action buttons (Approve/Save Edits/Revoke/Delete)
- `resources/js/views/Teams/components/FacilitatorQueueFilters.vue` — Status toggle (pending/approved/all), date range
- `resources/js/views/Teams/components/TeamMembersList.vue` — Per-student stats table
- `resources/js/stores/teamPhotos.js` — Team photos Pinia store (CRUD, approve, revoke, delete, updateTags, memberStats)
- `resources/js/views/Teams/TeamsHub.vue` — Main teams page (replaces TeamsLayout sidebar + TeamDashboard)
- `resources/js/views/Teams/TeamOverview.vue` — Overview tab (stats, team info, all teams list)
- `resources/js/views/Teams/TeamSettingsTab.vue` — Consolidated settings tab (privacy, edit, download, leave)
- `resources/js/views/Teams/components/ParticipantGrid.vue` — Participant slot management grid
- `resources/js/views/Teams/ParticipantEntry.vue` — Token entry page (`/session`)
- `resources/js/views/Teams/ParticipantWorkspace.vue` — Participant upload/photos/tag workspace

### Tests
- `tests/Feature/Teams/TeamPhotosTest.php` — 35 tests (new_tags, CLO tag edits, member stats, safeguarding, delete, revoke, approval, map)
- `tests/Feature/Teams/ParticipantSessionTest.php` — 28 tests (slots, token auth, photos, queue, metrics)

## Authorization

### Facilitator Queue access control
```php
// TeamPhotosController authorization pattern:
// Team leader OR user with 'manage school team' permission
if (! $team->isLeader($user->id) && ! $user->can('manage school team')) {
    return response()->json(['success' => false, 'message' => 'unauthorized'], 403);
}
```

### Roles involved
| Role | How assigned | Facilitator access |
|------|-------------|-------------------|
| Team leader | `team.leader = user_id` | Yes — `$team->isLeader($userId)` |
| `school_manager` | `php artisan school:assign-manager {email}` or admin toggle | Yes — has `manage school team` permission |

**Critical:** School managers are NOT admins. They cannot access `/api/admin/*` endpoints. The admin queue and facilitator queue are completely separate systems with no overlap.

## Invariants

1. **School photos start private.** `PhotoObserver::creating()` sets `is_public = false` when `team.isSchool()`. This is non-negotiable.
2. **All public queries use `Photo::public()` or `where('is_public', true)`.** Missing this leaks school data to maps, clusters, exports, and points API.
3. **School teams must NOT be `is_trusted`.** Trust bypasses the teacher approval step entirely. School teams default to `is_trusted = false`.
4. **Teacher approval is atomic and idempotent.** The `WHERE is_public = false` clause prevents double-processing of already-approved photos.
5. **Safeguarding uses deterministic numbering.** Student names are masked based on `team_user.id` (creation order), not photo data or pagination.
6. **SchoolDataApproved broadcasts on a private channel** (`team.{id}`). School team names (e.g., "St. X 1st Years 2026") must never appear on public channels.
7. **Admin queue excludes school photos.** `is_public = false` photos never appear in `/api/admin/photos`. School photos go through teacher approval only.
8. **Participant photos: `user_id = facilitator`.** MetricsService, XP, leaderboards are untouched. `participant_id` is for attribution only.
9. **Participant isolation.** `PhotoTagsRequest::authorize()` checks `$photo->participant_id === $participant->id` to prevent cross-participant tagging (all photos share `user_id = facilitator`).
10. **`hasParticipantSessions()`** returns `participant_sessions_enabled && isSchool()` — community teams can never have participant sessions.
11. **Privacy defaults.** All new teams: `leaderboards = false`. School teams: `safeguarding = true` enforced, `is_trusted = false` enforced.
12. **SchoolManagerInvite email.** Queued on role grant (both artisan command and admin toggle). Not sent on revoke.

## Patterns

### PhotoObserver — automatic privacy

```php
// app/Observers/PhotoObserver.php
public function creating(Photo $photo): void
{
    if (! $photo->team_id) {
        return;
    }

    $team = Team::find($photo->team_id);

    if ($team && $team->isSchool()) {
        $photo->is_public = false;
    }
}
```

### Teacher approval flow

```php
// TeamPhotosController::approve()
DB::transaction(function () {
    // Atomic update — WHERE is_public = false prevents double-processing
    Photo::whereIn('id', $approvedIds)
        ->where('is_public', false)
        ->update([
            'is_public' => true,
            'verified' => VerificationStatus::ADMIN_APPROVED->value,
            'team_approved_at' => now(),
            'team_approved_by' => $user->id,
        ]);

    // Fire metrics for each newly-approved photo
    foreach ($affectedPhotos as $photo) {
        event(new TagsVerifiedByAdmin(
            photo_id: $photo->id,
            user_id: $photo->user_id,
            country_id: $photo->country_id,
            state_id: $photo->state_id,
            city_id: $photo->city_id,
            team_id: $photo->team_id
        ));
    }

    event(new SchoolDataApproved($team, $teacher, $count));
});
```

### Photo scopes for team queries

```php
// All public photos (excludes unapproved school photos + soft-deleted)
Photo::public()  // ->where('is_public', true)

// All photos for a team (private view — members see everything)
Photo::forTeam($teamId)

// Pending teacher approval
Photo::pendingTeamApproval($teamId)
// ->where('team_id', $teamId)->where('is_public', false)
//   ->where('verified', '>=', VERIFIED)->whereNull('team_approved_at')

// Already approved by teacher
Photo::teamApproved($teamId)
// ->where('team_id', $teamId)->whereNotNull('team_approved_at')
```

### Safeguarding identity masking

```php
// MasksStudentIdentity trait
// Builds stable mapping: user_id -> "Student N" from team_user.id order
if ($team->hasSafeguarding() && !$team->isLeader($viewer->id)
    && !$viewer->hasPermissionTo('view student identities')) {
    // Mask names to "Student 1", "Student 2", etc.
}
```

### Team model key methods

```php
$team->isSchool()         // type_name === 'school'
$team->isLeader($userId)  // leader === $userId
$team->hasSafeguarding()  // (bool) safeguarding
```

### Database indexes for team photo queries

```sql
-- Approval queue: team_id + is_public + verified + created_at
INDEX photos_team_approval_idx ON photos(team_id, is_public, verified, created_at)

-- Team photo listing
INDEX photos_team_public_idx ON photos(team_id, is_public)

-- Public queries
INDEX photos_public_verified_idx ON photos(is_public, verified)
```

### Teacher delete flow

```php
// TeamPhotosController::destroy()
// DELETE /api/teams/photos/{photo}?team_id=X
// 1. Check authorization (leader or 'manage school team')
// 2. If processed: MetricsService::deletePhoto() → reverse metrics
// 3. DeletePhotoAction → S3 cleanup
// 4. $photo->delete() → soft-delete
// 5. Decrement photo owner's XP and total_images
```

### Teacher revoke flow

```php
// TeamPhotosController::revoke()
// POST /api/teams/photos/revoke { team_id, photo_ids? | revoke_all? }
// 1. Check authorization (leader or 'manage school team')
// 2. Query: is_public = true AND team_approved_at IS NOT NULL
// 3. For each processed photo: MetricsService::deletePhoto()
// 4. Atomic update: is_public=false, verified=VERIFIED, clear approval timestamps
// Idempotent: already-private photos filtered by WHERE clause
```

### Safeguarding on global map (PointsController)

```php
// PointsController::formatFeatures()
// After building properties array:
if ($photo->team_id && $photo->team && $photo->team->hasSafeguarding()) {
    $properties['name'] = null;
    $properties['username'] = null;
    $properties['social'] = null;
}
// popup.js shows "Contributed by [Team Name]" when name/username are null but team exists
```

### Facilitator Queue — CLO-based tag editing

```php
// TeamPhotosController::updateTags()
// PATCH /api/teams/photos/{photo}/tags
// Accepts CLO payload: { tags: [{ category_litter_object_id, litter_object_type_id?, quantity, picked_up?, materials?, brands?, custom_tags? }] }
DB::transaction(function () use ($request, $photo, $user) {
    $photo->photoTags()->each(function ($tag) {
        $tag->extraTags()->delete();
        $tag->delete();
    });
    $photo->update(['summary' => null, 'xp' => 0, 'verified' => VerificationStatus::UNVERIFIED->value]);
    app(AddTagsToPhotoAction::class)->run($user->id, $photo->id, $request->tags);
});
```

### Facilitator Queue — new_tags response format

```php
// TeamPhotosController::index() and show() return new_tags
// Same format as UsersUploadsController::getNewTags() and AdminQueueController
// Includes: category_litter_object_id, litter_object_type_id, category, object, extra_tags
```

### Member stats endpoint

```php
// TeamPhotosController::memberStats()
// GET /api/teams/photos/member-stats?team_id=X
// Returns per-student: total_photos, pending, approved, litter_count, last_active
// Applies safeguarding pseudonyms via MasksStudentIdentity trait
// Leader or 'manage school team' permission required
```

### Keyboard shortcuts (FacilitatorQueue.vue)

| Key | Action |
|-----|--------|
| A | Approve current photo |
| D | Delete (with confirmation) |
| E | Save edits (when modified) |
| R | Revoke approval (with confirmation) |
| S / K / ArrowRight | Next photo |
| J / ArrowLeft | Previous photo |
| Escape | Clear search |

### Controllers/queries that must use `is_public = true`

- `Maps/GlobalMapController` — global map points
- `HomeController` — homepage stats
- `CommunityController` — community page
- `Leaderboard/LeaderboardController` — leaderboards
- `DisplayTagsOnMapController` — tag map
- `History/GetPaginatedHistoryController` — public history
- `Points/PointsController` — points API
- `MapController` — map clusters
- `User/ProfileController` — public profile

## Common Mistakes

- **Querying photos without `Photo::public()` scope on public-facing endpoints.** This leaks school team photos.
- **Setting `is_trusted = true` on school teams.** Trusted teams bypass teacher approval. School teams must always be `is_trusted = false`.
- **Broadcasting school data on public channels.** `SchoolDataApproved` must use private channel `team.{id}`.
- **Using non-deterministic ordering for safeguarding masks.** Masks must be based on `team_user.id` (join order), not photo data.
- **Forgetting `PhotoObserver` when creating photos in tests.** The observer auto-fires on `Photo::create()`. If testing non-school behavior, ensure `team_id` is null or team is community type.
- **Double-approving photos.** The `WHERE is_public = false` clause in the approval query prevents this, but don't remove it.
- **Using old category/object string format in updateTags.** `TeamPhotosController::updateTags()` uses CLO format (same as `PhotoTagsController::update`). Payload uses `category_litter_object_id`, NOT category/object key strings.
- **Forgetting `new_tags` in team photo responses.** Both `index()` and `show()` must include `new_tags` with `category_litter_object_id`, `litter_object_type_id`, and `extra_tags` for the facilitator queue tag editor to work.
