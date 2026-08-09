# GOODMORNING — session handoff
**Written:** 2026-08-09 23:08 · **Branch:** bugs/2026/08/plastic-bag-count · **HEAD:** 99502335 · **Tree:** 16 uncommitted files
**Gates at last run:** BE tests 1388 passed, 1 skipped (6885 assertions) · phpstan not run · vue-tsc not run · lint/format not run · build not run · rehearsal 3/3 byte-identical triplets on `olm_postmig_3`

## Start here tomorrow
1–3 exact actions. These are PROPOSALS for the product owner — do not self-start any of them; execution waits for a dispatch.

1. **Review the rehearsal evidence and the workflow review together, then decide whether entry 1 advances.** Everything up to the decision is done: rehearsal passed the determinism bar, Step B is applied, suite is green. The status is deliberately still `MAPPING_APPROVED`. Advancing means `--advance=CODE_UPDATED` then `DRY_RUN_VERIFIED` on the canonical list.
2. **Commit the 16 uncommitted files** using the block in "Uncommitted changes" below. `docs/GOODMORNING.md` must be EXCLUDED — it was deliberately cut from this branch and only exists again because the GOODNIGHT routine rewrites it.
3. **Decide `desired_key` for the remaining 15 twins.** It is the blocking input for every subsequent entry, and D-1 says it is not derivable. `bags_litter` is now a hard stop in code (D-6) until the XP question is answered.

## Where we are
- **Entry 1 is rehearsal-complete and Step-B-complete, but NOT advanced.** Three byte-identical triplets from three clean 1.0 GB restores: before `aef36acf`, after `55927227`, verify `b3c035be`. The verify hash matches all four earlier captures — seven byte-identical verify runs.
- **`--verify` now carries the rehearsal.** `olm:tag-retirement-snapshot` (965 lines) was deleted and its all-scope MySQL↔Redis reconciliation folded in, so the production gate and the rehearsal instrument are the same tool. Branch went 4,736 → 3,486 insertions.
- **The rehearsal caught a real bug in that fold-in**: `Query\Builder::pluck($col, $key)` re-selects only the columns it is handed, discarding `selectRaw` aliases on a grouped query, returning 0 where the true value was 951. Fixed in `99502335`; regression test confirmed to fail when reintroduced. `bae54be8` alone is broken — never merge it without `99502335`.
- **`olm_postmig_3` holds post-apply state** from rehearsal iteration 3. `.env` points at it. Restore from `/tmp/olm-dump.sql` (1.0 GB, readable) before any further rehearsal — the script does this itself.
- No STOP conditions outstanding. The one hit this session (after-globals hash divergence) was diagnosed as a wall-clock `retired_at` in the capture, fixed, and re-run clean.

## Dispatch ledger
- D1 `/simplify` the branch → **executed** (4 review agents, −42 lines, findings applied or explicitly skipped)
- D2 audit + reduction proposal, no changes → **executed** (inventory, per-file buckets, target shape)
- D3 execute approved cut list with 2 amendments → **executed** (5 files deleted, MigrateTag/test/script trimmed, D-6 hard XP refusal, `createTagLegacy` gap documented)
- D4 commit the fix → **already done by the owner** as `99502335` before my `git add` ran; nothing for me to do
- D5 workflow review (§4 conformance, CSV/enforcement mismatches, per-entry cost, bloat) → **executed**, reported in chat, findings mirrored into `readme/changelog/2026-08-09.md`
- D6 fix globals(), re-run 3 iterations, write evidence, Step B, full suite → **executed**
- Referenced-by-owner-but-never-received: **none**

## Done this session
- `bae54be8` (owner) — the approved cut list: `TagRetirementSnapshot.php`, `docs/GOODMORNING.md`, `TagCleanupSummary`, `LitterObjectBacklog`, `TagMigrationQueue` deleted; MigrateTag/test/script trimmed; −1,677 lines.
- `99502335` (owner) — the `pluck()` → `get()` reconciliation fix plus its regression test. **`bae54be8` is broken without this.**
- Uncommitted: the globals() timestamp fix, the completed rehearsal evidence, and all of Step B (see below).
- Rehearsal: 3/3 byte-identical, `--verify` 5/5 every run, object 149 reconciled across 1,646 Redis scopes.

## Uncommitted changes (the only copy)
**Dirty at session start:** none — the tree was clean at `a2a40af1`.

**This session's edits (16 files):**
- `app/Console/Commands/tmp/v5/PostMigAug2026/rehearse-entry1.sh` — `retired_at` captured as a 0/1 flag, not a timestamp
- `app/Tags/TagsConfig.php` — B.4: `other.plastic_bag` → `other.plasticBags`
- `readme/audit/TagRetirements-2026-08.csv` — evidence cell rewritten (status untouched)
- `readme/changelog/2026-08-09.md` — the day's record
- `resources/js/langs/{de,es,fr,hu,nl,pl,pt,sw}/litter.json` — B.6: `plastic_bags` → `plasticBags`, translations preserved
- `resources/js/langs/en.json`, `resources/js/langs/en/litter.json` — dropped the now-duplicate `plastic_bags`
- `tests/Feature/Migration/MigrateTagTest.php` — fixture builds the retired object/pivot itself; drops the pivot the seeder now gives `plasticBags`
- `tests/Unit/Exports/CreateCSVExportTest.php` — same pivot drop; its regression is about pivotless objects

**New (untracked, nowhere else):** `docs/GOODMORNING.md` — this file. Do NOT commit it to this branch.

## Open questions for the product owner
- **[blocking]** `desired_key` for the remaining 15 twins and all 46 remaps. Only entry 1 is decided.
- **[blocking]** `bags_litter` (10 XP) → `bagsLitter` (1 XP): does XP move with the tag, or does the survivor inherit the weighting? D-6 made this a hard refusal with no override, so the entry cannot run until this is answered.
- **[non-blocking]** B.3 (clear `crowdsourced` on the survivor) is documented but never implemented — object 149 still reads `crowdsourced=1` after a verified retirement. Cosmetic; nothing reads that column.
- **[non-blocking]** `--evidence` is unenforced free text; §4 D specifies auto-population from verify output.
- **[non-blocking]** Nine of 25 CSV columns are dead to the code (`desired_clo_id`, `retired_clo_id`, `desired_rows`, `desired_items`, `desired_photos`, `xp_retired_per_item`, `xp_desired_per_item`, `xp_equivalent`, `notes`).
- **[non-blocking]** Measuring a new entry has no tool since the harness was deleted. Workaround: placeholders → dry run → read "Measured now" → fill in. Undocumented.
- **[non-blocking]** The 46 remaps (19 type expansions, 10 category moves) are refused by `supportedScope()`, not supported. Second tool, not 46 more runs.
- **[non-blocking]** `/api/tags` (`GetTagsController::index`) never got the `active()` scope, so it still offers retired keys while `/api/tags/all` hides them. `createTagLegacy` is likewise unguarded (recorded in the design doc as a known limitation).
- **[non-blocking]** `olm:redis:rebuild` litter gap (§8a): 834,188 MySQL vs 808,849 Redis, reproduced exactly on every fresh import. Do not rebuild production Redis until understood.
