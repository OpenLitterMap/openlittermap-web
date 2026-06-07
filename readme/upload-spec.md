# Upload & Tagging Spec — Idempotent Upload + Idempotent Tagging

**Status:** IMPLEMENTED (backend) — Q1–Q6 answered against the code below
**Owners:** Mobile (React Native) ↔ Backend (Laravel)
**Goal:** Eliminate two related production bugs by making both the upload and the
tag-post steps **idempotent**, so a lost-response retry can never corrupt state.

---

## 1. Background — the two bugs

The app uploads in two steps: `POST /api/v3/upload` (binary + GPS) returns a
`photo_id`, then the app posts tags with that id. `imagesArray` (the local inbox)
is persisted, and the upload loop re-runs on every HomeScreen focus, retrying any
photo it still considers unfinished.

**Bug P1 — stranded photo id (the Sentry report).**
`POST /api/v3/tags` → 422 **"The photo id field must be an integer."**
(~494 events / 11 users). When an upload is rejected as a duplicate, the backend
returns a 422 **with no `photo_id`**. The app has no server id to tag with, so it
falls back to a local placeholder id — a string like `"onboarding_…"` or a local
counter — and posts *that*. A string fails the `integer` rule → 422 forever
(persisted record, retried every focus).

**Bug P2 — double-tagging on retry (found during backend review).**
`POST /api/v3/tags` **appends** tags; it does not replace them. For ordinary
(non-trusted) users the authorize gate never fires, so a *repeated* POST silently
**double-tags and double-counts XP**. Our retry-on-focus path uses POST, so any
time a tag-post succeeds server-side but the app loses the response, the next
focus re-POSTs and inflates the photo. Same lost-response race as P1.

---

## 2. Status quo — confirmed against the Laravel codebase

### Upload — `POST /api/v3/upload`
- Controller: `app/Http/Controllers/Uploads/UploadPhotoController.php` (`__invoke`).
- Validation: `app/Http/Requests/UploadPhotoRequest.php`.
- Dedup key = `user_id + datetime`. Application-level only — **no DB unique
  constraint** (`photos.datetime` is a string column, migration `2017_02_24_151913`).
- `recordUploadMetrics` is *not* self-idempotent (always +1 upload / +5 XP) — the
  duplicate branch must never reach it.

### Tags — `POST` vs `PUT /api/v3/tags`
- `PhotoTagsRequest::rules()`: `'photo_id' => ['required','integer', Rule::exists(...)]`
  → a non-integer id yields the exact "must be an integer." message.
- **`POST` (`PhotoTagsController::store`) — APPENDS.** `AddTagsToPhotoAction` only
  ever *creates* `PhotoTag` rows. The only brake is the authorize gate
  (`verified >= VERIFIED(1)` → 403), which does **not** fire for ordinary users
  (they stay `verified = 0` after tagging).
- **`PUT` (`PhotoTagsController::update`) — REPLACES.** Deletes existing tags,
  resets, re-adds. `ReplacePhotoTagsRequest` authorize is ownership-only.

---

## 3. Target design (IMPLEMENTED)

### 3.1 Idempotent upload (backend) — DONE
On a duplicate, **return success with the existing id** instead of a 422. Applies
to both the mobile/explicit and web/EXIF branches.

```json
{ "success": true, "photo_id": 12345, "already_uploaded": true, "tagged": false, "xp_awarded": 0 }
```
- Implementation: the duplicate check moved **out of `UploadPhotoRequest::after()`
  and into `UploadPhotoController::__invoke()`**, right after the datetime/coords
  are resolved and **before** any S3 write / `Photo::create` / XP. It does
  `Photo::where('user_id', …)->where('datetime', …)->orderBy('id')->first()` and,
  if found, returns the idempotent payload. Pure lookup — zero side effects.
  Skipped for participant uploads (students share the facilitator's `user_id`).

### 3.2 Idempotent tagging — app uses **PUT** (replace)
**Decision: the auto-upload flow uses `PUT /api/v3/tags` (replace).** PUT is
idempotent and converges. Client rules:
1. Upload response `tagged: true` → **skip tagging**, drop from local inbox.
2. Otherwise → **PUT** the tags (safe whether the photo had 0 or a partial set).

### 3.3 Backend hardening — DONE (shipped, not optional)
`POST /api/v3/tags` now has an **"already has a summary" guard**: if the target
photo's `summary` is non-null, the controller returns an idempotent success
(`{ success: true, already_tagged: true, photoTags: [...] }`) **without** re-adding
tags. This protects already-installed (old) app versions that still POST.

---

## 4. Exact contracts (target / implemented)

**Upload success — duplicate (NEW):**
```json
{ "success": true, "photo_id": <int>, "already_uploaded": true, "tagged": <bool> }
```

**POST `/api/v3/tags` — already-tagged (NEW idempotent no-op):**
```json
{ "success": true, "already_tagged": true, "photoTags": [ /* existing tags */ ] }
```

**Tag write — `PUT /api/v3/tags`:** unchanged; accepts the same `tags` shapes as
POST, including custom-tag-only `{ "custom": true, "key": "tag-text" }`.

---

## 7. Open questions for the Laravel agent — ANSWERED

### Q1 — Does `PUT`-first-time == `POST`-first-time? **YES.** ✅ Commit to always-PUT.

Code-read (not assumption):
- POST `store()` calls `AddTagsToPhotoAction::run($uid,$pid,$tags)` with the
  default `skipVerification = false` (`PhotoTagsController.php`).
- PUT `update()`, inside `DB::transaction`, deletes tags + resets
  `summary/xp/total_tags/verified = 0`, then (tags non-empty) calls the **same**
  `AddTagsToPhotoAction::run(...)`, also `skipVerification = false`.
- `run()` always: `generateSummary()` (sets `summary`, `xp`, `total_tags`,
  `total_brands` — `GeneratePhotoSummaryService::run` `:202-208`) → `if
  (!$skipVerification) updateVerification()` (`AddTagsToPhotoAction:50`).
- `updateVerification()` (`:446-481`): trusted → `verified = ADMIN_APPROVED(2)` +
  fires `TagsVerifiedByAdmin`; school student → `verified = VERIFIED(1)`, **no**
  event; ordinary non-trusted → `verified = 0` + fires event.

On a **never-tagged** photo, PUT's reset is a no-op (no existing tags, `verified`
already 0, `summary` already null), then it runs the identical `run()`. Therefore
tag XP, `users.xp`, `photos.verified`, `total_tags`, and MetricsService counts are
**identical** for trusted (2), school students (1), and ordinary users (0).
Covered by `ReplacePhotoTagsTest::test_put_first_time_matches_post_for_trusted_user`.

**One divergence fixed as part of this change:** `store()` stamps
`onboarding_completed_at` on first tag; `update()` did not. Since the auto-upload
flow now tags via PUT, the same line was mirrored into `update()` (guarded: only
when `tags` is non-empty and onboarding is not already complete). With that,
PUT-first-time is fully equivalent. Covered by
`test_put_first_time_marks_onboarding_complete` and
`test_put_clearing_tags_does_not_mark_onboarding_complete`.

### Q2 — `tagged` flag definition. **`tagged := $photo->summary !== null`.** ✅

`summary` is set by `GeneratePhotoSummaryService` whenever any tags are added,
independent of verification status — it is the canonical "is tagged" signal (the
untagged filter across the app is `whereNull('summary')`). More reliable than
`total_tags`. The client can trust `tagged: true` → skip tagging.

### Q3 — datetime stability. **Stable; `->first()` reliably hits the original.** ✅

Both the first upload and the retry parse the same `date` value identically:
`is_numeric($date) ? Carbon::createFromTimestamp((int)$date) : Carbon::parse($date)`
— the same code resolves the stored value (`UploadPhotoController:64-67`, stored at
`:117`). `createFromTimestamp` yields an absolute instant rendered to the
`datetime` column (cast `datetime`, `'Y-m-d H:i:s'`) under a fixed
`config('app.timezone') = 'UTC'` (`config/app.php:70`). Same input string → same
Carbon → same stored string both times → the `user_id + datetime` lookup matches.
Stable as long as the app timezone is constant (it is — config-fixed). The
existing duplicate tests already prove a Carbon `where('datetime', …)` matches the
stored row.

### Q4 — legacy duplicates. **`orderBy('id')->first()` → the original row.** ✅

Pre-dedup data may have multiple rows for one `user_id + datetime`. The lookup uses
`orderBy('id')` for determinism and returns the earliest (original). The app then
PUTs (replace) onto it — no data loss. Rare legacy case; acceptable.

### Q5 — custom tags via PUT. **Identical to POST.** ✅

PUT → same `AddTagsToPhotoAction::run` → `addTagsToPhoto` → `isExtraTagOnly()` /
`createExtraTagOnly()` (custom-only) or `createTagLegacy` `{custom:true, key}`
branch. `ReplacePhotoTagsRequest` allows `tags.*.custom` and `tags.*.key`. Same
code path as POST — no divergence.

### Q6 — XP on duplicate. **Duplicate awards 0 XP; PUT then applies tag XP once.** ✅

The idempotent duplicate branch is a pure lookup — no `recordUploadMetrics`, no
`users.xp` increment, no S3 — so 0 upload XP on the duplicate response (the
original upload already awarded +5 once). Subsequent PUT tagging applies tag XP
exactly once via `processPhoto → doUpdate`, computing the delta against the
photo's `processed_xp` (= 5 from upload). Covered by
`UploadPhotoTest::test_mobile_upload_duplicate_by_explicit_date_returns_existing_id`
(asserts XP unchanged + no second row).

---

## 8. Test plan — backend status

Backend (all passing):
- `UploadPhotoTest::test_web_upload_duplicate_returns_existing_id_idempotently` —
  duplicate returns existing id + `already_uploaded`, no second Photo row.
- `UploadPhotoTest::test_mobile_upload_duplicate_by_explicit_date_returns_existing_id`
  — pure lookup, XP unchanged, no second row (Q6).
- `UploadPhotoTest::test_duplicate_upload_reports_tagged_true_when_photo_has_summary`
  — `tagged` flag correct (Q2).
- `UploadPhotoTest::test_duplicate_upload_response_shape` — response keys.
- `AddNewTagsToPhotosTest::test_post_tags_on_already_tagged_photo_is_idempotent` —
  repeated POST is a no-op, no double-tag / no XP inflation (P2 closed).
- `ReplacePhotoTagsTest::test_put_first_time_matches_post_for_trusted_user`,
  `test_put_first_time_marks_onboarding_complete`,
  `test_put_clearing_tags_does_not_mark_onboarding_complete` (Q1).

App-side tests: owned by the mobile agent (see §5 of the original brief).

---

## 9. Decisions captured
- ✅ Idempotent upload: duplicate → `{ success, photo_id, already_uploaded, tagged }`.
- ✅ Tagging via **PUT** (replace) in the auto-upload flow — Q1 confirms
  PUT-first-time == POST-first-time (with the onboarding line mirrored into PUT).
- ✅ `POST /tags` already-tagged guard — **shipped** (not deferred): idempotent
  no-op when `summary` is present, protecting old app versions from P2.
- ✅ `tagged := summary !== null` (Q2).
- ✅ Keep the `isServerPhotoId` guard permanently (app-side).
