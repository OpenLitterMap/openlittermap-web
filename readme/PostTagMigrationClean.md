# Post-Migration Tag Cleanup — Retirement Process

**Status:** Implemented — `olm:migrate-tag`, covered by `tests/Feature/Migration/MigrateTagTest.php`.
Entry 1 is approved and at `CODE_UPDATED`. **No production data has moved**, and a fresh
rehearsal from a current production snapshot, on the exact commit to be deployed, is required
before `PRODUCTION_APPLIED` — the 2026-08-09 rehearsal is void (the apply control flow changed
after it was recorded).

Scope: what a problematic tag is, the list of them, and the process for retiring them one at a
time. It authorises no specific retirement — each row is approved individually by the product
owner.

Related: `readme/Tags.md` (taxonomy), `readme/ExportData.md` (CSV export), `readme/Metrics.md`
(metrics pipeline). The export-undercount incident record (`PostMigration-2026-08.md`) lives on
branch `audit/2026/08/tag-taxonomy` with the export fix it describes.

---

## 1. What a problematic tag is

**A tag we previously used and no longer want.** Both keys are live in `litter_objects`, both
hold tags, and exactly one should survive:

```
v4 column      app/Models/Litter/Categories/Other.php:25        'plastic_bags'
migration map  app/Services/Tags/ClassifyTagsService.php:228    'plastic_bags' => ['object' => 'plasticBags']
v5 object      litter_objects #149  plasticBags   crowdsourced=1, not in TagsConfig
competing key  litter_objects #92   plastic_bag   crowdsourced=0, TagsConfig:332
```

This definition splits the 74-pair manifest into two populations needing different work:

| Population | Pairs | Objects | Items | Unwanted? |
|---|---:|---:|---:|---|
| **Retirements** | 65 | 62 | 266,357 | Yes |
| Missing pivots — canonical object, category has no pivot | 9 | 9 | 10,812 | **No** |

The nine are `industrial/plastic`, `marine/bag`, `marine/bottle`, `marine/lighters`,
`other/dogshit`, `other/dogshit_in_bag`, `other/tyre`, `sanitary/gloves`, `sanitary/sanitiser` —
tags we keep, simply tagged in a category with no `category_litter_object` row. Seven are
category moves. **They are not retirements and are excluded from this process.**

---

## 2. The list

`readme/audit/TagRetirements-2026-08.csv` — one row per retirement, and the single place
"approved" is recorded. Per row: retired and desired key/id, category, class, measured
rows/items/photos on both sides, XP weight per item, quick-tag count on the retired CLO,
`status`, `approver`, `approved_at`, `verification_evidence`.

Two classes, very different costs:

- **`twin` — 16 rows.** One concept, two spellings; a pure repoint once the owner picks the
  survivor. Every one has the same shape: the camelCase/plural key holds **36,483 rows** against
  **791** on the snake_case/singular twin, so retiring the low-volume side moves 46× less data.
- **`remap` — 46 rows.** No twin exists. `beerCan` becomes `can` + type `beer`; `randomLitter`
  becomes `other`. Genuine taxonomy decisions, and all 19 type expansions and 10 category moves
  are here.

---

## 3. Decisions

- **D-1 — Direction is per-row, not derivable.** `TagsConfig` is not consistently snake_case —
  `rollingPapers`, `vapePen`, `brokenGlass`, `pullRing` and `earSwabs` sit beside `plastic_bag`
  and `bags_litter` — so there is no house convention to appeal to. The list carries an explicit
  owner-set `desired_key`.
- **D-2 — Default is "keep the key that holds the data", confirmed case by case.** `status`
  starts at `IDENTIFIED`; the command refuses to write data below `MAPPING_APPROVED`.
- **D-3 — No new keys, ever.** One surviving key per concept, drawn from keys that already exist.
- **D-4 — Entry 1: retire `plastic_bag` (92), keep `plasticBags` (149).**
- **D-5 — Rehearsal runs against `olm_postmig_2`**, restorable from the owner's `.sql`
  source-of-truth dump, which makes the database itself disposable.
- **D-6 — XP equivalence is a hard refusal, no override.** `supportedScope()` refuses any entry
  whose two keys carry different `XpScore` weights. `bags_litter` (10) → `bagsLitter` (1) is the
  first that will need its own product decision about whether XP moves with the tag or the
  survivor's weighting applies.

---

## 4. The process

One command, one entry, one run.

### Step 0 — close the picker first

`retired_at` is set and filtered out of the picker **before any data moves**. The snapshot is
immutable, so a tag created mid-run is not in it, survives the retirement, and fails `--verify`
with no remediation short of starting over. Deleting the pivot cannot close the door instead —
`photo_tags` still references that CLO at this point. This is the main reason `retired_at` is
worth a schema change.

Necessary but **not sufficient**: it stops writes that *name* the retired object, not a replace
or delete that omits it, and it is read without a row lock. The write freeze in §E.3 is what
actually holds the population still.

### A — move the data

All against an immutable snapshot taken at first run, so a row inserted or edited after approval
is never swept in.

1. Snapshot the retired side's rows, photo ids and baseline items/XP. Queries `photo_tags`
   directly with no join to `photos`, so tags on soft-deleted photos are included — the "zero
   rows on the retired id" assertion spans them too.
2. Create the desired pivot if absent. `plasticBags` has none, and the pivot governs **picker
   visibility only** — export columns derive from `photo_tags` via a key lookup, with no pivot
   dependency.
3. Repoint `photo_tags` — `litter_object_id` and `category_litter_object_id` to the desired
   values. Idempotent per id, so a crashed run resumes rather than failing permanently.
4. Repoint `user_quick_tags.clo_id` from the retired CLO to the desired one.
5. Regenerate `photos.summary`, then re-run `MetricsService::processPhoto()` — **only where
   `processed_at IS NOT NULL AND deleted_at IS NULL`**. `deletePhoto()` already reversed the
   metrics for a soft-deleted photo, so reprocessing it would re-add them and break the absolute
   Redis reconciliation in §6.

Quantities, categories, types, extra tags and physical rows are preserved throughout. Logical
duplicates are **not** merged — 280 collision groups carry differing material/brand/custom-tag
sets. Row-level deduplication is a separate later operation.

### B — deprecate the old key

B.1 runs before A (Step 0); the rest after. The lifecycle puts `CODE_UPDATED` before the apply,
so B.4–B.7 are committed before the data moves. That is safe only where the config edit is inert
until the retirement runs — verified for entry 1, since `TagsConfig` object keys feed only the
suggested types and materials in `getAllTags()` while the picker reads the database — and must be
re-checked per entry.

1. Set `retired_at` and `merged_into_id`; filter retired objects out of the picker. **Precedes A.**
2. Keep the retired CLO row. The picker already excludes the object via `retired_at`. The pivot
   is the only way a stale mobile CLO id can remount onto the survivor after apply. Export
   columns disappear because A.3 drained the rows, not because the pivot is gone.
3. Clear `crowdsourced` on a migration-minted survivor. Not automated; see §E.8.
4. `TagsConfig` — survivor in, retired key out.
5. `BrandsConfig` — repoint references to the survivor. **Must land in the same commit as the
   retirement**, or the next `AutoCreateBrandRelationships` run reintroduces the retired key from
   config. No-op for entry 1: `BrandsConfig` already carries `plasticBags` only.
6. `resources/js/langs/*/litter.json` — all locales, not just `en`.
7. Resurrection guard — `firstOrCreate` paths refuse a retired key. Both of them:
   `AutoCreateBrandRelationships` and `GenerateTagsSeeder`, which rebuilds objects **and** pivots
   straight from `TagsConfig`. B.4 is the intended fix, but it is a hand edit — the guard is what
   stops a stale config silently undoing a completed retirement.

`ClassifyTagsService` is **never edited.** It is the historical record of what the v5 migration
did, and under D-4's direction line 228 is already correct.

> **API writes never create taxonomy relationships.** A stale write can remount only when the
> survivor already has a CLO in the category the client sent. The approved migration is the sole
> owner of survivor-pivot creation (A.2); if that pivot is missing, photo-tag and quick-tag writes
> fail with 422 rather than minting an unapproved category/object pairing. Entry 1 is safe because
> both keys live in `other` and A.2 creates `(other, plasticBags)` before data moves.

### C — prove the new key is used everywhere

Asserted mechanically by `--verify`. See §6.

### D — record completion

`--verify` must pass before `--advance=COMPLETE` is permitted, and the evidence string comes from
the verify output rather than being typed by hand. `--advance` writes `approver`/`approved_at`
**once** — they record the mapping approval and are never overwritten. Every rung after that is
appended to `verification_evidence` as `STATUS by <who> at <when>`.

### E — the production run

1. **Confirm the database before anything runs.** The dry run measures whatever `.env` points at,
   and a rehearsal leaves it on a post-apply database where `expectationsMatch` refuses.
2. **Confirm production is running this branch.** The `--verify` reconciliation was wrong before
   `99502335`, so a verify run on older code cannot be trusted. Separately, per §8: **do not
   rebuild production Redis.**
3. **Freeze tag writes, then drain — mandatory, and a gate of eight steps, not one command.**
   Work through in order; do not start the final dry run until every one has passed.

   1. **Enumerate every serving web node and write the list down.** `config/app.php` has no
      `maintenance` key, so the driver is the framework default `file` and the flag is
      `storage_path('framework/down')` — local disk, **per-node**. `down` on one node leaves the
      others serving.
   2. **`php artisan down` on every node in that list.** Prefer per-node over switching to the
      shared `cache` driver, which is a config change that would itself need committing,
      deploying and verifying identically everywhere on the day. **Do not pass `--secret`** — the
      bypass cookie turns step 3 into a false negative.
   3. **Confirm 503 on an exact endpoint:**
      `curl -s -o /dev/null -w '%{http_code}\n' https://openlittermap.com/api/v3/user/photos`
      That route (`routes/api.php:82`) is in the same `auth:sanctum` v3 group as `POST` and
      `PUT /tags`, so a 503 is direct evidence the tag-write routes are down. A `401` means the
      freeze is not on.
   4. **Verify each node directly wherever the infrastructure allows.** One request through a load
      balancer proves one backend answered. If nodes cannot be addressed individually, say so in
      the run log and treat step 3 as weaker evidence than it looks.
   5. **Drain in-flight PHP/web requests via production's established procedure**, then wait at
      least the maximum request/transaction lifetime. **[OPERATOR PREREQUISITE — the command is
      external to this repository; establish and record it before the run day.]** `down` gates
      request *entry*, not exit. **This step is the actual drain guarantee.**
   6. **Check for open database transactions** — `SELECT * FROM information_schema.innodb_trx;`
      (needs `PROCESS`). An uncommitted transaction is invisible to step 7.
   7. **Only now, the SQL snapshots — a backstop, not proof.** Run twice ~60s apart and require
      identical output:
      ```
      SELECT COUNT(*), MAX(id), MAX(updated_at) FROM photo_tags;
      SELECT COUNT(*), MAX(id), MAX(updated_at) FROM user_quick_tags;
      ```
      The triple covers insert, edit and delete on both tables the run touches. Two identical
      readings do not prove drainage — that is what steps 5 and 6 are for. What they prove is the
      negative: **if either reading moves, something is still writing. Abort and find it.**
   8. **Then start the final dry run.** `php artisan up` only once `--verify` has passed.

   `CheckForMaintenanceMode` is in the **global** middleware stack (`app/Http/Kernel.php:21`) with
   an empty `$except`, so `down` returns 503 for every HTTP route, web and mobile alike. No queued
   job, listener or scheduled command writes `photo_tags`, so once HTTP is stopped everywhere and
   in-flight work has drained, the freeze is complete.

   The freeze spans the **whole** run — from before the final dry run through apply, any
   `--repair-redis`, the apply rerun and verify. Each step measures against a population the
   previous one fixed.

   **Why remount is not a substitute for the freeze.** Live writes that *name* the retired
   object remount onto the survivor. Two cases remount cannot cover: a request that read the
   object as active before Step 0 (no row lock); and a `PUT /api/v3/tags` replace that
   **omits** a snapshotted tag, deleting a row the snapshot holds by id. The freeze closes both.

   **Residual, if the freeze is skipped anyway.** Live writes remount onto the survivor, so a
   late tag is not a leftover on the retired id. A `PUT` that *omits* a snapshotted tag can
   still delete a row the snapshot holds by id — that is why the freeze stays.
4. **Run the entry in one sitting:** dry run → `--advance=DRY_RUN_VERIFIED` → `--apply` →
   `--verify` → advance the remaining rungs. `--advance=COMPLETE` re-runs verify and refuses on
   failure. The dry run reports the affected footprint (users + countries/states/cities) alongside
   its "Measured now" counts, and a successful `--apply` closes with an **Overview** — tags/items
   migrated, photos, distinct users, countries/states/cities, quick tags — read from the snapshot,
   so it counts exactly what the run moved, not the survivor's pre-existing population.
5. **If Redis does not reconcile, repair it — do not rebuild it.** `--apply` retains its snapshot
   and fails on a mismatch. Recovery is four steps:
   ```
   php artisan olm:migrate-tag --entry=… --apply           # fails: Redis mismatch, snapshot kept
   php artisan olm:migrate-tag --entry=… --repair-redis    # Redis now matches MySQL
   php artisan olm:migrate-tag --entry=… --apply           # idempotent rerun — CLEARS the snapshot
   php artisan olm:migrate-tag --entry=… --verify
   ```
   **The rerun is not optional.** `clearState()` is reached only by a successful `--apply`, so
   repair-then-verify passes every assertion while leaving the snapshot on disk and the entry
   looking mid-run. By then the rows are at the target and the pivot is gone, so the rerun moves
   nothing and re-asserts. A mismatch raised by a standalone `--verify` long after a clean apply
   has no snapshot and needs only `--repair-redis` → `--verify`; the command distinguishes the two.
6. **The locations tags API is cached for 10 minutes.** `LocationService::getTopTags()` wraps its
   read in `Cache::remember(…, 600)`. Flush `tags:*` or wait it out before checking that surface;
   the picker endpoints are uncached.
7. **Expect the counts to have drifted.** The list's `retired_*` figures were measured 2026-08-08
   with the picker open since, so the dry run may abort with "data moved since approval". That is
   the guard working — re-measure from its "Measured now" line, update the list, and re-approve.
   Do not advance past a refusal.
8. **Do not run `db:seed` between `CODE_UPDATED` and the apply.** B.4 puts the *survivor* into
   `TagsConfig`, and the survivor is not retired, so the seeder hands it a pivot early. Both keys
   are then selectable at once — a window in which new tags can land on either. The resurrection
   guard does not close this: it only refuses keys that are already retired. If a seed does run,
   re-measure at the dry run before continuing.
9. **B.3 is accepted-cosmetic.** The survivor still reads `crowdsourced=1` after a verified
   retirement. Nothing reads that column; it is not a defect to chase mid-run.

---

## 5. Schema change

One migration on `litter_objects`: `retired_at` (nullable timestamp — the key is deprecated) and
`merged_into_id` (nullable FK to `litter_objects` — what replaced it).

Before this, "deprecated" was an accident of having no pivot row, since the picker selects
objects `whereHas('categories')`. Step A.2 deliberately *creates* pivots, so that accident stops
holding. An explicit fact is something `--verify` can assert, and it lets `firstOrCreate` paths
refuse to resurrect a retired key — load-bearing, because `AutoCreateBrandRelationships`
`firstOrCreate`s both the object **and** a pivot, and the audit found 21 retired objects
referenced by `BrandsConfig`.

---

## 6. Verification surfaces

Every surface a retirement touches, and what proves it. The ten `--verify` checks each fail
independently; `--verify` also runs standalone, days after an apply and against production, where
nothing is self-evident.

| Surface | Assertion | Proved by |
|---|---|---|
| `photo_tags` | zero rows on the retired object id, soft-deleted photos included | `--verify` 1 |
| `category_litter_object` | surviving pivot exists (key is selectable); retired pivot kept and unreferenced | `--verify` 2, 6 |
| `litter_objects` | `retired_at` set; `merged_into_id` = THIS entry's survivor; survivor itself still active | `--verify` 3, 4, 5 |
| `user_quick_tags` | nothing references the retired pivot; quick tags at or above the recorded floor on the **surviving** CLO | `--verify` 7, 8 |
| `photos.summary` | no summary JSON contains the retired `object_id` | `--verify` 9 |
| Redis `:obj` + `rank:objects` + `{u:ID}:tags`, `metrics` | reconcile **absolutely** — MySQL vs Redis for both objects, both structures, at global and every affected country/state/city, plus every contributing user hash | `--verify` 10 |
| Picker endpoints, retirement state columns | retired key absent, desired key present | `MigrateTagTest` |
| Reseeding | `GenerateTagsSeeder` does not rebuild a retired object's pivot | `MigrateTagTest` |
| `GET /api/locations/{type}/{id}/tags/*` | `top`, `summary`, `by-category`, `cleanup`, `trending` — retired key absent from each | by hand |
| `GET /api/user/top-tags` | retired CLO absent from Quick Tags suggestions | `TopTagsTest` |
| CSV export | retired column gone; desired column carries the combined total | by hand |
| Code references | zero in `TagsConfig`, `BrandsConfig`, `langs/*/litter.json`, rest of `app/` | by hand (B.4–B.6) |

Check 5 also runs ahead of the apply's first mutation — verification alone would find a migration
into a retired survivor only once the rows were already behind a closed key. Check 8 asserts
survival, not absence: `user_quick_tags.clo_id` cascades on delete, so "zero rows on the retired
CLO" is true whether presets were repointed or silently destroyed.

Excluded by design: `ClassifyTagsService` and the migration scripts under `tmp/v5/Migration/`,
which are frozen history.

Neither `/api/tags` nor `/api/tags/all` is cached, so a verify read reflects current state. **If
caching is ever introduced, `--verify` must flush or bypass it.**

**Reconciliation is absolute, never a before/after delta.** Once MySQL and `processed_tags` are
updated, `MetricsService` produces no second delta, so a delta-based check could never survive a
retry. `RedisMetricsCollector::processPhoto()` logs and swallows every Redis error, so a run
against a degraded Redis would move MySQL while discarding the matching metrics writes. Two
guards: `--apply` pings Redis before closing the picker, and `--repair-redis` rewrites both
objects' counts from MySQL at every scope. The repair is bounded and complete because the objects
dimension is a retirement's entire Redis footprint — `supportedScope()` holds category, quantities
and XP weighting equal, so no stats hash, HLL or leaderboard ZSET is touched. Zero is written as
absence (`HDEL`/`ZREM`).

---

## 7. Testing and rehearsal

**Feature tests** (`olm_test`, factories, `RefreshDatabase`) cover everything deterministic:
lifecycle gating, refusal cases, idempotent resume, pivot creation, quick-tag repointing, and each
`--verify` assertion failing when it should.

**Rehearsal** against `olm_postmig_2` covers what factories cannot — 10,051 real rows across real
countries and users, real Redis fan-out, a real CSV export. Run by hand; it is short, and a
wrapper script is one more thing to keep true:

```
restore olm_postmig_2 from the .sql source of truth
migrate, flush + rebuild Redis
globals()  →  before
php artisan olm:migrate-tag --entry=other--plastic_bag --apply
php artisan olm:migrate-tag --entry=other--plastic_bag --verify
globals()  →  after
restore, repeat
```

`globals()` is the one thing `--verify` cannot know — that nothing OUTSIDE the retirement moved.
Six SELECTs either side: total `photo_tags` rows and quantity, total live photos and XP, both
objects' rows/items/photos, their pivots, their retirement state, and quick-tag counts on both
CLOs. Anything that changes and is not the retirement is a bug.

**Readiness bar: determinism.** Same restore, same entry, identical before-globals, after-globals
and verify output, three runs in a row.

There is no separate measurement harness — `olm:tag-retirement-snapshot` was deleted and its
all-scope reconciliation folded into `--verify`, so the rehearsal instrument and the production
gate are the same tool.

`olm:fix-orphaned-tags` is **deleted**. Its 75 mappings were never approved and its `plasticBags`
row encoded the pre-D-4 direction, so running it would have moved data backwards onto a retired
object. `master` holds what it did. Approved retirements go through `olm:migrate-tag`, one entry
at a time, against `readme/audit/TagRetirements-2026-08.csv`.

---

## 8. Open ticket — `olm:redis:rebuild` does not reproduce the `metrics` litter total

**Not part of the retirement work**, but it must not be bundled into a retirement run. This is a
**rebuild fidelity** finding, not a statement about production Redis, which has been maintained
incrementally by `MetricsService` and was never part of the comparison. That makes it more
consequential, not less: `olm:redis:rebuild` is the documented remediation whenever Redis and
MySQL disagree. **Do not rebuild production Redis until this is understood.**

On `olm_postmig_2` (536,947 photos replayed) uploads and XP reconcile exactly; litter is
834,188 in `metrics` against 808,849 in Redis — a gap of 25,339. For reference `photo_tags` sums
to 837,278, so three totals are in play and only two agree.

It does not block entry 1: objects 92 and 149 reconcile absolutely at every scope — 0 mismatches
across 1,236 scope checks and 460 user hashes — so the gap is constant across the retirement and
cancels out.

**Where to start:** the rebuild derives litter as `array_sum(processed_tags['objects'])` per photo
(`RebuildRedisCommand:70`), whereas `metrics` was written by `MetricsService` at processing time.
Likely candidates are photos whose `processed_tags` object map does not sum to the recorded
`litter`, and extra-tag-only rows that count as litter in one path and contribute nothing to
`objects` in the other. Quantify per photo before changing anything.

---

## 9. Open questions

1. **[blocking, per row]** `desired_key` for the remaining 15 twins and all 46 remaps. Only entry
   1 is decided.
2. **[non-blocking]** Export header stability. Each twin retirement renames a CSV column, with 98+
   peer-reviewed citations downstream. Recommended: clean break per column, plus a published
   one-page `retired_key → desired_key` mapping linked from the export page.
3. **[non-blocking]** The 9 missing-pivot cases — separate backlog, but who owns it and when?
4. **[non-blocking]** `Other.php` and its 17 sibling v4 category models still list retired column
   names. In scope here, or left to `PostMigrationCleanup.md`?
5. **[non-blocking]** **Read surfaces were never audited for raw key rendering.** Anything reading
   `litter_objects.key` or `summary.object_id` may be showing users a raw key today — e.g.
   `plasticBags` instead of "Plastic Bag". Unchecked: map popups, profile tag breakdowns, mobile
   tag display, achievements.
6. **[non-blocking, future]** **No category reassignment — the survivor inherits the retired
   object's category.** Moved rows keep their original `category_id`, and every rewrite
   (`repointRows`, `remountLingeringCloReferences`, `ensureDesiredPivot`) assumes one unchanged
   category; `supportedScope()` enforces it by refusing any object tagged across more than one
   category. A future entry may need to retire a key *into a different* category (a category move
   folded into the retirement). Lifting this requires rewriting `photo_tags.category_id`,
   resolving/creating the destination CLO in the new category, re-pointing the object's
   metrics/Redis scopes to that category, and re-checking XP weighting under the destination
   category. Out of scope for v1 — note it before scheduling any cross-category entry.

---

## Appendix — measured evidence

Measured 2026-08-08 against `olm_postmig_2` (production import), which still reproduces the
documented incident baseline exactly — 189,518 pivotless rows / 277,169 items.

```sql
SELECT id, `key`, crowdsourced FROM litter_objects WHERE id IN (92, 149);
--  92  plastic_bag  0
-- 149  plasticBags  1

SELECT id, category_id, litter_object_id FROM category_litter_object WHERE litter_object_id IN (92, 149);
-- 111, 12, 92     ← plastic_bag only; plasticBags has none

SELECT litter_object_id, COUNT(*) rows_ct, SUM(quantity) items, COUNT(DISTINCT photo_id) photos
FROM photo_tags WHERE litter_object_id IN (92, 149) GROUP BY litter_object_id;
--  92     253 rows    264 items    253 photos
-- 149  10,051 rows 12,946 items 10,051 photos

SELECT COUNT(*) FROM user_quick_tags WHERE clo_id = 111;   -- 128
```

Population split, from `TagPairMigrationManifest-2026-08.csv`: 74 pairs = 65 legacy (62 objects,
266,357 items) + 9 canonical-source (9 objects, 10,812 items). Within the 65: 65 object merges,
19 type expansions, 10 category moves, 13 collapses to generic `other`. Operations overlap, so
those do not sum.
