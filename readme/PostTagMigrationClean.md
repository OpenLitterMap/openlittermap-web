# Post-Migration Tag Cleanup — Retirement Process

**Created:** 2026-08-08
**Status:** Design, for review. Nothing implemented, no data changed.
**Supersedes for this workstream:** the queue/backlog/manifest split. This document is now the
sole record of it — `readme/audit/TagCleanupSummary-2026-08.md`,
`readme/audit/LitterObjectBacklog-2026-08.csv` and `readme/audit/TagMigrationQueue-2026-08.csv`
were deleted on 2026-08-09 rather than left sitting beside their replacement.
`readme/audit/LitterObjectInventory-2026-08.csv` and
`readme/audit/TagPairMigrationManifest-2026-08.csv` remain — they are measured evidence, not
superseded process.

> **Scope.** This document defines *what a problematic tag is*, *the list of them*, and *the
> process for cleaning them one at a time*. It does not authorise any specific retirement —
> each row is approved individually by the product owner.
>
> Related, still authoritative for their own subjects: `readme/PostMigration-2026-08.md`
> (the export-undercount incident), `readme/Tags.md` (taxonomy), `readme/ExportData.md`
> (CSV export), `readme/Metrics.md` (metrics pipeline).

---

## 1. What a problematic tag is

**A tag we previously used and no longer want.**

Worked example, the whole chain for one tag:

```
v4 column      app/Models/Litter/Categories/Other.php:25        'plastic_bags'
migration map  app/Services/Tags/ClassifyTagsService.php:228    'plastic_bags' => ['object' => 'plasticBags']
v5 object      litter_objects #149  plasticBags   crowdsourced=1, not in TagsConfig
competing key  litter_objects #92   plastic_bag   crowdsourced=0, TagsConfig:332
```

Both keys are live in `litter_objects` and both hold tags. Exactly one should survive.

This definition is narrower than the 74-pair manifest, and deliberately so. Applying it splits
that manifest into two populations that need different work:

| Population | Pairs | Objects | Items | "No longer wanted"? |
|---|---:|---:|---:|---|
| **Retirements** — a key we want gone | 65 | **62** | 266,357 | Yes |
| Missing pivots — canonical object, category has no pivot | 9 | 9 | 10,812 | **No** |

The second group is `industrial/plastic`, `marine/bag`, `marine/bottle`, `marine/lighters`,
`other/dogshit`, `other/dogshit_in_bag`, `other/tyre`, `sanitary/gloves`, `sanitary/sanitiser`.
Nothing there is unwanted — `bag` is a tag we keep; it is simply tagged in a category that has
no `category_litter_object` row for it. Seven of the nine are category moves.

**These nine are not retirements and are excluded from this process.** They get their own short
backlog and a different fix (add the pivot, or move the category). Forcing them into a
retirement schema would leave most of its columns unfillable.

---

## 2. The list

One file — `readme/audit/TagRetirements-2026-08.csv`, **not yet created** — replacing the
backlog, the queue and the manifest, so "approved" is recorded in exactly one place. Generating
it from the existing inventory and manifest is the first implementation step.

**One row per retirement. 62 rows.**

```
retired_key   retired_id  desired_key  desired_id  category  class
plastic_bag   92          plasticBags  149         other     twin
straw         25          straws       150         food      twin
beerCan       137         can          5           alcohol   remap+type
randomLitter  170         other        1           other     remap+generic
```

Per row, additionally: measured `rows`/`items`/`photos` on **both** sides; the surface
reference counts already gathered in `LitterObjectInventory-2026-08.csv` (`ref_tagsconfig`,
`ref_brandsconfig`, `ref_translations`, `ref_other_app_code`); `status`, `approved_by`,
`approved_at`, `verification_evidence`.

### The `class` column

The 62 are not homogeneous. Two classes, very different costs:

**`twin` — 16 rows.** Two keys, one concept, differing only in spelling. The owner picks the
survivor; the work is a pure repoint. Measured, every one has the same shape — a
camelCase-or-plural key holding nearly all the data, and a snake_case-singular twin holding
almost none:

| retired candidate | rows | desired candidate | rows |
|---|---:|---|---:|
| `plastic_bag` | 253 | `plasticBags` | 10,051 |
| `straw` | 247 | `straws` | 7,543 |
| `broken_glass` | 79 | `brokenglass` | 3,412 |
| `shotgun_cartridge` | 79 | `shotgun_cartridges` | 493 |
| `bags_litter` | 49 | `bagsLitter` | 1,041 |
| `balloon` | 24 | `balloons` | 2,180 |
| `cable_tie` | 14 | `cableTie` | 1,021 |
| `face_mask` | 13 | `facemask` | 7,742 |
| `ear_swabs` | 10 | `earSwabs` | 172 |
| `poster` | 6 | `posters` | 208 |
| `overflowing_bin` | 6 | `overflowingBins` | 517 |
| `pull_ring` | 5 | `pullRing` | 758 |
| `fishing_net` | 4 | `fishing_nets` | 894 |
| `condom` | 1 | `condoms` | 190 |
| `traffic_cone` | 1 | `trafficCone` | 116 |
| `buoy` | 0 | `buoys` | 145 |
| **total** | **791** | | **36,483** |

Retiring the low-volume side moves **791 rows instead of 36,483** — 46× less data.

**`remap` — 46 rows.** No twin exists. `beerCan` becomes `can` + type `beer`; `randomLitter`
becomes `other`. These are genuine taxonomy decisions, and every one of the **19 type
expansions** and **10 category moves** lives here. They need individual thought; the twins can
be reviewed in one sitting.

---

## 3. Decisions taken

Recorded 2026-08-08 by the product owner during design.

**D-1 — Direction is a per-row decision, not derivable.** The survivor is *not* inferable from
"canonical = present in `TagsConfig`". `TagsConfig` is not consistently snake_case; it already
carries `rollingPapers`, `vapePen`, `brokenGlass`, `pullRing` and `earSwabs` alongside
`plastic_bag` and `bags_litter`. There is no house convention to appeal to. The list therefore
carries an explicit owner-set `desired_key`.

**D-2 — Default is "keep the key that holds the data", confirmed case by case.** The list
derives the default; the tool never applies it unapproved. `status` starts at `IDENTIFIED` and
the command refuses to write data below `MAPPING_APPROVED`.

**D-3 — No new keys, ever.** One surviving key per concept, drawn from keys that already exist.
This also settles ten rows the earlier audit left open (`hair_tie`, `toothpick`, `elec_small`,
`magazine`, `washingUp`, `elec_large`, `books`, `ear_plugs`, `item`, `randomLitter`) where the
proposed resolution was "add a new object" — they merge into something existing or they stay.

**D-4 — First entry: retire `plastic_bag` (92), keep `plasticBags` (149).** This inverts the
direction the existing `TagMigrationQueue` row assumed. Consequences in §8.

**D-5 — Rehearsal runs against `olm_postmig_2` directly**, restorable from the owner's `.sql`
source-of-truth dump. (`PostMigration-2026-08.md` previously called for an untouched baseline
plus a clone; the dump makes the database itself disposable.)

**D-6 (2026-08-09) — XP equivalence is a hard refusal, with no override.** `supportedScope()`
refuses any entry whose two keys carry different `XpScore` weights. The earlier design had an
`xp_equivalent=accepted` escape hatch in the list; it was removed because entry 1 is 1→1 and the
mechanism was dead code that deferred a real decision to memory. It returns when a
non-equivalent entry is actually scheduled — `bags_litter` (10) → `bagsLitter` (1) is the first
one, and it needs its own product decision about whether XP moves with the tag or the surviving
key inherits the weighting. The `xp_equivalent` column stays in the list as the record.

---

## 4. The process

One command, one entry, one run. The existing nine-stage lifecycle in `olm:migrate-tag` is
kept; `--verify` becomes a real gate rather than a printed checklist.

### Step 0 — close the door first

**This must happen before any data moves.** The snapshot is immutable by design, so a tag
created mid-run is not in it, survives the retirement, and fails `--verify` with no remediation
path short of starting over.

`GetTagsController::getAllTags()` selects `LitterObject::whereHas('categories')`, so an object
is offered in the picker whenever it has a pivot — and the retired key keeps its pivot until
B.2 deletes it, at the very end. Live traffic could otherwise keep writing to the retired object
for the whole run.

So: set `retired_at` (B.1) **first**, and filter it out of `getAllTags()`. The resurrection
guard in B.7 does not cover this — it only guards `firstOrCreate` paths, not the picker.

This ordering is the main reason the `retired_at` column is worth a migration: the door cannot
be closed by deleting the pivot instead, because `photo_tags` still references that CLO at this
point.

### A — move the data

Everything operates on an immutable snapshot taken at first run, so a row inserted or edited
after approval is never swept in.

1. Snapshot the rows on the retired side, their photo ids, and baseline items/XP.
   **Includes tags on soft-deleted photos** — `photo_tags` rows survive a soft delete, and the
   "zero rows on the retired id" assertion in §6 spans them too. Query `photo_tags` directly,
   with no join to `photos`.
2. **Create the desired pivot if absent.** `plasticBags` has no `category_litter_object` row;
   it needs one **to be selectable in the tag picker**.

> **Corrected 2026-08-08 by measurement.** An earlier revision said the pivot was also needed
> "to earn an export column". That is wrong: the v5.13.1 fix derives export columns from
> `photo_tags` via a `LitterObject` key lookup, with no pivot dependency. The `before` capture
> confirms `plasticBags` **already has an export column** in both scopes measured (team 211 and
> user 4051). The pivot governs picker visibility only.
3. Repoint `photo_tags` — `litter_object_id` → desired, `category_litter_object_id` → desired
   CLO. Idempotent per id, so a crashed run resumes rather than failing permanently.
4. **Repoint `user_quick_tags.clo_id`** from the retired CLO to the desired one.
5. Regenerate `photos.summary` for the snapshot photos, then re-run
   `MetricsService::processPhoto` — **only where `processed_at IS NOT NULL` AND
   `deleted_at IS NULL`**.

> **⚠ The soft-delete guard is not optional.** `MigrateTag:468` currently gates on
> `processed_at !== null` alone. `MetricsService::deletePhoto()` already reversed the metrics
> for a soft-deleted photo, so calling `processPhoto()` on it re-adds them — inflating XP and
> object counts, and breaking the absolute Redis reconciliation in §6. Repoint the rows, skip
> the metrics.
>
> Measured 2026-08-08: both objects in entry 1 have **zero** tags on soft-deleted photos, so
> this does not bite the first retirement. It is latent, not theoretical — later entries will
> hit it.

Quantities, categories, types, extra tags and physical rows are preserved throughout. Rows are
repointed and kept physically separate; logical duplicates are **not** merged (280 collision
groups carry differing material/brand/custom-tag sets — merging destroys those dimensions).
Row-level deduplication is a separate later operation.

### B — deprecate the old key

Seven concrete actions, gated by lifecycle status. **B.1 runs before A** (see Step 0); the rest
run after.

> **Ordering caveat (2026-08-10).** The lifecycle inverts this — `CODE_UPDATED` precedes the
> apply, so B.4–B.7 are committed before the data moves — which is safe only where the config
> edit is inert until the retirement runs (verified for entry 1: `TagsConfig` object keys feed
> only the suggested types and materials in `getAllTags()`, while the picker itself reads the
> database), and must be re-checked for any entry where it is not.

1. Set `litter_objects.retired_at` and `merged_into_id` on the retired row (see §5), and filter
   retired objects out of `GetTagsController::getAllTags()`. **This is Step 0 — it precedes A.**
2. Delete the retired CLO row — **cleanup, not load-bearing.** After A.3 and A.4, both of which
   still reference it. The picker already excludes the object via `retired_at` (Step 0), and the
   retired export column disappears because A.3 drained its `photo_tags` rows — not because the
   pivot went. This step removes a now-dangling row; nothing user-visible depends on it.
3. Clear `crowdsourced` on the survivor where it was a migration-minted key.
4. `TagsConfig` — survivor in, retired key out.
5. `BrandsConfig` — repoint references to the survivor.
6. `resources/js/langs/*/litter.json` — all locales, not just `en`.
7. Resurrection guard — `firstOrCreate` paths refuse a retired key.

> **Known limitation (2026-08-09).** The tag-write guard sits in
> `AddTagsToPhotoAction::createTagFromClo()` only. The legacy path, `createTagLegacy()` via
> `resolveTag()`, resolves the object by id or key with no retirement check, so a legacy-format
> POST can still write onto a retired object during the drain window. Accepted for entry 1 —
> the window is minutes and the picker is already closed — and recorded here rather than fixed,
> because the durable fix is a `PhotoTag::saving` guard covering both paths and every future
> one, which is its own change.

`ClassifyTagsService` is **never edited.** It is the historical record of what the v5 migration
did. Under D-4's direction, line 228 (`'plastic_bags' => ['object' => 'plasticBags']`) is
already correct, so no override map is needed for this entry at all.

### C — prove the new key is used everywhere

Asserted mechanically by `--verify`. See §6 for the full surface table.

### D — record completion

`--verify` must pass before `--advance=COMPLETE` is permitted, and the evidence string is
populated from the verify output rather than typed by hand.

`--advance` writes `approver`/`approved_at` **once** — they record the mapping approval and are
never overwritten. Every rung after that is attributed in `verification_evidence` as
`STATUS by <who> at <when>`, so the full transition history survives to `COMPLETE`.

### E — the production run

A checklist for the console, in order. Entry 1 is rehearsed and `CODE_UPDATED`; everything below
is the remaining path.

1. **Confirm the database before anything runs.** The dry run measures whatever `.env` points
   at, and a rehearsal leaves it on a post-apply database where the retired object reads zero
   rows and `expectationsMatch` refuses. Confirm the target is production first.
2. **Confirm production is running this branch.** The `--verify` gate reconciles MySQL against
   Redis at every scope the objects touch, and that reconciliation was wrong before `99502335`
   (`pluck()` discarding `selectRaw` aliases, reporting false divergence). A verify run on older
   code cannot be trusted. Separately, per §8a, **do not rebuild production Redis** — the litter
   gap there is unresolved and is not part of this run.
3. **Run the entry in one sitting**, in this order: dry run → `--advance=DRY_RUN_VERIFIED`
   → `--apply` → `--verify` → advance the remaining rungs. `--advance=COMPLETE` re-runs verify
   itself and refuses on failure.
4. **Expect the counts to have drifted.** `retired_rows`/`retired_items`/`retired_photos` were
   measured 2026-08-08 and the picker has been open since, so the dry run may abort with
   "data moved since approval". That is the guard working. Re-measure from the dry run's
   "Measured now" line, update the list, and re-approve before continuing — do not advance past
   a refusal.
5. **B.3 is accepted-cosmetic.** The survivor still reads `crowdsourced=1` after a complete,
   verified retirement. Nothing reads `litter_objects.crowdsourced`. It is not a defect to chase
   mid-run.

---

## 5. Schema change

One migration, adding to `litter_objects`:

| Column | Purpose |
|---|---|
| `retired_at` | nullable timestamp — the key is deprecated |
| `merged_into_id` | nullable FK to `litter_objects` — what replaced it |

**Why this is worth a schema change.** Today "deprecated" is an accident of having no pivot
row: `GetTagsController::getAllTags()` selects objects `whereHas('categories')`, so a pivotless
object is invisible to the picker as a side effect. Step A.2 deliberately *creates* pivots, so
that accident stops holding. An explicit fact is something `--verify` can assert, and it lets
`firstOrCreate` paths refuse to resurrect a retired key.

That last point is load-bearing: `AutoCreateBrandRelationships` calls `normalizeDeprecatedTag()`
and then `firstOrCreate`s both the object **and** a pivot. It is a live resurrection path, and
the audit found **21 retired objects referenced by `BrandsConfig`**.

> ### ⚠ `AutoCreateBrandRelationships` is do-not-re-run
>
> Until the resurrection guard (B.7) is in place, re-running this command after any retirement
> recreates the retired object *and its pivot*, silently undoing the work — the object becomes
> selectable again and starts collecting new tags. Treat it as do-not-re-run unless a dated
> post-migration command supersedes its mapping.
>
> B.5 (repointing `BrandsConfig` to the survivor) must land in the **same commit** as the
> retirement, or the next run of this command reintroduces the retired key from config.

---

## 6. Verification surfaces

The surfaces a retirement touches. This is the design intent; what `--verify` asserts **today**
is the five-check subset recorded directly below the table.

| Surface | Assertion |
|---|---|
| `photo_tags` | zero rows on the retired object id — **including tags on soft-deleted photos** |
| `user_quick_tags` | zero rows on the retired CLO |
| `category_litter_object` | retired CLO gone; desired CLO exists and is (category, desired) |
| `litter_objects` | `retired_at` set, `merged_into_id` = desired id |
| `photos.summary` | no summary JSON contains the retired `object_id` |
| Redis `{scope}:obj` | retired id absent at global **and** every affected country / state / city |
| Redis `{scope}:rank:objects` | same, at all four scopes |
| Redis `{u:ID}:tags` | no contributing user retains an `obj:{retired_id}` field |
| `metrics` table | desired id reconciles against MySQL |
| `GET /api/tags/all` | retired key absent from the picker, desired key present |
| **`GET /api/locations/{type}/{id}/tags/*`** | **`top`, `summary`, `by-category`, `cleanup`, `trending` — all serve an `objects` dimension off the location Redis hashes; retired key absent from each** |
| `GET /api/user/top-tags` | retired CLO absent from Quick Tags suggestions |
| CSV export | retired column gone; desired column carries the combined total |
| Code references | zero in `TagsConfig`, `BrandsConfig`, `langs/*/litter.json`, rest of `app/` |

### What `--verify` asserts today (2026-08-09)

Five checks, chosen because each can fail independently. An earlier ten-check version restated
itself — "retired key absent from picker" is implied by `retired_at` being set plus the pivot
being dropped, and both of those were self-checks on work the command had just done in the same
run.

1. `photo_tags` drained — zero rows on the retired object id, including tags on soft-deleted photos
2. surviving pivot exists — (category, desired object) is present, so the key is selectable
3. quick tags survived the repoint — count on the **surviving** CLO ≥ `quick_tags_on_retired_clo`
4. no `photos.summary` references the retired object id
5. Redis reconciles **absolutely** — MySQL vs Redis for both objects at global, every affected
   country / state / city, and every contributing user's `{u:ID}:tags` hash

Check 3 is deliberately an assertion of survival, not of absence. `user_quick_tags.clo_id`
cascades on delete and the retired pivot is dropped at the end of a run, so "zero rows on the
retired CLO" is true whether the presets were repointed or silently destroyed.

Check 5 is what the deleted `olm:tag-retirement-snapshot` harness used to do. Folding it into
`--verify` means the rehearsal instrument and the production gate are the same tool; the
previous global-only check covered a small fraction of what a retirement moves.

Not asserted by the command, and checked by hand or by test instead: the retirement state
columns (covered by `MigrateTagTest::test_picker_is_closed_before_data_moves`), the picker
endpoints (covered by `test_retired_object_is_excluded_from_the_tag_picker`), the locations
API, the CSV export, and every code/translation surface in §4 B.4–B.6.

Neither `/api/tags` nor `/api/tags/all` is cached — `GetTagsController` holds no `Cache::` call
and no tag cache key exists in `app/`, `routes/`, `config/` or `resources/js/`. Both read live
from MySQL, so a verify read reflects current state. **If caching is ever introduced, `--verify`
must flush or bypass it**, or the assertion silently passes against stale data.

Excluded from the code-reference check by design: `ClassifyTagsService` and the migration
scripts under `app/Console/Commands/tmp/v5/Migration/`, which are frozen history.

**On the Redis rows.** `RedisMetricsCollector::updateTags()` writes object counts to a `:obj`
hash *and* a `rank:objects` ZSET for every scope in `RedisKeys::getPhotoScopes()` — global,
country, state, city — plus an `obj:{id}` field in each contributing user's `{u:ID}:tags` hash.
The first entry alone touches 10,051 photos spread across many countries and users. Verifying
the global hash only, as the current command does, covers a small fraction of what moved.

Reconciliation is **absolute** (MySQL vs Redis), never a before/after delta. A delta cannot
survive a retry: once MySQL and `processed_tags` are updated, `MetricsService` produces no
second delta, so a rerun after a Redis outage would fail forever.

---

## 7. Testing and rehearsal

**Feature tests** (`olm_test`, factories, `RefreshDatabase`) cover everything deterministic:
lifecycle gating, refusal cases, idempotent resume, pivot creation, quick-tag repointing, and
each `--verify` assertion failing when it should. These run on every change.

**Rehearsal** against `olm_postmig_2` covers what factories cannot — 10,051 real rows across
real countries and users, real Redis fan-out, a real CSV export.

The loop, scripted as `rehearse-entry1.sh` so repeatability is cheap:

```
restore olm_postmig_2 from the .sql source of truth
migrate, flush + rebuild Redis
globals()  →  before
php artisan olm:migrate-tag --entry=other--plastic_bag --apply
php artisan olm:migrate-tag --entry=other--plastic_bag --verify
globals()  →  after
restore, repeat
```

**There is no separate measurement harness.** `olm:tag-retirement-snapshot` — 965 lines
producing JSON artefacts — was deleted on 2026-08-09 and its all-scope MySQL↔Redis
reconciliation folded into `--verify` (§6). The rehearsal instrument and the production gate are
now the same tool, so rehearsal cannot pass against assertions the real run does not make.

`globals()` is the one thing `--verify` cannot know: that nothing OUTSIDE the retirement moved.
Six SELECTs either side — total `photo_tags` rows and quantity, total live photos and XP, the
two objects' rows/items/photos, their pivots, their `retired_at`/`merged_into_id` state, and
quick-tag counts on both CLOs. Anything that changes and is not the retirement is a bug.

**Readiness bar: determinism.** Same restore, same entry, identical before-globals,
after-globals and verify output, three runs in a row. Until that holds it does not go near
production — the standard the incident doc set, and the one `fix-orphaned-tags` never met.

Reviews: one between implementation and the first `--apply` on the rehearsal database, and a
second after rehearsal before production.

---

## 8. Impact on existing code

**`olm:migrate-tag` — `supportedScope()` is reworked, not extended.** All three of its
data-asserted guards encode the opposite direction to D-4 and come out:

| v1 asserts | Reality for entry 1 |
|---|---|
| source object has **zero** pivots | `plastic_bag` has CLO 111 → refuses |
| every source row has a **null** CLO | all 253 rows carry CLO 111 → refuses |
| target CLO **already exists** | no pivot exists for (other, `plasticBags`) → refuses |

**Quick Tags migration returns.** It was deleted on the reasoning that "a genuinely pivotless
source cannot hold a stored CLO reference" — true in the old direction, false in this one.
**128 `user_quick_tags` rows point at CLO 111**; without repointing, those saved presets break.

**`MigrateTagTest`** — 25 tests today. The three boundary tests now assert the inverse, the
deleted quick-tags test returns, and the verify-surface tests are new. Roughly a third rewrites.

**`TagMigrationQueue-2026-08.csv`** — its single row encoded the pre-D-4 direction. Replaced by
the new list and deleted.

**`olm:fix-orphaned-tags`** — unchanged, unapproved, still **do not run**.

---

## 8a. Open ticket — `olm:redis:rebuild` does not reproduce the `metrics` litter total

**Not part of the retirement work.** Filed here because it was measured during it; it needs its
own investigation and must not be bundled into a retirement run.

> **Scope, precisely.** This is a **rebuild fidelity** finding, not a statement about
> production Redis. Production Redis has been maintained incrementally by `MetricsService`
> since it was populated and is far ahead of anything local; it was never part of this
> comparison. What is measured here is a freshly rebuilt Redis against the `metrics` table on
> the same snapshot.
>
> That makes it *more* consequential rather than less: `olm:redis:rebuild` is the documented
> remediation whenever Redis and MySQL disagree, including the remediation `--verify` points
> at. If the rebuild is lossy for litter, then rebuilding production Redis would silently move
> the global litter figure. **Do not rebuild production Redis until this is understood.**

`olm:redis:rebuild` completed cleanly on `olm_postmig_2` (536,947 photos replayed) and reports
its own self-check as:

| Metric | MySQL (`metrics` table) | Redis | Gap |
|---|---:|---:|---:|
| Uploads | 536,947 | 536,947 | 0 |
| XP | 7,429,014 | 7,429,014 | 0 |
| **Litter** | **834,188** | **808,849** | **−25,339** |

Uploads and XP reconcile exactly; only litter diverges. For reference, `photo_tags` summed
across all photos (including deleted and unprocessed) is **837,278**, so there are three
different totals in play and only two of them agree.

**Why it does not block entry 1:** neither object is implicated. Objects 92 and 149 reconcile
absolutely at every scope — 0 mismatches across 1,236 scope checks and 460 user hashes — with
`photo_tags` and `processed_tags` agreeing exactly (264 and 12,946 respectively, drift 0). The
gap is constant across the retirement, so it cancels in the before/after diff.

**Where to start:** the rebuild derives litter as `array_sum(processed_tags['objects'])` per
photo (`RebuildRedisCommand:70`), whereas the `metrics` table was written by `MetricsService`
at processing time. Those are two different definitions, and the gap is the distance between
them. Likely candidates: photos whose `processed_tags` object map does not sum to the `litter`
recorded alongside it, and extra-tag-only rows (brand/material/custom with no object) that
count as litter in one path and contribute nothing to `objects` in the other. Quantify per
photo before changing anything.

**Consequence for `--verify`:** its Redis assertion is scoped to the two objects in the
retirement, both of which reconcile exactly, so this does not weaken it. But its remediation
hint ("rebuild with `olm:redis:rebuild`") should not be followed on production until the
rebuild is known to be faithful.

---

## 9. Open questions

0. **[BLOCKING — blocks entry 1]** **151 processed, live photos have `user_id = NULL`, and
   `RedisMetricsCollector::updateUserMetrics()` type-hints `int $userId`.** Passing null throws
   a `TypeError`, which `processPhoto()`'s `catch (\Exception $e)` at line 81 does **not**
   catch — `TypeError` extends `Error`, not `Exception` — so it propagates and kills the
   process rather than being logged.
   This is not hypothetical for entry 1: **photos 181750, 181751 and 181753** are processed,
   live, null-user, and carry entry-1 tags. A.5 would throw on each. `MigrateTag` catches
   `Throwable` per photo, so the run degrades to "3 photos failed, re-run to retry" — and
   because the `TypeError` is deterministic, it can never succeed.
   It also makes `olm:redis:rebuild` unable to complete: the documented remediation for a Redis
   mismatch dies at photo 16,536, having replayed 15,727 of 536,947.
   Two defects, fixable independently: `user_id` should be `?int` (or the null-user case
   skipped), and the catch should be `\Throwable`.

1. **[blocking, per row]** `desired_key` for the remaining 15 twins and all 46 remaps. Only
   entry 1 is decided.
2. **[non-blocking]** Export header stability. Each twin retirement renames a CSV column
   (`plastic_bag` → `plasticBags`, `face_mask` → `facemask`, `straw` → `straws`). With 98+
   peer-reviewed citations downstream, does this need a deprecation notice, a transition period
   carrying both columns, or is a clean break acceptable?
   **Recommended (review, 2026-08-08): clean break per column, plus a published one-page
   mapping** (`retired_key → desired_key`, with the date) linked from the export page. Carrying
   dual columns for all 16 twins bloats every export and only defers the same break.
3. **[non-blocking]** The 9 missing-pivot cases — separate backlog, but who owns it and when?
4. **[non-blocking]** `Other.php` and its 17 sibling v4 category models still list retired
   column names. In scope here, or left to `PostMigrationCleanup.md`?
5. **[non-blocking]** **Read surfaces were never audited for raw key rendering.** Only the CSV
   export was investigated during the 2026-08-03 incident. Anything else reading
   `litter_objects.key` or `summary.object_id` may be showing users a raw key today — e.g.
   `plasticBags` instead of "Plastic Bag". Unchecked: map popups, profile tag breakdowns,
   mobile tag display, achievements. This is distinct from §6, which verifies one retirement at
   a time; this is a standing audit of what users currently see.

---

## Appendix — measured evidence

Measured 2026-08-08 against `olm_postmig_2` (production import) and the working tree. The
database still reproduces the documented incident baseline exactly — **189,518 pivotless rows /
277,169 items** — so it is unmutated.

```sql
-- Both keys, live
SELECT id, `key`, crowdsourced FROM litter_objects WHERE id IN (92, 149);
--  92  plastic_bag  0
-- 149  plasticBags  1

-- Pivots
SELECT clo.id, clo.category_id, clo.litter_object_id
FROM category_litter_object clo WHERE clo.litter_object_id IN (92, 149);
-- 111, 12, 92     ← plastic_bag only; plasticBags has none

-- Tag rows
SELECT litter_object_id, COUNT(*) rows_ct, SUM(quantity) items,
       COUNT(DISTINCT photo_id) photos,
       SUM(category_litter_object_id IS NOT NULL) with_clo,
       SUM(litter_object_type_id IS NOT NULL) typed
FROM photo_tags WHERE litter_object_id IN (92, 149) GROUP BY litter_object_id;
--  92    253 rows    264 items    253 photos    253 with_clo    0 typed
-- 149  10,051 rows 12,946 items 10,051 photos      0 with_clo    0 typed

-- Quick tags at risk
SELECT COUNT(*) FROM user_quick_tags WHERE clo_id = 111;   -- 128
```

Code references, counted in the working tree:

| Reference | Count |
|---|---|
| `plasticBags` in `app/Tags/BrandsConfig.php` | 12 |
| `plastic_bag` in `app/Tags/BrandsConfig.php` | 0 |
| locale files mentioning `plastic_bag` | 11 |
| locale files mentioning `plasticBags` | 2 |
| `plastic_bags` in `app/` | 2 (`Other.php:25`, `ClassifyTagsService.php:228`) |

Population split, computed from `TagPairMigrationManifest-2026-08.csv`: 74 pairs = 65 legacy
(62 objects, 266,357 items) + 9 canonical-source (9 objects, 10,812 items). Within the 65:
65 object merges, 19 type expansions, 10 category moves, 13 collapses to generic `other`.
Operations overlap, so those do not sum.
