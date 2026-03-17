# School Team Setup

## Overview

School teams are created by users with the `school_manager` role. Each school manager can create 1 team. The team automatically has safeguarding enabled and is never trusted (photos stay private until teacher approval).

For the full photo approval pipeline, see `readme/SchoolPipeline.md`.
For general team architecture, see `readme/Teams.md`.

---

## Roles & Permissions

| Role | Who | How Granted |
|------|-----|-------------|
| `school_manager` | Teacher / facilitator | Superadmin grants via `/admin/users` UI |
| Team leader | Same user who created the team | Automatic on creation |

Granting `school_manager` sets `remaining_teams = 1` if currently 0.

---

## Create School Team Flow

### Backend

**Route:** `POST /api/teams/create`
**Auth:** `auth:sanctum` + `school_manager` role (checked in `CreateTeamRequest::authorize()`)
**Action:** `CreateTeamAction::run()`

### Request Fields

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | Yes | Unique team name, 3-100 chars |
| `identifier` | string | Yes | Unique join code, 3-100 chars |
| `teamType` | integer | Yes | Must be the `school` type ID from `team_types` |
| `contact_email` | email | Yes | Teacher's contact email |
| `county` | string | Yes | Geographic area (required for LitterWeek reporting) |
| `academic_year` | string | No | e.g. "2025/2026" |
| `class_group` | string | No | e.g. "5th Class" |
| `logo` | image file | No | School logo, max 2MB (PNG/JPG) |
| `max_participants` | integer | No | Max students, 1-500 |
| `participant_sessions_enabled` | boolean | No | Enable participant session tokens (default false) |

### What Happens on Creation

1. `remaining_teams` check (must be > 0)
2. Logo uploaded to `logos` S3 disk under `school-logos/` prefix
3. Team created with `safeguarding = true`, `is_trusted = false`
4. Creator auto-joins and becomes team leader
5. `remaining_teams` decremented
6. `TeamCreated` event fires

---

## Logo Storage

School logos are stored on a **separate S3 bucket** from photo uploads:

| Disk | Bucket Env Var | Purpose |
|------|---------------|---------|
| `s3` | `AWS_BUCKET` | User photo uploads |
| `logos` | `AWS_LOGOS_BUCKET` | School team logos |

Config: `config/filesystems.php` → `disks.logos`

The `logos` disk has `visibility: public` for direct URL access.

**Local dev (MinIO):** Create an `openlittermap-logos` bucket in MinIO, or override via `AWS_LOGOS_BUCKET` env var.

---

## Database Columns (School-Specific)

On the `teams` table:

| Column | Type | Notes |
|--------|------|-------|
| `safeguarding` | boolean | Always `true` for school teams |
| `contact_email` | varchar | Teacher's email |
| `county` | varchar(100) | Geographic area |
| `academic_year` | varchar(20) | e.g. "2025/2026" |
| `class_group` | varchar(100) | e.g. "5th Class" |
| `logo` | varchar | S3 path on `logos` disk |
| `max_participants` | int unsigned | Max students allowed |
| `participant_sessions_enabled` | boolean | Whether participant sessions are enabled |

---

## Participant Sessions

Participant sessions allow students to participate **without creating real user accounts**. Facilitators pre-create numbered slots with session tokens. Students authenticate via token, and all photos are owned by the facilitator.

### How It Works

1. Facilitator enables `participant_sessions_enabled` during school team creation
2. Facilitator creates participant slots (e.g., 30 at once) — each gets a 64-char session token
3. Students enter their session code at `/session` — stored in localStorage
4. Student requests include `X-Participant-Token` header via `ParticipantAuth` middleware
5. Middleware resolves facilitator via `User::find($team->leader)` and calls `Auth::setUser($facilitator)`
6. Photos created with `user_id = facilitator`, `participant_id = student's slot`
7. MetricsService, XP, leaderboards are **completely untouched** — everything accrues to facilitator

### Key Invariant

`photos.user_id = team.leader` (facilitator) for ALL participant photos. The `participant_id` column is for attribution only — it has no effect on metrics, XP, or leaderboard credit.

### Database

**`participants` table:**

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT PK | Auto-increment |
| team_id | INT FK | → teams.id (CASCADE) |
| slot_number | SMALLINT | Sequential, unique per team |
| display_name | VARCHAR(100) | e.g. "Student 1" or custom name |
| session_token | CHAR(64) | Unique, never returned in JSON (model `$hidden`) |
| is_active | BOOLEAN | Default true; deactivate to revoke access |
| last_active_at | TIMESTAMP | Touched on each authenticated request (throttled 60s) |

**`photos` table addition:**

| Column | Type | Notes |
|--------|------|-------|
| participant_id | BIGINT FK | → participants.id (SET NULL on delete) |

### Middleware

`ParticipantAuth` (`app/Http/Middleware/ParticipantAuth.php`):
- Reads `X-Participant-Token` header
- Resolves participant + team, checks `hasParticipantSessions()`
- Sets `Auth::setUser($facilitator)` (stateless, NOT `Auth::login()`)
- Attaches `participant` and `participant_team` as request attributes

### API Endpoints

**Facilitator management** (auth:sanctum, leader only):

| Method | Route | Action |
|--------|-------|--------|
| GET | `/api/teams/{team}/participants` | List slots with photo_count |
| POST | `/api/teams/{team}/participants` | Create slots (count or named) |
| POST | `/api/teams/{team}/participants/{id}/deactivate` | Revoke session |
| POST | `/api/teams/{team}/participants/{id}/activate` | Re-enable session |
| POST | `/api/teams/{team}/participants/{id}/reset-token` | Regenerate token |
| DELETE | `/api/teams/{team}/participants/{id}` | Hard delete slot |

**Session entry** (public, no auth):

| Method | Route | Action |
|--------|-------|--------|
| POST | `/api/participant/session` | Validate token, return session info |

**Participant workspace** (token auth via `participant` middleware):

| Method | Route | Action |
|--------|-------|--------|
| POST | `/api/participant/upload` | Upload photo (reuses UploadPhotoController) |
| POST | `/api/participant/tags` | Tag own photo (reuses PhotoTagsController) |
| GET | `/api/participant/photos` | List own photos |
| DELETE | `/api/participant/photos/{photo}` | Delete own pre-approval photo |

### Frontend

- `CreateTeam.vue` — "Enable participant sessions" checkbox in school fields
- `/session` — `ParticipantEntry.vue` — token entry page (public)
- `/session/workspace` — `ParticipantWorkspace.vue` — upload/photos/tag tabs
- `ParticipantGrid.vue` — Tab in TeamDashboard for managing slots

### Test Coverage

`tests/Feature/Teams/ParticipantSessionTest.php` — 28 tests covering:
- Slot management (create, list, deactivate, activate, reset, delete, sequential)
- Token auth (valid, invalid, deactivated, disabled team)
- Participant photos (scoping, delete, approval guards)
- Facilitator queue integration (eager-load, member-stats, approval)
- Metrics invariant (accrue to facilitator)
- CreateTeam additions (county required, participant_sessions_enabled)

---

## Frontend

**Page:** `resources/js/views/Teams/CreateTeam.vue`
**Route:** `/teams/create`

- School fields only show when school type is selected
- Logo has drag-and-drop upload with preview
- Form uses `FormData` (multipart) for file upload
- Team store `createTeam()` handles FormData serialization

---

## Admin Management

Superadmins manage school managers from `/admin/users`:

- **Grant role:** Purple "+ School" button on user row → `POST /api/admin/users/{id}/school-manager`
- **Revoke role:** Same button toggles off
- Granting auto-sets `remaining_teams = 1` if currently 0

---

## Key Invariants

- School teams are NEVER `is_trusted` (photos must go through teacher approval)
- School team photos have `is_public = false` until teacher approves
- `safeguarding = true` enables student identity masking on the public map
- Admin queue excludes school photos (they have their own facilitator queue)
- `max_participants` enforced at participant slot creation (existing + new <= max)
- Participant photos: `user_id = facilitator`, `participant_id = student slot` — MetricsService untouched
- `hasParticipantSessions()` returns `participant_sessions_enabled && isSchool()` — community teams cannot have sessions

---

## Test Coverage

- `tests/Feature/Teams/CreateTeamTest.php` — 13 tests (create, school-specific, logo, validation)
- `tests/Feature/Teams/SchoolTeamLifecycleTest.php` — Full lifecycle (create → student upload → teacher approve)
- `tests/Feature/Teams/ParticipantSessionTest.php` — 28 tests (slots, token auth, photos, queue, metrics)
- `tests/Feature/Admin/AdminUsersTest.php` — School manager role grant/revoke (4 tests)
