# Auth & Infrastructure Table Audit

**Audited:** 2026-03-14

---

## Auth Tables — All Active

### Passport (OAuth2)

| Table | Status | Notes |
|-------|--------|-------|
| `oauth_access_tokens` | Active | Passport token storage |
| `oauth_auth_codes` | Active | Passport auth codes |
| `oauth_clients` | Active | Passport clients |
| `oauth_personal_access_clients` | Active | Passport PAC |
| `oauth_refresh_tokens` | Active | Passport refresh tokens |

### Sanctum

| Table | Status | Notes |
|-------|--------|-------|
| `personal_access_tokens` | Active | Sanctum token storage (mobile auth) |

### Spatie Permission

| Table | Status | Notes |
|-------|--------|-------|
| `roles` | Active | Admin roles (superadmin, admin, helper, school_manager) |
| `permissions` | Active | Spatie permissions |
| `model_has_roles` | Active | User↔role pivot |
| `model_has_permissions` | Active | User↔permission pivot |
| `role_has_permissions` | Active | Role↔permission pivot |

### Password Reset

| Table | Status | Notes |
|-------|--------|-------|
| `password_resets` | Active | Laravel password reset tokens |

---

## Infrastructure Tables

| Table | Status | Notes |
|-------|--------|-------|
| `migrations` | Active | Laravel migration tracking |
| `failed_jobs` | Active | Laravel failed job tracking |
| `telescope_entries` | Active | Laravel Telescope (debug/monitoring) |
| `telescope_entries_tags` | Active | Telescope tags |
| `telescope_monitoring` | Active | Telescope monitoring |
| `pulse_aggregates` | Active | Laravel Pulse metrics |
| `pulse_entries` | Active | Pulse entries |
| `pulse_values` | Active | Pulse values |

### Potentially Orphaned

| Table | Status | Notes |
|-------|--------|-------|
| `websockets_statistics_entries` | Orphaned | Old Pusher/WebSocket stats — Reverb replaced it. 0 references in app/. Safe to drop if not using old WebSocket package. |

---

## No Drops Recommended

All auth and core infrastructure tables are managed by Laravel packages. The only candidate is `websockets_statistics_entries` if you've fully moved to Reverb.
