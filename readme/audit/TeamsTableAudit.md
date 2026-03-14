# Teams Table Audit

**Audited:** 2026-03-14

---

## Table Inventory

| Table | Rows | Purpose |
|-------|------|---------|
| `teams` | 295 | Team definitions |
| `team_user` | 557 | User↔team pivot (membership + per-member stats) |
| `team_types` | 2 | Team type definitions (community, school) |
| `participants` | 0 | Participant session slots for school teams |
| `dirty_teams` | 0 | Dirty team tracking for clustering |

---

## Phase A — Safe Now

### Teams Table

| Column | Type | Grep Evidence | Notes |
|--------|------|--------------|-------|
| `images_remaining` | int unsigned | Only in Team model `$fillable`, User model `$fillable`, RegisterController (writes `1000` to users, not teams) | Never read on teams table. The User model reference is for users.images_remaining, not teams. |
| `total_images` | int unsigned | Removed from `$fillable` and all reads (2026-03-14). Replaced with `withCount('teamPhotos')` live queries. |
| `total_litter` | int unsigned | Removed from `$fillable` and all reads (2026-03-14). Replaced with `withSum('teamPhotos', 'total_tags')` live queries. |

**Total: 3 columns safe to drop from teams**

### team_user Table

| Column | Type | Status |
|--------|------|--------|
| `total_litter` | int unsigned | Code references removed (2026-03-14). `ListTeamMembersAction` now uses live subquery. Safe to drop. |

All other columns actively used. `total_photos` written by `UpdateStatsForTeam` command. Privacy toggles (`show_name_maps`, `show_username_maps`, `show_name_leaderboards`, `show_username_leaderboards`) used by TeamsLeaderboardController and map display.

### team_types Table — No Drops

All 5 columns active (id, team, price, description, created_at, updated_at). Referenced by 6 files including CreateTeamAction, TeamsController.

### participants Table — No Drops

All 9 columns active. Part of participant sessions system (28 tests).

### dirty_teams Table — No Drops

All 3 columns active. Clustering dirty-team tracking.

### Indexes — No Drops

All indexes across all team tables are justified:

**teams (4 indexes):**
| Index | Columns | Status |
|-------|---------|--------|
| `PRIMARY` | id | Required |
| `teams_name_unique` | name | Unique team names |
| `teams_leader_foreign` | leader | FK index |
| `teams_type_id_foreign` | type_id | FK index |

**team_user (5 indexes):**
| Index | Columns | Status |
|-------|---------|--------|
| `PRIMARY` | id | Required |
| `team_user_team_id_user_id_unique` | (team_id, user_id) | Dedup guard |
| `team_user_user_id_team_id_idx` | (user_id, team_id) | Reverse lookup |
| `team_user_team_id_foreign` | team_id | FK index |
| `team_user_user_id_foreign` | user_id | FK index |

**participants (3 indexes):** All justified (PK, session_token unique, team_id+slot_number unique).

**dirty_teams (2 indexes):** All justified (PK on team_id, changed_at+attempts composite).

---

## Phase B — Post-Migration

No remaining Phase B columns — `total_images` and `total_litter` moved to Phase A after code references were removed (2026-03-14).

---

## Active — Do Not Touch

### Teams Table

| Column | Usage |
|--------|-------|
| `id`, `name` | Core identity |
| `members` | Member count — read in API responses (6 files) |
| `leader` | Team leader FK — authorization checks (9 files) |
| `type_id`, `type_name` | Team type (community/school) — CreateTeamAction writes, API reads |
| `created_by` | Creator FK |
| `identifier` | Unique team identifier |
| `leaderboards` | Boolean toggle for leaderboard visibility (5 files) |
| `is_trusted` | Trust status — affects photo verification pipeline |
| `safeguarding` | School safeguarding mode — masks student identity |
| `contact_email` | School team contact (write on creation, stored) |
| `academic_year` | School team field (write on creation, stored) |
| `class_group` | School team field (write on creation, stored) |
| `county` | School team field (required for school teams) |
| `logo` | School team logo (S3 upload, read in participant sessions) |
| `max_participants` | Participant session limit |
| `participant_sessions_enabled` | Toggle for participant session feature |
| `created_at`, `updated_at` | Timestamps |

### Notes

- `academic_year`, `class_group`, `county`, `contact_email` are school-only fields — written on creation but not currently exposed in API responses. They're stored data, not deprecated.
- `images_remaining` on teams is the only truly unused column — it's in `$fillable` but never read or written for teams specifically. The `images_remaining` references in User model and RegisterController are for the **users** table column, not teams.
