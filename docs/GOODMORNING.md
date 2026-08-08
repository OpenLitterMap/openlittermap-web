# GOODMORNING — session handoff
**Written:** 2026-08-08 20:35 · **Branch:** bugs/2026/08/plastic-bag-count · **HEAD:** e6a1a4b3 · **Tree:** 22 uncommitted files
**Gates at last run:** BE tests — MigrateTagTest 28 passed; Redis+Metrics+Tags+Migration 238 passed (2,008 assertions); Photos+QuickTags+Leaderboard+Teams 268 passed (1,019 assertions) · phpstan not run · vue-tsc not run · lint/format not run · build not run · full suite not run this session

## Start here tomorrow
1–3 exact actions. These are PROPOSALS for the product owner — do not self-start any of them; execution waits for a dispatch.

1. **Step B for entry 1** — the manual code/config half: `TagsConfig` (`plastic_bag` out, `plasticBags` in), `BrandsConfig` (12 refs already point at `plasticBags`, verify no `plastic_bag` refs), `resources/js/langs/*/litter.json` (11 locales carry `plastic_bag`, 2 carry `plasticBags`). Then `--advance=CODE_UPDATED`.
2. **Chase the `olm:redis:rebuild` litter gap** (`PostTagMigrationClean.md` §8a). Rebuild reads 808,849 vs the `metrics` table's 834,188. Hypothesis to test first: the rebuild derives litter as `array_sum(processed_tags['objects'])`, which counts nothing for extra-tag-only rows (brand/material/custom with null `litter_object_id`). Blocks any production Redis rebuild.
3. **Batch the 15 remaining spelling twins** — 791 rows total, mechanically identical to entry 1. Needs `desired_key` confirmed per row first (decision D-2: default is keep the data-heavy side, confirmed case by case).

## Where we are
- **Entry 1 (`other--plastic_bag` → `plasticBags`) rehearsal PASSED.** Three iterations byte-identical (before `03051759`, after `e820e905`, diff `af9cfd29`), verify 10/10 each run, globals unchanged, Redis 1,196/1,196 scopes and 450/450 user hashes correct. Awaiting the second §7 review.
- Entry 1 status is deliberately still `MAPPING_APPROVED`. Advancing past `CODE_UPDATED` would assert step-B work that has not been done.
- **`olm_postmig_2` is NOT in its baseline state** — it holds the post-apply result of rehearsal iteration 3 (0 rows on object 92, 10,304 on 149, 1 retired object). The `retired_at`/`merged_into_id` migration has also been run against it, and its Redis was flushed and rebuilt. Restore from the owner's `.sql` before any further rehearsal.
- The 1.0 GB baseline snapshot I took lives in the session scratchpad under `/private/tmp/` and will not survive. The owner's `.sql` source of truth is the recovery path; its location was never recorded here.
- STOP conditions hit and cleared this session: XP-weighting check (entry 1 clean at 1→1; `bags_litter`→`bagsLitter` is 10→1 and is now hard-refused unless the list says `xp_equivalent=accepted`), and the null-user metrics defect (fixed).

## Dispatch ledger
- D1 summarise design into a new doc → **executed** (`readme/PostTagMigrationClean.md`)
- D2 consolidate `PostMigration-2026-08.md` → **executed** (27KB → 12KB incident record; dangling `TagAudit-2026-08.md` reference fixed)
- D3 build snapshot harness, report before-capture + determinism → **executed** (`olm:tag-retirement-snapshot`)
- D4 fix the two metrics defects, rebuild Redis, resume at Step 0 → **executed**; audit found more than the crash site (see Done)
- D5 implement retirement pipeline for entry 1 through tests, stop before `--apply` → **executed**
- D6 rehearsal, 3 iterations → **executed**, passed on the third attempt (two harness defects fixed en route)
- D7 "file the litter-gap follow-up" → **executed** (§8a)
- Referenced-by-owner-but-never-received: **none**

## Done this session
No commits. All work is uncommitted. Shas: none.

- Two metrics defects fixed: ownerless-photo handling across the whole path (not just the crash site — the `TypeError` was masking six `(string)null → ""` writes into the contributor HLL, contributor ZSET and global XP leaderboard, because a throw inside `Redis::pipeline` discards every queued command), and a second independent `TypeError` in `MetricsService::buildTimeSeriesRows()`. `catch (\Exception)` → `catch (\Throwable)` at two sites.
- `olm:tag-retirement-snapshot` — read-only before/after harness with absolute MySQL↔Redis reconciliation across every location scope and user hash.
- `litter_objects.retired_at` + `merged_into_id` migration; `LitterObject::active()` scope; `getAllTags()` filtered.
- `olm:migrate-tag` reworked for the D-4 direction: inverted boundary guards, XP-equivalence refusal, Step-0 picker closure, pivot creation, quick-tags repointing, soft-delete-guarded metrics, `--verify` (10 assertions) gating `COMPLETE`.
- Resurrection guards in `AddTagsToPhotoAction` (422 on a retired CLO) and `AutoCreateBrandRelationships` (skips retired objects).
- `MigrateTagTest` reworked to 28 tests; new `RedisMetricsCollectorNullUserTest` (7 tests).
- Redis rebuilt clean on `olm_postmig_2` (536,947 photos).

## Uncommitted changes (the only copy)
**Dirty at session start (previous session's work, untouched by me):** `CLAUDE.md`, `readme/audit/TagCleanupSummary-2026-08.md`, `readme/audit/TagMigrationQueue-2026-08.csv`, `readme/changelog/2026-08-03.md`, `readme/audit/LitterObjectBacklog-2026-08.csv` (untracked).

**This session's edits:**
- `app/Services/Redis/RedisMetricsCollector.php` — null-user guards on all six user-scoped writes; `?int`; Throwable
- `app/Services/Metrics/MetricsService.php` — skip per-user metrics row when ownerless; three XP-update guards
- `app/Console/Commands/tmp/v5/PostMigAug2026/MigrateTag.php` — reworked for the D-4 direction (+692/−...)
- `app/Actions/Tags/AddTagsToPhotoAction.php` — refuse tagging a retired object
- `app/Console/Commands/tmp/v5/Migration/Brands/AutoCreateBrandRelationships.php` — skip retired objects
- `app/Http/Controllers/API/Tags/GetTagsController.php` — `.active()` on the picker query
- `app/Models/Litter/Tags/LitterObject.php` — cast, `active()` scope, `isRetired()`, `mergedInto()`
- `tests/Feature/Migration/MigrateTagTest.php` — 28 tests for the inverted direction
- `readme/PostMigration-2026-08.md` — reduced to an incident record
- `readme/ExportData.md` — column-ordering caveat

**New (untracked — nowhere else):**
- `app/Console/Commands/tmp/v5/PostMigAug2026/TagRetirementSnapshot.php` — the measurement harness
- `app/Console/Commands/tmp/v5/PostMigAug2026/rehearse-entry1.sh` — the rehearsal loop
- `database/migrations/2026_08_08_182838_add_retired_at_and_merged_into_id_to_litter_objects_table.php`
- `readme/PostTagMigrationClean.md` — the design doc and forward process
- `readme/audit/TagRetirements-2026-08.csv` — the retirement list, entry 1
- `readme/changelog/2026-08-08.md`
- `tests/Unit/Redis/RedisMetricsCollectorNullUserTest.php`

## Open questions for the product owner
- **[blocking]** `desired_key` for the remaining 15 twins and all 46 remaps. Only entry 1 is decided.
- **[blocking]** `bags_litter` (10 XP) → `bagsLitter` (1 XP): does XP move with the tag, or does the surviving key inherit the 10 XP weighting? The command refuses this retirement until the list records `xp_equivalent=accepted`.
- **[non-blocking]** Export header stability across 16 twin renames. Recommendation on file: clean break per column plus a published `retired_key → desired_key` mapping page.
- **[non-blocking]** 2,239 items on `bagsLitter` are currently under-credited at 1 XP versus 161 items on `bags_litter` at 10 XP — a live scoring inconsistency independent of any retirement. Separate fix, or leave?
- **[non-blocking]** The 9 missing-pivot cases (`marine/bag`, `other/dogshit`, …) — separate backlog, unowned.
- **[non-blocking]** Read surfaces never audited for raw key rendering: map popups, profile tag breakdowns, mobile tag display, achievements.
