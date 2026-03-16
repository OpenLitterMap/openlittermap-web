---
name: olm-architecture
description: >
  OpenLitterMap v5 architecture reference. Use this skill whenever working on OLM backend code,
  Laravel controllers, services, events, tests, Redis, metrics, tags, photos, teams, admin,
  leaderboards, or any part of the OpenLitterMap codebase. Also trigger when the user mentions
  MetricsService, VerificationStatus, PhotoTags, AddTagsToPhotoAction, TagsVerifiedByAdmin,
  rewardXpToAdmin, school pipeline, teacher approval, clustering, or any OLM-specific concept.
  This skill should be used BEFORE writing any OLM code to avoid architectural mistakes.
  When in doubt about OLM patterns, read this skill.
---

# OpenLitterMap v5 — Architecture Reference

This is the canonical reference for OLM's v5 architecture. Read this before writing any backend code.

## Core Principle: Every Tag Change Flows Through MetricsService

Nothing writes metrics directly. Every tag creation, edit, or deletion flows through a pipeline that ensures metrics, leaderboards, Redis, and the database stay in sync.

## The Tag Pipeline

```
User/Admin/Teacher tags a photo
    ↓
AddTagsToPhotoAction::run($userId, $photoId, $tags)
    ├── Resolves tags:
    │   ├── Object tags: uses category_litter_object_id (CLO ID), auto-resolves category
    │   ├── Custom-only: $tag['custom'] = true, $tag['key'] = "dirty-bench"
    │   ├── Brand-only: $tag['brand_only'] = true
    │   └── Material-only: $tag['material_only'] = true
    ├── Creates PhotoTag + PhotoTagExtraTags rows
    ├── Calls GeneratePhotoSummaryService
    │   ├── Builds summary JSON from PhotoTags (numeric ID keys)
    │   ├── Calculates XP via XpScore enum multipliers
    │   └── Writes photo.summary, photo.xp, photo.total_tags
    ↓
updateVerification() — WHO tagged it?
    ├── Trusted user → verified = ADMIN_APPROVED, fires TagsVerifiedByAdmin
    ├── Non-trusted user → verified = UNVERIFIED (0), fires TagsVerifiedByAdmin
    │   (Photo NOT on map, but user gets immediate leaderboard credit)
    ├── School student → verified = VERIFIED (1), does NOT fire event
    │   (Waits for teacher approval — safeguarding invariant)
    ↓
TagsVerifiedByAdmin event (fires for ALL non-school users)
    → ProcessPhotoMetrics listener
        → MetricsService::processPhoto($photo)
            ├── Computes fingerprint from summary JSON
            ├── If new (processed_at null): creates metric rows
            ├── If re-edit (processed_at set): computes delta
            ├── Upserts metrics table (5 timescales × 4 location scopes × 2 user modes)
            ├── Updates Redis (stats hashes, XP ZSETs, bitmaps, HLLs)
            └── Sets photo.processed_at, processed_fp, processed_tags, processed_xp
```

**Key distinction:** `TagsVerifiedByAdmin` fires for ALL non-school users at tag time. Trusted users also get `verified = ADMIN_APPROVED` (photo visible on map). Non-trusted users stay at `verified = 0` (photo NOT on map, but user IS on leaderboard). Only school students' photos wait for teacher approval.

## VerificationStatus Enum

```php
enum VerificationStatus: int
{
    case UNVERIFIED = 0;      // Uploaded, no tags (also: tagged by non-trusted non-school user)
    case VERIFIED = 1;        // Tagged by school student (awaiting teacher)
    case ADMIN_APPROVED = 2;  // Approved by admin/trusted/teacher
    case BBOX_APPLIED = 3;    // Bounding boxes drawn
    case BBOX_VERIFIED = 4;   // Bounding boxes verified
    case AI_READY = 5;        // Ready for model training
}
```

**Critical rule:** Use `->value >= VerificationStatus::ADMIN_APPROVED->value` (not `== 2`) when checking if a photo is public-ready. Photos at BBOX_APPLIED+ are also approved.

The enum is cast on the Photo model: `'verified' => VerificationStatus::class`. Use `->value` for `>=`/`<` comparisons, direct enum for `===` equality.

## The Atomic Approve Pattern

Used everywhere: admin approve, teacher approve, batch approve.

```php
$updated = Photo::where('id', $photo->id)
    ->where('is_public', true)
    ->where('verified', '<', VerificationStatus::ADMIN_APPROVED->value)
    ->update(['verified' => VerificationStatus::ADMIN_APPROVED->value]);

if ($updated > 0) {
    event(new TagsVerifiedByAdmin(
        photo_id: $photo->id,
        user_id: $photo->user_id,
        country_id: $photo->country_id,
        state_id: $photo->state_id,
        city_id: $photo->city_id,
        team_id: $photo->team_id
    ));
    rewardXpToAdmin();
}
```

**Why atomic:** Prevents double-processing. If two admins approve simultaneously, the WHERE clause ensures only one succeeds. The second gets `$updated = 0` and no event fires.

**Always pass all 6 named constructor args** to TagsVerifiedByAdmin. Missing args cause listener failures.

## MetricsService

**Single writer rule.** Only `MetricsService` writes to the `metrics` table and Redis metric keys. No other code may increment/decrement counters. Never use `DB::table('metrics')->increment()` or `Redis::hincrby()` directly.

### processPhoto($photo)
- Computes fingerprint from summary JSON
- If `processed_at` is null: first processing → `doCreate()` (creates metrics + increments `users.xp`)
- If `processed_at` is set: re-processing → `doUpdate()` (computes delta, applies corrections to `users.xp`)
- Upserts `metrics` table rows: 5 timescales × 4 location scopes × 2 (aggregate user_id=0 + per-user user_id>0)
- Updates Redis via `RedisMetricsCollector` (inside `DB::afterCommit`)
- Sets `photo.processed_at`, `processed_fp`, `processed_tags`, `processed_xp`

### recordUploadMetrics($photo, $uploadXp)
- Gated on school team check: `$photo->team_id && $team->isSchool()` — school photos skip entirely (deferred to approval)
- NOT gated on `is_public` — private-by-choice photos still get immediate upload XP
- For non-school photos: writes 1 upload + upload XP to metrics/Redis, sets `processed_at`
- At tag time, `processPhoto()` routes to `doUpdate()` (delta-based) since `processed_at` is set

### deletePhoto($photo)
- **Must run BEFORE `$photo->delete()`** — hard sequencing rule
- Reverses all metrics that processPhoto created
- Only runs if `processed_at` is not null
- Clears all processed_* columns
- Prunes zero-XP members from Redis leaderboard ZSETs

```php
// CORRECT order
if ($photo->processed_at) {
    $this->metricsService->deletePhoto($photo);
}
$photo->delete(); // soft delete

// WRONG — metrics can't read a deleted photo
$photo->delete();
$this->metricsService->deletePhoto($photo); // too late
```

## Photo Summary JSON

Every tagged photo has a `summary` JSON column with numeric ID keys:

```json
{
    "tags": {
        "2": {
            "15": {
                "quantity": 5,
                "materials": { "3": 5, "7": 5 },
                "brands": { "12": 3 },
                "custom_tags": {}
            }
        }
    },
    "totals": {
        "total_tags": 10, "total_objects": 5,
        "by_category": { "2": 5 },
        "materials": 10, "brands": 3, "custom_tags": 0
    },
    "keys": {
        "categories": { "2": "smoking" },
        "objects": { "15": "butts" },
        "materials": { "3": "plastic", "7": "paper" },
        "brands": { "12": "marlboro" }
    }
}
```

Structure: `tags.{categoryId}.{objectId}.{quantity, materials, brands, custom_tags}` with `keys` for human-readable reverse lookup. Generated by `GeneratePhotoSummaryService` from PhotoTag rows. If summary is null, the photo has no tags and cannot be approved.

## PhotoTags (v5 Tag System)

```sql
photo_tags (
    id, photo_id,
    category_id,                  -- FK → categories (auto-resolved from object)
    litter_object_id,             -- FK → litter_objects
    category_litter_object_id,    -- FK → category_litter_object pivot (CLO ID)
    litter_object_type_id,        -- FK → litter_object_types (e.g., "beer" in "beer bottle")
    custom_tag_primary_id,        -- for custom-only tags (no category/object)
    quantity, picked_up,
    created_at, updated_at
)

photo_tag_extra_tags (
    id, photo_tag_id,
    tag_type,      -- 'material' | 'brand' | 'custom_tag'
    tag_type_id,   -- FK → materials / brandslist / custom_tags_new
    quantity, index,
    created_at, updated_at
)
```

**v4 vs v5:** v4 stored tags in 16+ separate category tables (smoking_id, food_id, etc on photos). v5 uses the unified PhotoTags table. Legacy v4 endpoints and the `ConvertV4TagsAction` shim have been removed (2026-03-01). Mobile now uses v3 endpoints with CLO format. Category FK columns on photos are deprecated.

**Frontend sends CLO IDs:** The web frontend sends `category_litter_object_id` (pre-resolved from the search index). Backend auto-resolves `category_id` from the CLO pivot. Category need NOT be sent separately.

## School Privacy — The is_public Gate

**Hard rule:** School team photos have `is_public = false` until a teacher approves them.

```php
// PhotoObserver::creating() — automatic privacy
if ($photo->team_id) {
    $team = Team::find($photo->team_id);
    if ($team && $team->isSchool()) {
        $photo->is_public = false;
    }
}

// ALL public-facing queries MUST use this:
Photo::public()  // →where('is_public', true)
// or:
->where('is_public', true)
```

School pipeline: student uploads → `is_public = false` → NO upload metrics (deferred) → teacher reviews in Facilitator Queue → teacher approves → `is_public = true` + `verified = ADMIN_APPROVED` → `TagsVerifiedByAdmin` fires → `processPhoto()` → `doCreate()` handles full XP (upload + tag) in one pass + increments `users.xp`.

Admin queue never sees school photos (`WHERE is_public = true` excludes them). Teachers are the sole approvers for their team via the Facilitator Queue (3-panel admin-like UI).

## User Photo Visibility

Users can control the public visibility of their photos independently of the school pipeline.

**`users.public_photos` (boolean, default `true`):** A per-user default. New photos inherit this value unless overridden.

**Visibility precedence (highest to lowest):**
1. School team — always `is_public = false` (enforced by PhotoObserver, cannot be overridden)
2. Explicit `is_public` param in upload request
3. User's `public_photos` default
4. Falls back to `true`

**Private-by-choice photos** (non-school, `is_public = false` by user preference):
- Hidden from map, clusters, and all public endpoints (same as any `is_public = false` photo)
- Upload metrics are processed immediately (unlike school photos)
- User appears on leaderboard with full XP credit

**School photos** (`is_public = false` enforced):
- Hidden from map AND upload metrics are deferred until teacher approval
- `recordUploadMetrics()` is skipped (school team check, not `is_public` check)

**Per-photo toggle:** `PATCH /api/v3/photos/{id}/visibility` — owner-only, requires `auth:sanctum`. Blocked for school team photos (403). PhotoObserver marks affected tiles dirty on `is_public` change so the map updates correctly.

## Spatie Roles & Permissions

Laravel Permission 6, `web` guard.

| Role | Access |
|------|--------|
| `superadmin` | Everything + trust management + Horizon |
| `admin` | Photo review, approve, edit tags, delete |
| `helper` | Tag editing only |
| `school_manager` | Manage school team, approve student photos (invite email sent on grant) |

Check with: `$user->hasRole('admin')` or `$user->hasRole('superadmin')`

Admin middleware checks: `hasRole('admin') || hasRole('superadmin')`

Nav.vue `isAdmin` includes `'superadmin'` role (not just `'admin'` and `'helper'`).

## Redis Key Patterns

All keys use hash tags `{...}` for Redis Cluster compatibility:

```
{g}:stats               → HASH (global stats: uploads, tags, litter, xp, ...)
{g}:lb:xp               → ZSET (global XP leaderboard, user_id → xp)
{g}:hll                  → HyperLogLog (contributor count)
{c:ID}:stats             → HASH (country stats)
{c:ID}:lb:xp             → ZSET (country leaderboard)
{s:ID}:stats             → HASH (state stats)
{s:ID}:lb:xp             → ZSET (state leaderboard)
{ci:ID}:stats            → HASH (city stats)
{ci:ID}:lb:xp            → ZSET (city leaderboard)
{u:ID}:stats             → HASH (per-user stats: uploads, xp, litter)
{u:ID}:bitmap            → Bitmap (activity tracking)
```

Key builder: `RedisKeys::global()` → `{g}`, `RedisKeys::country($id)` → `{c:$id}`, etc.

`rewardXpToAdmin()` updates both MySQL `users.xp` AND Redis `{g}:lb:xp` sorted set.

**Redis is a derived cache.** Rebuildable from the `metrics` table. Never treat Redis as source of truth.

## Soft Deletes

Photo model uses `SoftDeletes` trait. `$photo->delete()` sets `deleted_at`, does not remove the row. Eloquent auto-applies global scope excluding soft-deleted records. Raw queries need explicit `whereNull('deleted_at')`.

## Teams

Types: `community` and `school`. Stored in `team_types` table (resolve via `$team->getTypeNameAttribute()`). Do NOT hardcode type IDs — they vary between environments.

School teams enforce extra safeguarding: `MasksStudentIdentity` trait masks student names as "Student N" (deterministic, based on `team_user.id` join order). Student identity never exposed publicly.

School teams must NOT be `is_trusted` — trust bypasses teacher approval entirely.

Key methods: `$team->isSchool()`, `$team->isLeader($userId)`, `$team->hasSafeguarding()`

### Facilitator Queue (Teacher's Admin-like UI)

School team leaders have a 3-panel verification queue (same layout as admin queue):
- Left: `FacilitatorQueueFilters` (status toggle, date range)
- Center: `PhotoViewer` (reused from tagging v2)
- Right: `UnifiedTagSearch` + `ActiveTagsList` (reused from tagging v2)
- Keyboard: A=approve, D=delete, E=save edits, R=revoke, S/K/→=next, J/←=prev

Backend: `TeamPhotosController` returns `new_tags` (CLO format), accepts CLO-based tag edits, provides member stats with safeguarding.

### Teams Frontend — TeamsHub

`/teams` route uses `TeamsHub.vue` (replaces old sidebar layout). Three states: no teams (create/join landing), active team (header + stats + tabs), no active team (team picker). Tabs: Overview, Photos, Map, Members, Settings, Leaderboard, Approval Queue (school), Participants (school+sessions). Privacy defaults: `leaderboards = false` for all new teams, `safeguarding = true` enforced for school.

### SchoolManagerInvite Email

`SchoolManagerInvite` mailable queued when `school_manager` role is granted (artisan command or admin toggle). Two CTAs: Upload → `/upload`, Create Team → `/teams/create`. Not sent on revoke.

## Level System

Config-driven thresholds in `config/levels.php`. 12 levels from "Noob" (0 XP) to "SuperIntelligent LitterMaster" (1M+ XP).

`LevelService::getUserLevel($xp)` returns: level, title, xp_into_level, xp_for_next, xp_remaining, progress_percent.

User model `next_level` accessor calls LevelService. Frontend reads `user.next_level.title`.

## XP Calculation

`AddTagsToPhotoAction::calculateXp()` uses `XpScore` enum multipliers:

| Action | XP | Note |
|--------|----|------|
| Upload | 5 | Base per photo |
| Object | 1 | Per item (special objects: small=10, medium=25, large=50, bagsLitter=10) |
| Brand | 3 | Per brand (uses brand's own quantity) |
| Material | 2 | Per material (uses parent tag's quantity — set membership) |
| Custom Tag | 1 | Per custom tag (uses parent tag's quantity) |
| Picked Up | 5 | Per object (×quantity) where `photo_tags.picked_up=true`. Objects only — no bonus for brand/material/custom-only tags |

## API Field Naming Convention

All list/leaderboard endpoints use: `total_tags`, `total_photos`, `total_members`, `created_at`, `updated_at`. Never use old names: `total_litter`, `total_images`, `tags`, `photos`, `contributors`, `members`.

## Clustering

Grid-based, 9 zoom levels (0,2,4,6,8,10,12,14,16). Two tiers:
- Global (zoom 0-6): single query across all verified photos
- Per-tile (zoom 8-16): pre-computed tile keys, generated columns

Photos need `verified >= ADMIN_APPROVED` and `is_public = true` to appear in clusters.

`PhotoObserver` marks affected tiles dirty when photos are verified/moved. Team dirty tracking was removed — team clustering is on-demand only.

## Testing Patterns

- Base `TestCase` uses `RefreshDatabase` + Redis flush + `TagKeyCache::forgetAll()`
- `auth:sanctum` routes: use `actingAs($user)` with NO guard arg
- `auth:api` routes (legacy mobile): use `actingAs($user, 'api')`
- Photo factory: set `verified`, `is_public`, `summary`, `country_id` explicitly
- `VerificationStatus` assertions: compare enum directly or use `->value` for ordering
- `Event::fake([TagsVerifiedByAdmin::class])` — fakes specific events, others still fire
- Reset Spatie permissions cache: `app()[PermissionRegistrar::class]->forgetCachedPermissions()`
- `TeamType::create(['team' => 'community', 'price' => 0])` — `price` has no default

1010+ tests passing (1 skipped). See `testing-patterns` skill for full details.

## Common Mistakes

1. **Forgetting `is_public = true`** in admin/public queries — exposes school data
2. **Using `->verified == 2`** instead of `->verified->value >= VerificationStatus::ADMIN_APPROVED->value` — misses BBOX+ states and breaks due to enum cast
3. **Deleting before MetricsService** — metrics can't reverse what they can't read
4. **Missing TagsVerifiedByAdmin constructor args** — all 6 required (photo_id, user_id, country_id, state_id, city_id, team_id)
5. **Writing metrics directly** instead of going through MetricsService — causes desync
6. **Forgetting `whereNotNull('summary')`** in queue queries — untagged photos can't be approved
7. **Using old category relationships** (Photo::smoking(), Photo::food()) — v4 deprecated
8. **Not wrapping tag edits in `DB::transaction()`** — partial tag state causes summary corruption
9. **Gating summary generation behind trust check** — summary MUST be unconditional (null summary = zero metrics at approval)
10. **Dispatching `TagsVerifiedByAdmin` for school students** — breaks safeguarding invariant
11. **Using `result_string` or `total_litter`** — deprecated write-only columns. Use `summary` and `total_tags`
12. **Treating Redis as source of truth** — Redis is a derived cache from `metrics` table
13. **Using `doesntHave('photoTags')` for untagged filter** — use `WHERE verified = 0`
14. **Mismatching `actingAs()` guard with route middleware** — `actingAs($user)` for `auth:sanctum`, `actingAs($user, 'api')` for `auth:api`

## Domain Documentation

Read the relevant file before working in that area:

| Document | Covers |
|----------|--------|
| `readme/API.md` | Comprehensive API endpoint reference (source of truth) |
| `readme/Tags.md` | Tag hierarchy, summary JSON, XP calculation |
| `readme/Teams.md` | Teams architecture, permissions, safeguarding, facilitator queue |
| `readme/SchoolPipeline.md` | School approval pipeline (critical data flow) |
| `readme/Metrics.md` | Metrics pipeline and aggregation |
| `readme/Leaderboards.md` | Leaderboard system (Redis ZSETs + MySQL) |
| `readme/Admin.md` | Admin verification system, queue UI, roles |
| `readme/Upload.md` | Photo upload pipeline |
| `readme/Clustering.md` | Map clustering system |
| `readme/Locations.md` | Location and geography system |
| `readme/Mobile.md` | Mobile app & v4-to-v5 tag conversion shim |
| `readme/Profile.md` | User profile, settings, privacy, public profiles |
