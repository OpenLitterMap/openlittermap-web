# OpenLitterMap v5 — User Lifecycle

## Overview

This document describes how a regular user's data flows through OLM from account creation to deletion. It covers two user types: **trusted** (`verification_required = false`) and **untrusted** (`verification_required = true`, the default for new signups).

**Core principle:** Give users XP and feedback as quickly as possible. All users — trusted and untrusted — get immediate XP, leaderboard entries, location metrics, and contributor recognition at tag time. The only thing verification gates is **map popup visibility**: untrusted users' photos and tags do not appear in map popups until an admin approves them.

School team students follow a separate pipeline — see **SchoolPipeline.md**.

---

## The Lifecycle

```
1. Create Account     → user.xp = 0, no photos, no metrics
2. Upload Photo       → Photo created, user.xp += 5, metrics rows written, Redis updated
                         (recordUploadMetrics sets processed_at + processed_xp = 5)
3. Add Tags           → TagsVerifiedByAdmin fires for ALL non-school users
                         ├── MetricsService::processPhoto → doUpdate() delta from upload baseline
                         ├── Trusted: verified = ADMIN_APPROVED (2) → photo visible on map
                         └── Untrusted: verified stays UNVERIFIED (0) → photo hidden from map
4. (Untrusted only)   → Admin reviews → may edit tags (delta) → promotes to ADMIN_APPROVED
                         → photo now visible on map popups
5. Update Tags        → MetricsService re-processes (fingerprint delta)
6. Delete Photo       → MetricsService::deletePhoto() reverses ALL metrics (upload + tags)
```

---

## What Verification Controls

| Behaviour | Requires verification? |
|-----------|----------------------|
| User XP | No — immediate at tag time |
| Leaderboard appearance | No — immediate |
| Location metrics (litter counts, contributor stats) | No — immediate |
| Redis stats hashes, ZSETs, HLLs | No — immediate |
| `metrics` table rows | No — immediate |
| Profile stats | No — immediate |
| **Photo + tags visible in map popups** | **Yes — requires `verified >= ADMIN_APPROVED`** |

Verification is a **spam filter** for new untrusted users, not a metrics gate. The map is the public-facing representation of the data, and unverified data should not appear there. But the user's personal stats, leaderboard position, and aggregate location totals all update in real time.

**During verification, an admin may edit an untrusted user's tags.** This changes the photo's summary and XP. MetricsService handles this via its fingerprint delta mechanism — the difference between old and new tags is computed and applied to all metrics. The user's XP, leaderboard score, and location metrics adjust accordingly.

---

## Step 1: Create Account

`POST /register` → Laravel `Registered` event → email verification.

### Initial state

| Field | Value |
|-------|-------|
| `user.xp` | 0 |
| `user.verification_required` | true (untrusted by default) |
| Redis `user:{id}:stats` | empty hash |
| Redis `{g}:lb:xp` | no entry for user |
| `metrics` table | no rows for user |

A fresh user has zero presence in the system. No Redis keys, no metrics rows, no leaderboard entries.

---

## Step 2: Upload Photo

User uploads a photo with GPS coordinates. The upload controller creates a photo record with location FKs but no tags.

```
UploadPhotoController::__invoke()
├── MakeImageAction::run($file)              → image + EXIF
├── UploadPhotoAction::run() × 2             → S3 full + bbox
├── getCoordinatesFromPhoto($exif)           → lat, lon
├── ResolveLocationAction::run($lat, $lon)   → country_id, state_id, city_id
├── Photo::create()                          → FKs set, summary = null, xp = 0
├── user.increment('xp', 5)                  → MySQL users.xp += 5
├── recordUploadMetrics($photo, 5)           → metrics table, Redis stats/leaderboards,
│                                               processed_at, processed_xp=5
├── event(ImageUploaded)                     → broadcast to real-time map
└── event(NewCountry/State/CityAdded)        → notifications (if new location)
```

### State after upload

| Field | Value |
|-------|-------|
| `photo.verified` | `UNVERIFIED (0)` |
| `photo.summary` | null |
| `photo.xp` | 0 (no tags yet) |
| `photo.processed_at` | set (by `recordUploadMetrics`) |
| `photo.processed_xp` | 5 (upload base) |
| `photo.processed_fp` | `''` (empty — no tags) |
| `photo.processed_tags` | `'[]'` (empty JSON array) |
| `user.xp` | 5 |
| `metrics` table | rows at all scopes (xp=5, litter=0, uploads=1) |
| Redis user stats | xp=5, uploads=1, litter=0 |
| Redis leaderboard | user appears with score 5 |

### Upload XP — immediate feedback

The user receives 5 XP immediately on upload, before any tags are added. This provides instant positive feedback for the act of capturing and uploading an observation.

The upload XP is awarded at upload time by incrementing:

1. `users.xp` in MySQL (+5)
2. `user:{id}:stats` → `xp` in Redis (+5)
3. `{g}:lb:xp` ZSET in Redis (ZINCRBY user_id 5)
4. Location-scoped leaderboard ZSETs (`{c:ID}:lb:xp`, `{s:ID}:lb:xp`, `{ci:ID}:lb:xp`)

**Implementation note:** `photo.xp` contains tag XP only (not upload base). The upload base is added by `MetricsService::extractMetricsFromPhoto()` when computing effective XP for metrics. `processed_xp` = upload base + tag XP.

### What upload does NOT do

- No summary generated (no tags yet)
- Photo has no tag XP (photo.xp = 0)
- Photo is not visible on map (no tags, verified = 0)

### What upload DOES do (via `recordUploadMetrics`)

`MetricsService::recordUploadMetrics()` runs the full metrics pipeline at upload time:

1. Writes `metrics` table rows at all scopes (xp=5, litter=0, uploads=1)
2. Sets `processed_at`, `processed_xp=5`, `processed_fp=''`, `processed_tags='[]'`
3. Updates Redis: user stats, leaderboard ZSETs, scope stats, HLLs, contributor rankings

This ensures the user appears on time-filtered leaderboards immediately after upload (before tagging). When tags are added later, `MetricsService::processPhoto()` routes to `doUpdate()` (not `doCreate()`) because `processed_at` already exists, computing a delta from the upload-only baseline.

---

## Step 3: Add Tags

User tags the photo via the web tagging UI or mobile app. **`TagsVerifiedByAdmin` fires for ALL non-school users** — both trusted and untrusted. This is the key architectural decision: metrics are immediate.

```
POST /api/v3/tags

AddTagsToPhotoAction::run($photo, $tags)
├── Create PhotoTag records (category_id, litter_object_id, quantity)
├── GeneratePhotoSummaryService
│   ├── Build summary JSON from PhotoTags
│   ├── Calculate tag XP (objects + materials + brands + picked_up bonus)
│   └── Write photo.summary and photo.xp
└── updateVerification()
    ├── Trusted user (verification_required = false)
    │   ├── Set verified = ADMIN_APPROVED (2), verification = 1
    │   └── Fire TagsVerifiedByAdmin → MetricsService
    └── Untrusted non-school user (verification_required = true)
        ├── Set verification = 0.1 (verified stays at 0 = UNVERIFIED)
        └── Fire TagsVerifiedByAdmin → MetricsService (SAME pipeline)

MetricsService::processPhoto()
├── processed_at already set (from recordUploadMetrics at upload time)
├── Routes to doUpdate() (NOT doCreate) — delta-based
├── Old: xp=5, litter=0, tags=[]
├── New: xp=5+tagXP, litter=N, tags={...}
└── Delta: xp=+tagXP, litter=+N, tag hashes added
```

### State after tagging — BOTH user types

| Field | Trusted | Untrusted |
|-------|---------|-----------|
| `photo.verified` | `ADMIN_APPROVED (2)` | `UNVERIFIED (0)` — stays at default |
| `photo.summary` | populated | populated |
| `photo.xp` | tag XP | tag XP |
| `photo.processed_at` | updated by MetricsService | updated by MetricsService |
| `photo.processed_xp` | 5 + tag XP | 5 + tag XP |
| `user.xp` | 5 (upload) + tag XP | 5 (upload) + tag XP |
| Redis leaderboard | 5 + tag XP | 5 + tag XP |
| Redis stats, HLLs | updated | updated |
| `metrics` table | rows at all scopes | rows at all scopes |
| **Photo on map popups** | **Yes** | **No — hidden until admin approves** |

The ONLY difference between trusted and untrusted at this point is `photo.verified` and therefore map popup visibility. Everything else is identical.

---

## Step 4: Admin Verification (untrusted users only)

The admin reviews the photo in the admin queue. They can do one of three things:

### 4a. Approve as-is

```
POST /api/admin/verify { photoId: X }

AdminController::verify()
├── Atomic update: WHERE is_public = true AND verified < ADMIN_APPROVED
├── Set verified = ADMIN_APPROVED (2)
├── rewardXpToAdmin()
└── logAdminAction()
```

**Note:** `TagsVerifiedByAdmin` fires again on approve (if `$affected > 0`), but MetricsService::processPhoto() detects that the fingerprint and XP haven't changed (idempotent check) and returns early. No double-counting occurs. The approve action's net effect is promoting `verified` from `UNVERIFIED (0)` to `ADMIN_APPROVED (2)`, making the photo visible in map popups.

### 4b. Edit tags, then approve

```
PATCH /api/admin/photos/{photo}/tags { tags: [...] }

AdminController::updateDelete()
├── DB::transaction: delete old PhotoTags + AddTagsToPhotoAction::run()
├── GeneratePhotoSummaryService → new summary + new tag XP
├── Set verified = ADMIN_APPROVED (2)
├── Fire TagsVerifiedByAdmin → MetricsService::processPhoto()
│   └── Fingerprint changed → doUpdate() → delta applied to all metrics
├── rewardXpToAdmin()
└── logAdminAction()
```

The user's XP, leaderboard score, and location metrics adjust by the delta between old and new tags. This is the existing MetricsService re-processing flow — no new code needed.

### 4c. Delete

Admin decides the photo is spam. See Step 6.

### State after admin approval (approve as-is)

Same as "State after tagging" except `photo.verified = ADMIN_APPROVED`. The photo now appears in map popups. No metrics change.

### State after admin approval (tag edit)

XP, leaderboard, and metrics adjusted by delta. Photo now visible on map with corrected tags.

---

## Step 5: Update Tags

The user (or admin) updates the tags on an already-processed photo.

```
AddTagsToPhotoAction::run($photo, $newTags)
├── DB::transaction: delete old PhotoTags + create new PhotoTags
├── GeneratePhotoSummaryService → new summary + new tag XP
└── updateVerification() → fires TagsVerifiedByAdmin

MetricsService::processPhoto()
├── Compute fingerprint from new summary
├── Compare to stored processed_fp
│   ├── Same fingerprint + same XP → skip (no-op)
│   └── Different → doUpdate()
│       ├── Calculate delta (new metrics − stored processed_tags)
│       ├── Upsert delta to MySQL metrics (positive or negative)
│       ├── Apply delta to Redis (HINCRBY, ZINCRBY — can be negative)
│       └── Update photo: processed_at, processed_fp, processed_tags, processed_xp
└── User XP adjusted by delta
```

### XP delta examples

Starting state: 3 cigarette butts → tag XP = 3

**Add 2 wrappers:** tag XP = 3 + 2 = 5. Delta = +2. User total = 5 (upload) + 5 = 10.

**Remove 1 butt:** tag XP = 2 + 2 = 4. Delta = −1. User total = 5 (upload) + 4 = 9.

Redis leaderboard scores and MySQL metrics rows are adjusted by the delta, not reset.

---

## Step 6: Delete Photo

The user (or admin) deletes the photo.

```
MetricsService::deletePhoto($photo)   ← MUST run BEFORE soft delete
├── Lock row (SELECT FOR UPDATE)
├── Guard: if processed_at is null → return (no-op)
├── Read stored processed_tags + processed_xp
│   └── processed_xp includes upload base + tag XP (e.g. 8 for 5+3)
├── Calculate negative metrics from stored values
├── Upsert negative deltas to MySQL (GREATEST prevents below 0)
├── Reverse users.xp: GREATEST(CAST(xp AS SIGNED) - processed_xp, 0)
│   └── This reverses BOTH upload XP and tag XP in one operation
├── Decrement Redis: stats, tags, rankings, user stats
├── ZINCRBY leaderboard ZSETs by -xp, then ZREMRANGEBYSCORE prunes score ≤ 0
├── Clear photo: processed_at, processed_fp, processed_tags, processed_xp
└── Done — ALL metrics reversed (upload + tags)

$photo->delete()  ← soft delete (sets deleted_at)
```

**Key:** Upload XP reversal is NOT separate from MetricsService. `processed_xp` always includes the upload base (set to 5 at upload, updated to 5+tagXP at tag time). `deletePhoto()` reverses the full `processed_xp` amount in a single operation.

### State after delete

| Field | Value |
|-------|-------|
| `photo.deleted_at` | set (soft-deleted) |
| `photo.processed_at` | null (cleared by deletePhoto) |
| `user.xp` | previous − (5 upload + tag XP) |
| Redis leaderboard | score decreased (pruned if 0) |
| `metrics` table | rows decremented (GREATEST prevents negative) |

**Hard sequencing rule:** `MetricsService::deletePhoto()` runs BEFORE `$photo->delete()`. The metrics reversal reads `processed_tags` to calculate negative deltas. If the photo is soft-deleted first, the data is excluded from queries and metrics can't be reversed.

---

## XP Accounting Summary

| Event | User XP change | Source | Both user types? |
|-------|---------------|--------|------------------|
| Upload photo | +5 | Upload controller (`user.increment`) + `recordUploadMetrics` | Yes |
| Add tags | +tag XP | MetricsService via TagsVerifiedByAdmin (`doUpdate` delta) | Yes |
| Admin edits tags | ±delta | MetricsService (fingerprint delta) | Untrusted only |
| User updates tags | ±delta | MetricsService (fingerprint delta) | Yes |
| Delete photo | −(5 + tag XP) | MetricsService::deletePhoto (single operation via `processed_xp`) | Yes |

**Total user XP at any point** = 5 × active photo count + sum of tag XP across all processed photos.

---

## Map Popup Visibility

The map popup query MUST filter by `verified >= ADMIN_APPROVED`. This is the single gate that separates trusted (immediate visibility) from untrusted (deferred visibility).

```php
// Map popup query — only show verified photos
Photo::where('is_public', true)
    ->where('verified', '>=', VerificationStatus::ADMIN_APPROVED)
    ->whereNotNull('summary')
```

**Clustering** also uses `verified >= ADMIN_APPROVED` to determine which photos generate map clusters. Unverified photos contribute to metrics but not to the visual map layer.

---

## Trusted vs Untrusted — Summary of Differences

| Behaviour | Trusted | Untrusted |
|-----------|---------|-----------|
| Upload XP | Immediate | Immediate |
| Tag XP | Immediate | Immediate |
| MetricsService | Immediate | Immediate |
| Leaderboards | Immediate | Immediate |
| Location metrics | Immediate | Immediate |
| Profile stats | Immediate | Immediate |
| Photo on map | Immediate | After admin approval |
| `photo.verified` after tagging | `ADMIN_APPROVED (2)` | `UNVERIFIED (0)` — never changed by updateVerification |
| Admin review required | No | Yes |
| Admin may edit tags | N/A | Yes (triggers delta) |

---

## Trust Transition

When a `superadmin` sets `verification_required = false` on a user, future tags auto-set `verified = ADMIN_APPROVED` (map-visible immediately). Existing photos at `UNVERIFIED (0)` still need explicit promotion — either admin approve-all or individual approval.

---

## Implementation Status (all complete)

| Change | File | Status |
|--------|------|--------|
| Award upload XP | `UploadPhotoController` | Done — `user.increment('xp', 5)` + `recordUploadMetrics()` |
| Tag XP = tag only | `GeneratePhotoSummaryService` | Done — `photo.xp` excludes upload base |
| Upload base in metrics | `MetricsService::extractMetricsFromPhoto()` | Done — adds `XpScore::Upload->xp()` to `photo.xp` |
| Fire TagsVerifiedByAdmin for untrusted | `AddTagsToPhotoAction::updateVerification()` | Done — fires for all non-school users |
| Admin approve idempotent | `AdminController::verify()` | Done — fires event but MetricsService detects no change |
| Delete reverses all XP | `MetricsService::deletePhoto()` | Done — `processed_xp` includes upload base |
| Map popup query | Clustering, PointsController | Done — `verified >= ADMIN_APPROVED` |

---

## Related Docs

| Document | Covers |
|----------|--------|
| **Upload.md** | Upload controller flow, TagsVerifiedByAdmin pipeline |
| **Tags.md** | XP calculation, summary JSON, tag hierarchy |
| **Metrics.md** | MetricsService internals, fingerprinting, delta processing |
| **Leaderboards.md** | Redis ZSETs, time-filtered MySQL queries, rewardXpToAdmin |
| **Profile.md** | Profile API, user stats sources, privacy controls |
| **Admin.md** | Admin approval flow, verification state machine |
| **SchoolPipeline.md** | School student flow (separate from this doc) |
| **FeatureLifecycleTest.md** | End-to-end test suite exercising this lifecycle |
