# OpenLitterMap v5 — Teams

## Overview

Teams allow groups of users to collaborate on litter mapping. Two team types exist:

| Type | Trust | Safeguarding | Photo Privacy | Approval Required |
|------|-------|-------------|---------------|-------------------|
| **Community** | Configurable | Off by default | Public | No |
| **School** | Always untrusted | Always on | Private until approved | Yes (teacher) |

**Golden rule:** School team photos are always private (`is_public = false`) until a teacher approves them — this is enforced by `PhotoObserver` regardless of user settings. Community team photos respect the uploading user's `public_photos` default (and any per-photo `is_public` override), so a community team member who has set `public_photos=false` will have their uploads hidden from the map while still receiving full metrics. This prevents student data from appearing on the public map or in aggregate metrics before adult review.

---

## Team Types

Stored in the `team_types` table. Seeded by migrations:

| team | price | Description |
|------|-------|-------------|
| `community` | 0 | General-purpose team |
| `school` | 0 | LitterWeek / environmental education |

The `Team` model resolves its type name via `getTypeNameAttribute()`, which reads from the `teamType` relationship. Do NOT hardcode type_id-to-name mappings — IDs vary between environments.

---

## Database Schema

### `teams` table

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT | PK |
| name | VARCHAR | Unique |
| identifier | VARCHAR | Unique join code |
| type_id | INT FK | → team_types.id |
| type_name | VARCHAR | Denormalized type name |
| leader | INT FK | → users.id |
| created_by | INT FK | → users.id |
| members | INT | Counter (default 1) |
| is_trusted | BOOLEAN | Whether tags auto-verify |
| safeguarding | BOOLEAN | Student identity masking |
| leaderboards | BOOLEAN | Whether team appears on leaderboards |
| contact_email | VARCHAR | School-specific |
| academic_year | VARCHAR(20) | School-specific |
| class_group | VARCHAR(100) | School-specific |
| county | VARCHAR(100) | School-specific |
| logo | VARCHAR | Path on `logos` disk (S3) |
| max_participants | INT UNSIGNED | Max students for school teams |
| participant_sessions_enabled | BOOLEAN | Enable token-based participant sessions |

### `participants` table

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | Auto-increment |
| team_id | INT FK | → teams.id (CASCADE) |
| slot_number | SMALLINT | Unique per team |
| display_name | VARCHAR(100) | Student label |
| session_token | CHAR(64) | Unique, hidden from JSON |
| is_active | BOOLEAN | Default true |
| last_active_at | TIMESTAMP | Last authenticated request |

### `photos` table (participant column)

| Column | Type | Notes |
|--------|------|-------|
| participant_id | BIGINT FK | → participants.id (SET NULL) |

### `team_user` pivot table

| Column | Type | Notes |
|--------|------|-------|
| team_id | INT FK | → teams.id |
| user_id | INT FK | → users.id |
| show_name_maps | BOOLEAN | Privacy preference |
| show_username_maps | BOOLEAN | Privacy preference |
| show_name_leaderboards | BOOLEAN | Privacy preference |
| show_username_leaderboards | BOOLEAN | Privacy preference |

### `users` columns (team-related)

| Column | Type | Notes |
|--------|------|-------|
| active_team | INT FK | Currently active team (nullable) |
| remaining_teams | INT | How many more teams user can create |

---

## Key Files

### Models
- `app/Models/Teams/Team.php` — Team model with type accessor, relationships, `hasParticipantSessions()`
- `app/Models/Teams/TeamType.php` — Team type lookup
- `app/Models/Teams/Participant.php` — Participant slot model (token, activation, relationships)

### Controllers
- `app/Http/Controllers/Teams/TeamsController.php` — Web routes (create, join, leave, members)
- `app/Http/Controllers/API/TeamsController.php` — API routes (same ops, JSON responses)
- `app/Http/Controllers/Teams/TeamPhotosController.php` — Photo listing, approval, tag editing, map, delete, revoke
- `app/Http/Controllers/Teams/TeamsDataController.php` — Dashboard stats + verification breakdown
- `app/Http/Controllers/Teams/TeamsLeaderboardController.php` — Team leaderboard
- `app/Http/Controllers/Teams/TeamsSettingsController.php` — Privacy settings per team
- `app/Http/Controllers/Teams/TeamsClusterController.php` — Map clustering for team photos
- `app/Http/Controllers/Teams/ParticipantController.php` — Facilitator CRUD for participant slots
- `app/Http/Controllers/Teams/ParticipantSessionController.php` — Token validation + session entry
- `app/Http/Controllers/Teams/ParticipantPhotoController.php` — Participant's own photos (list, delete)

### Middleware
- `app/Http/Middleware/ParticipantAuth.php` — Token auth for participant workspace routes

### Actions
- `app/Actions/Teams/CreateTeamAction.php` — Creates team, dispatches `TeamCreated`
- `app/Actions/Teams/JoinTeamAction.php` — Join by identifier
- `app/Actions/Teams/LeaveTeamAction.php` — Leave team, clears active_team
- `app/Actions/Teams/SetActiveTeamAction.php` — Set user's active team
- `app/Actions/Teams/UpdateTeamAction.php` — Update name/identifier (leader only)
- `app/Actions/Teams/DownloadTeamDataAction.php` — Export team data

### Validation
- `app/Http/Requests/Teams/CreateTeamRequest.php` — School teams require `school_manager` role + extra fields
- `app/Http/Requests/Teams/JoinTeamRequest.php`
- `app/Http/Requests/Teams/LeaveTeamRequest.php`
- `app/Http/Requests/Teams/UpdateTeamRequest.php`

### Events
- `app/Events/TeamCreated.php` — `(Team $team)` — broadcasts to private channel for schools
- `app/Events/SchoolDataApproved.php` — `(Team $team, User $approvedBy, int $photoCount)` — broadcasts approval notification
- `app/Events/TagsVerifiedByAdmin.php` — `(photo_id, user_id, country_id, state_id, ?city_id, ?team_id)` — triggers MetricsService

### Observer
- `app/Observers/PhotoObserver.php` — Sets `is_public = false` on `creating()` for school team photos

### Traits
- `app/Traits/MasksStudentIdentity.php` — Deterministic pseudonym masking ("Student 1", "Student 2", etc.)

### Commands
- `app/Console/Commands/Teams/AssignSchoolManager.php` — `php artisan school:assign-manager {email}` (queues `SchoolManagerInvite` email)

### Mailables
- `app/Mail/SchoolManagerInvite.php` — Queued email sent when `school_manager` role is granted (from artisan command or admin toggle). Two CTAs: "Upload Your First Photos" → `/upload`, "Create Your School Team" → `/teams/create`

### Tests
- `tests/Feature/Teams/TeamsTest.php` — Core CRUD, events, types, members, privacy (17 tests)
- `tests/Feature/Teams/TeamPhotosTest.php` — Photo listing, approval, CLO tag editing, new_tags format, member stats, map, dashboard, delete, revoke, safeguarding (35 tests)
- `tests/Feature/Teams/SchoolApprovalPipelineTest.php` — End-to-end approval pipeline (7 tests)
- `tests/Feature/Teams/SchoolPhotoPipelineTest.php` — Full photo pipeline integration (4 tests)
- `tests/Feature/Teams/CreateTeamTest.php` — Team creation validation
- `tests/Feature/Teams/JoinTeamTest.php` — Join flow
- `tests/Feature/Teams/SafeguardingTest.php` — Identity masking
- `tests/Feature/Teams/ParticipantSessionTest.php` — Participant slots, token auth, photos, metrics (28 tests)

---

## API Routes

All under `/api/teams`, most require `auth:api` middleware.

### Team Management
| Method | Route | Action | Auth |
|--------|-------|--------|------|
| GET | `/teams/types` | List team types | Public |
| GET | `/teams/joined` | User's teams | Required |
| GET | `/teams/list` | User's teams (API) | Required |
| GET | `/teams/members` | Paginated members (with safeguarding) | Required |
| POST | `/teams/create` | Create team | Required |
| POST | `/teams/join` | Join by identifier | Required |
| POST | `/teams/leave` | Leave team | Required |
| POST | `/teams/active` | Set active team | Required |
| POST | `/teams/inactivate` | Clear active team | Required |
| PATCH | `/teams/update/{team}` | Update name/identifier (leader only) | Required |
| POST | `/teams/settings` | Privacy settings | Required |

### Team Photos (school approval pipeline)
| Method | Route | Action | Auth |
|--------|-------|--------|------|
| GET | `/teams/photos?team_id=X&status=pending\|approved\|all` | List photos (with `new_tags`) | Required |
| GET | `/teams/photos/map?team_id=X` | Map points (up to 5000) | Required |
| GET | `/teams/photos/member-stats?team_id=X` | Per-student stats (leader only) | Required |
| GET | `/teams/photos/{photo}` | Single photo with tags (with `new_tags`) | Required |
| PATCH | `/teams/photos/{photo}/tags` | Edit tags — CLO format (leader/school_manager) | Required |
| POST | `/teams/photos/approve` | Approve photos (leader/school_manager) | Required |
| DELETE | `/teams/photos/{photo}?team_id=X` | Delete photo (leader/school_manager) | Required |
| POST | `/teams/photos/revoke` | Revoke approval (leader/school_manager) | Required |

### Participant Management (leader only)
| Method | Route | Action | Auth |
|--------|-------|--------|------|
| GET | `/teams/{team}/participants` | List participant slots | Required |
| POST | `/teams/{team}/participants` | Create slots in bulk | Required |
| POST | `/teams/{team}/participants/{id}/deactivate` | Revoke session | Required |
| POST | `/teams/{team}/participants/{id}/activate` | Re-enable session | Required |
| POST | `/teams/{team}/participants/{id}/reset-token` | Regenerate token | Required |
| DELETE | `/teams/{team}/participants/{id}` | Delete slot | Required |

### Participant Session (token auth)
| Method | Route | Action | Auth |
|--------|-------|--------|------|
| POST | `/participant/session` | Validate token | Public |
| POST | `/participant/upload` | Upload photo | Token |
| POST | `/participant/tags` | Tag own photo | Token |
| GET | `/participant/photos` | List own photos | Token |
| DELETE | `/participant/photos/{photo}` | Delete own photo | Token |

### Dashboard & Leaderboard
| Method | Route | Action | Auth |
|--------|-------|--------|------|
| GET | `/teams/data?team_id=X&period=all\|today\|week\|month\|year` | Dashboard stats | Required |
| GET | `/teams/leaderboard` | Team leaderboard | Required |
| POST | `/teams/leaderboard/visibility` | Toggle visibility | Required |

### Team Response Shape (list + leaderboard)

Both `GET /api/teams/list` and `GET /api/teams/leaderboard` return teams with consistent field naming:

```json
{
    "id": 1,
    "name": "Team Name",
    "type_name": "community",
    "total_members": 5,
    "total_tags": 1200,
    "total_images": 300,
    "created_at": "2025-01-15T10:00:00.000000Z",
    "updated_at": "2026-02-28T14:30:00.000000Z"
}
```

The `list` endpoint also includes `identifier` (join code). Uses `total_tags`/`total_images`/`total_members` — never `total_litter`/`members`.

---

## Permissions & Roles

Uses Spatie Laravel Permission 6. All on `web` guard.

### Permissions
| Permission | Purpose |
|-----------|---------|
| `create school team` | Create a school-type team |
| `manage school team` | Approve photos, edit tags, manage team |
| `toggle safeguarding` | Enable/disable safeguarding on a team |
| `view student identities` | See real student names even with safeguarding |

### Roles
| Role | Permissions |
|------|-------------|
| `school_manager` | All four above |

### Assignment
```bash
php artisan school:assign-manager user@example.com
```

Both the artisan command and `AdminUsersController::toggleSchoolManager()` queue a `SchoolManagerInvite` email when the role is granted. Revoking the role does not send an email.

---

## Trust Model

| Team Property | Effect |
|---------------|--------|
| `is_trusted = true` | Tags auto-verify → `TagsVerifiedByAdmin` fires immediately → MetricsService processes |
| `is_trusted = false` | Tags stay at `VERIFIED` (1) — no metrics event until approval |

**School teams MUST be `is_trusted = false`.** If a school team were trusted, student tags would immediately flow through MetricsService into public aggregate data (country totals, leaderboards) before teacher review. The photo would be hidden from the map (`is_public = false`), but aggregate data would leak.

Teacher approval IS the verification event for school photos. See `readme/SchoolPipeline.md`.

---

## Safeguarding (Identity Masking)

When `team.safeguarding = true`, student names are replaced with deterministic pseudonyms in API responses.

### Who sees what

| Viewer | Names visible? |
|--------|---------------|
| Team leader | Real names |
| User with `view student identities` permission | Real names |
| Students / other members | Masked ("Student 1", "Student 2") |

### How masking works

The `MasksStudentIdentity` trait builds a stable mapping from `team_user.id` ordering:

```php
$memberOrder = DB::table('team_user')
    ->where('team_id', $team->id)
    ->where('user_id', '!=', $team->leader)
    ->orderBy('id')
    ->pluck('user_id')
    ->flip()
    ->map(fn ($index) => 'Student ' . ($index + 1));
```

Numbering is deterministic — "Student 3" is always the same person regardless of which page or endpoint.

### Where masking is applied
- `TeamPhotosController::index()` — photo listing
- `TeamPhotosController::memberStats()` — per-student stats
- `TeamsController::members()` — member listing
- Any endpoint that uses the `MasksStudentIdentity` trait

---

## Delete & Revoke (Teacher Actions)

### Delete Photo

`DELETE /api/teams/photos/{photo}?team_id=X` — Teacher permanently removes a photo.

1. Validates authorization (leader or `manage school team` permission)
2. If photo was processed (`processed_at` set): calls `MetricsService::deletePhoto()` to reverse all metrics
3. Runs `DeletePhotoAction` to clean up S3 files
4. Soft-deletes the photo
5. Decrements the **photo owner's** XP and `total_images` (not the teacher's)
6. Returns updated team stats

### Revoke Approval

`POST /api/teams/photos/revoke` — Teacher un-publishes approved photos.

Accepts `{ team_id, photo_ids: [...] }` or `{ team_id, revoke_all: true }`.

1. Validates authorization (leader or `manage school team`)
2. Builds query: photos WHERE `team_id = X AND is_public = true AND team_approved_at IS NOT NULL`
3. For each processed photo: calls `MetricsService::deletePhoto()` to reverse metrics
4. Atomic UPDATE: `is_public = false`, `verified = VERIFIED`, `team_approved_at = null`, `team_approved_by = null`
5. Returns `{ success, revoked_count }`

**Idempotent:** Already-private photos are filtered out by the WHERE clause. Revoking twice is a no-op.

### Safeguarding on Global Map

When a school team has `safeguarding = true`, the `PointsController::formatFeatures()` method masks student identity in map popups:
- Sets `name`, `username`, and `social` to `null`
- Preserves `team` name for attribution: "Contributed by [Team Name]"
- Implemented via `team:id,name,safeguarding` eager load in PointsController

---

## Facilitator Queue (3-Panel Verification UI)

School team leaders have access to a full verification queue similar to the admin queue, scoped to their team.

### Frontend Components
- `FacilitatorQueue.vue` — 3-panel layout (filters | photo viewer | tag editor)
- `FacilitatorQueueHeader.vue` — Navigation, action buttons (Approve, Save Edits, Revoke, Delete)
- `FacilitatorQueueFilters.vue` — Status toggle (pending/approved/all), date range
- `TeamMembersList.vue` — Per-student stats table

### Reused Components (from Tagging v2)
- `PhotoViewer.vue` — Photo display with zoom/pan
- `UnifiedTagSearch.vue` — Fuzzy tag search (objects, types, brands, materials, custom)
- `ActiveTagsList.vue` — Active tags with quantity, picked_up, brands, materials, custom tags
- `TagCard.vue` — Individual tag display

### Tag Format
Both index and show endpoints return `new_tags` — the CLO-based format that `hydrateTagsForPhoto()` uses:

```json
{
    "new_tags": [
        {
            "id": 123,
            "category_litter_object_id": 45,
            "litter_object_type_id": null,
            "quantity": 3,
            "picked_up": true,
            "category": { "id": 1, "key": "smoking" },
            "object": { "id": 10, "key": "cigarette_butt" },
            "extra_tags": [
                { "type": "brand", "quantity": 1, "tag": { "id": 5, "key": "marlboro" } }
            ]
        }
    ]
}
```

### Tag Editing (CLO Format)
`PATCH /api/teams/photos/{photo}/tags` now accepts CLO-based payload (same as `PhotoTagsController::store`):

```json
{
    "tags": [
        {
            "category_litter_object_id": 45,
            "quantity": 3,
            "picked_up": true,
            "materials": [{ "id": 1, "quantity": 1 }],
            "brands": [{ "id": 5, "quantity": 1 }],
            "custom_tags": [{ "tag": "stained", "quantity": 1 }]
        }
    ]
}
```

Internally deletes existing tags, resets summary/xp/verified, then calls `AddTagsToPhotoAction::run()`.

### Keyboard Shortcuts
| Key | Action |
|-----|--------|
| A | Approve current photo |
| D | Delete (with confirmation) |
| E | Save edits (when modified) |
| R | Revoke approval (with confirmation) |
| S / K / ArrowRight | Next photo |
| J / ArrowLeft | Previous photo |
| Escape | Clear search |

### Member Stats
`GET /api/teams/photos/member-stats?team_id=X` — Leader/school_manager only.

Returns per-student stats:
```json
{
    "members": [
        {
            "user_id": 42,
            "name": "Student 1",
            "username": null,
            "total_photos": 15,
            "pending": 3,
            "approved": 12,
            "litter_count": 87,
            "last_active": "2026-02-28 14:30:00"
        }
    ]
}
```

When safeguarding is enabled, names are deterministic pseudonyms and usernames are null.

---

## Team Creation Validation

### Community teams
- `name`: required, 3-100 chars, unique
- `identifier`: required, 3-100 chars, unique
- `teamType`: required, must exist in team_types

### School teams (additional)
- User must have `school_manager` role (403 otherwise)
- `contact_email`: required, valid email
- `school_roll_number`: optional, max 50 chars
- `county`: required, max 100 chars
- `academic_year`: optional, max 20 chars
- `class_group`: optional, max 100 chars

---

## Dashboard Stats

`TeamsDataController::index()` returns live stats from the `photos` table:

```json
{
    "photos_count": 42,
    "litter_count": 185,
    "members_count": 7,
    "verification": {
        "unverified": 5,
        "verified": 12,
        "admin_approved": 20,
        "bbox_applied": 3,
        "bbox_verified": 2,
        "ai_ready": 0
    }
}
```

- `litter_count` sums `total_tags` for ADMIN_APPROVED+ photos only
- `members_count` is distinct user_ids in the period
- Supports period filtering: `all`, `today`, `week`, `month`, `year`

---

## Frontend Architecture

### TeamsHub (`/teams` route)

The teams frontend uses a single-page hub pattern. `TeamsHub.vue` replaces the old `TeamsLayout.vue` sidebar navigation.

**Three states:**
1. **No teams** → Landing page with Create/Join actions
2. **Has team(s)** → Active team dashboard with header (team name, type badge, team switcher), stats row, and tab navigation
3. **No active team but has teams** → Prompts to pick an active team

**Tabs:** Overview | Photos | Map | Members | Settings | Leaderboard | Approval Queue (school+leader) | Participants (school+sessions+leader)

**Key files:**
- `resources/js/views/Teams/TeamsHub.vue` — Main hub component
- `resources/js/views/Teams/TeamOverview.vue` — Overview tab (stats, team info, all teams list)
- `resources/js/views/Teams/TeamSettingsTab.vue` — Consolidated settings tab
- `resources/js/views/Teams/CreateTeam.vue` — Standalone creation page at `/teams/create`

**Routes:**
| Path | Component | Purpose |
|------|-----------|---------|
| `/teams` | `TeamsHub.vue` | Team-centric hub |
| `/teams/create` | `CreateTeam.vue` | Standalone create page |

### Privacy Defaults

- **All new teams:** `leaderboards = false` by default (opt-in via settings)
- **School teams:** `safeguarding = true` enforced on creation (cannot be disabled)
- **School teams:** `is_trusted = false` enforced on creation (cannot be changed)

### School Manager Onboarding

When a school team is created, the leader sees a "Getting Started" checklist in the Overview tab covering: upload photos, create participant sessions (if enabled), review the approval queue. The facilitator queue includes an explainer for first-time users. Nav badge shows pending photo count.

---

## Related Docs

| Document | Covers |
|----------|--------|
| **SchoolPipeline.md** | Full school approval pipeline (the critical data flow) |
| **Upload.md** | How photos enter the system, when MetricsService runs |
| **Metrics.md** | How MetricsService processes approved photos |
| **Leaderboards.md** | Leaderboard system — Redis ZSETs + MySQL per-user metrics |
| **Tags.md** | Tag hierarchy, summary JSON, XP calculation |
