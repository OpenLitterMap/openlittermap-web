# OpenLitterMap v5 — Teams

## Overview

Teams allow groups of users to collaborate on litter mapping. Two team types exist:

| Type | Trust | Safeguarding | Photo Privacy | Approval Required |
|------|-------|-------------|---------------|-------------------|
| **Community** | Configurable | Off by default | Public | No |
| **School** | Always untrusted | Always on | Private until approved | Yes (teacher) |

**Golden rule:** School team photos are private (`is_public = false`) until a teacher approves them. This prevents student data from appearing on the public map or in aggregate metrics before adult review.

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
| school_roll_number | VARCHAR(50) | School-specific |
| contact_email | VARCHAR | School-specific |
| academic_year | VARCHAR(20) | School-specific |
| class_group | VARCHAR(100) | School-specific |
| county | VARCHAR(100) | School-specific |

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
- `app/Models/Teams/Team.php` — Team model with type accessor, relationships
- `app/Models/Teams/TeamType.php` — Team type lookup

### Controllers
- `app/Http/Controllers/Teams/TeamsController.php` — Web routes (create, join, leave, members)
- `app/Http/Controllers/API/TeamsController.php` — API routes (same ops, JSON responses)
- `app/Http/Controllers/Teams/TeamPhotosController.php` — Photo listing, approval, tag editing, map
- `app/Http/Controllers/Teams/TeamsDataController.php` — Dashboard stats + verification breakdown
- `app/Http/Controllers/Teams/TeamsLeaderboardController.php` — Team leaderboard
- `app/Http/Controllers/Teams/TeamsSettingsController.php` — Privacy settings per team
- `app/Http/Controllers/Teams/TeamsClusterController.php` — Map clustering for team photos

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
- `app/Console/Commands/Teams/AssignSchoolManager.php` — `php artisan school:assign-manager {email}`

### Tests
- `tests/Feature/Teams/TeamsTest.php` — Core CRUD, events, types, members, privacy (17 tests)
- `tests/Feature/Teams/TeamPhotosTest.php` — Photo listing, approval, editing, map, dashboard (21 tests)
- `tests/Feature/Teams/SchoolApprovalPipelineTest.php` — End-to-end approval pipeline (7 tests)
- `tests/Feature/Teams/SchoolPhotoPipelineTest.php` — Full photo pipeline integration (4 tests)
- `tests/Feature/Teams/CreateTeamTest.php` — Team creation validation
- `tests/Feature/Teams/JoinTeamTest.php` — Join flow
- `tests/Feature/Teams/SafeguardingTest.php` — Identity masking

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
| GET | `/teams/photos?team_id=X&status=pending\|approved\|all` | List photos | Required |
| GET | `/teams/photos/map?team_id=X` | Map points (up to 5000) | Required |
| GET | `/teams/photos/{photo}` | Single photo with tags | Required |
| PATCH | `/teams/photos/{photo}/tags` | Edit tags (leader/school_manager) | Required |
| POST | `/teams/photos/approve` | Approve photos (leader/school_manager) | Required |

### Dashboard & Leaderboard
| Method | Route | Action | Auth |
|--------|-------|--------|------|
| GET | `/teams/data?team_id=X&period=all\|today\|week\|month\|year` | Dashboard stats | Required |
| GET | `/teams/leaderboard` | Team leaderboard | Required |
| POST | `/teams/leaderboard/visibility` | Toggle visibility | Required |

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
- `TeamsController::members()` — member listing
- Any endpoint that uses the `MasksStudentIdentity` trait

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
- `county`: optional, max 100 chars
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

## Related Docs

| Document | Covers |
|----------|--------|
| **SchoolPipeline.md** | Full school approval pipeline (the critical data flow) |
| **Upload.md** | How photos enter the system, when MetricsService runs |
| **Metrics.md** | How MetricsService processes approved photos |
| **Leaderboards.md** | Leaderboard system — Redis ZSETs + MySQL per-user metrics |
| **Tags.md** | Tag hierarchy, summary JSON, XP calculation |
