---
name: teams-safeguarding
description: Teams, school teams, team photos, approval flow, TeamPhotosController, privacy, is_public, PhotoObserver, MasksStudentIdentity, and safeguarding.
---

# Teams & Safeguarding

School teams enforce a private-by-default pipeline. Photos are invisible to the public until a teacher approves them. This protects minors and ensures data quality.

## Key Files

- `app/Http/Controllers/Teams/TeamPhotosController.php` — Photo listing, approval, tag editing, map points, delete, revoke
- `app/Observers/PhotoObserver.php` — Sets `is_public = false` on school team photo creation
- `app/Traits/MasksStudentIdentity.php` — Masks student names as "Student N"
- `app/Models/Teams/Team.php` — `isSchool()`, `isLeader()`, `hasSafeguarding()`
- `app/Models/Teams/TeamType.php` — `team` column: `'school'` or `'community'`
- `app/Actions/Teams/CreateTeamAction.php` — Team creation with school-specific fields
- `app/Http/Requests/Teams/CreateTeamRequest.php` — Validation + `school_manager` role check
- `app/Events/SchoolDataApproved.php` — Private broadcast on `team.{id}` channel
- `app/Listeners/NotifyTeamOfApproval.php` — Notifies team members after approval

## Invariants

1. **School photos start private.** `PhotoObserver::creating()` sets `is_public = false` when `team.isSchool()`. This is non-negotiable.
2. **All public queries use `Photo::public()` or `where('is_public', true)`.** Missing this leaks school data to maps, clusters, exports, and points API.
3. **School teams must NOT be `is_trusted`.** Trust bypasses the teacher approval step entirely. School teams default to `is_trusted = false`.
4. **Teacher approval is atomic and idempotent.** The `WHERE is_public = false` clause prevents double-processing of already-approved photos.
5. **Safeguarding uses deterministic numbering.** Student names are masked based on `team_user.id` (creation order), not photo data or pagination.
6. **SchoolDataApproved broadcasts on a private channel** (`team.{id}`). School team names (e.g., "St. X 1st Years 2026") must never appear on public channels.

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
