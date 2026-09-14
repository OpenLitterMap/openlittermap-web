# Migrate one object tag

`olm:migrate-tag OLD NEW` updates one object at a time. This release keeps each observation's category. It does not require a source CLO or run `GenerateTagsSeeder` in production.

## Plastic bags first

```bash
# Preview only — no changes
php artisan olm:migrate-tag plasticBags plastic_bag

# Apply after the checks below, with writes paused
php artisan olm:migrate-tag plasticBags plastic_bag --apply

# Same preview after completion reports Already applied
php artisan olm:migrate-tag plasticBags plastic_bag
```

The old `plasticBags` object records `plastic_bag` as its replacement. Existing `photo_tags` rows get the new object ID and the destination category's CLO ID. Row IDs, categories, quantities, collection status, extras and both observation timestamps stay unchanged. Existing `plastic_bag` observations stay unchanged too.

Every affected photo gets a regenerated summary using the existing XP rules; `photos.updated_at` changes. Previously processed, non-deleted photos get metrics deltas, regardless of verification level. Unprocessed school photos wait for approval. Soft-deleted photos get corrected rows and summaries without metrics updates. The command does not approve or publish photos.

Old object submissions and old CLO submissions resolve through the recorded replacement before saving. Retired objects disappear from the catalogue, search and generated top tags. Saved quick tags move to the destination CLO; their other fields stay intact, except an explicitly recorded replacement type overrides their type.

## What else changes when a key is renamed?

The command updates stored data. It does **not** rename references in PHP, JavaScript, translations or the mobile app. Review these references for every approved mapping.

For `plasticBags → plastic_bag`:

- **Catalogue:** `TagsConfig` already declares `other/plastic_bag`. The replacement object and its category/object entry (CLO) already exist in the fresh local database. Confirm they exist in the deployment database too; no production seeder is needed for this mapping.
- **Brand configuration — still to update:** replace the 12 `plasticBags` references in `app/Tags/BrandsConfig.php` with `plastic_bag`. For example, Aldi's `other` objects should contain `plastic_bag`. No current runtime reader of this configuration was found; updating the file keeps its associations consistent. It does not update stored brand relationships or require running the old brand tooling.
- **Translations — still to update:** add `"plastic_bag": "Plastic Bags"` under `litter.other` in `resources/js/langs/en.json`, which the current web app loads. Add the same entry under `other` in `resources/js/langs/en/litter.json`. Keep the old entries so historical data can still display a label. Without the new entry, tagging search falls back to “Plastic Bag” from the key.
- **Photo summaries:** the command regenerates affected summaries with the new object ID and key. For example, five `plasticBags` become five `plastic_bag`; the quantity stays five.
- **Downloads:** newly generated CSV exports read the updated catalogue and summaries, so they use `plastic_bag`. The command does not rewrite previously generated or downloaded files; generate a fresh export to see the change.
- **Suggestions and saved choices:** the server hides the retired object from new suggestions and updates saved quick tags that reference its CLO. An already-open app can still hold an old catalogue; supported stale submissions use the recorded replacement when saved. Refresh the catalogue during smoke checks.
- **Mobile app — still to check:** inspect its own key references and translations, then check a supported build with a refreshed catalogue and a stale submission. This web-repository review does not verify the mobile repository.

Keep `plasticBags` where it describes the old data:

- Historical conversion definitions in `OldTagsConfig` and `ClassifyTagsService`, and old repair scripts.
- Migration tests, command examples and audit records showing the old-to-new mapping.
- Legacy translations needed to display old data. The older `plastic_bags` database columns are separate historical fields; do not rename them with this object migration.

Before deployment, finish the pending configuration and translation edits above, build the frontend, and check the displayed label and a fresh export. These edits are separate from `--apply`; running the command alone does not complete them.

## Supported mappings and limits

Both objects must already exist. The destination must be active and have a CLO in each source category with observations or saved quick tags. Empty source CLOs do not require destinations. No source CLO is created.

```bash
# Separate, later mapping — review and rehearse independently
php artisan olm:migrate-tag energy_can can --type=energy
php artisan olm:migrate-tag energy_can can --type=energy --apply
```

Optional `--create-destination` creates only missing destination CLOs declared in `TagsConfig`, plus their declared type associations. All those type records must already exist. The preview prints the exact proposed rows. It does not create objects, categories, materials or types, or run seeders. Neither of the first two mappings should need this flag if their approved destination CLOs already exist.

Source observations with a null category or an existing type are refused. Category moves and migrations of already-typed observations are follow-up work. Incompatible destination types or saved quick-tag types stop the run before retirement. The same checks run in preview and apply.

`--allow-xp-change` is required when the old and new object keys have different per-item XP under the existing summary calculator. A zero per-item delta is **not** a promise of zero whole-photo XP drift: every affected photo is rescored using current rules, including its other observations. Compare XP during rehearsal before approving a mapping.

The latest non-null type recorded in an object replacement chain wins. Without a recorded type, a supplied type must be allowed at the final destination or the API returns 422 atomically. For a retired object submitted without a category, the destination and effective type must identify one category; otherwise the API returns 422. Explicit object-format categories are preserved or rejected, never silently substituted. See [API contract](API.md).

Matching retries resume the remaining rows; conflicting object/type arguments fail. A completed A → B can be replayed after B → C. B → C is refused while an earlier migration into B still has source rows or quick tags. The command uses one MySQL advisory lock for the entire apply, with source and destination row locks in ID order during retirement. It checks lock ownership and Redis connectivity before each batch of 200 photos. This does not replace the write freeze.

## Rehearsal and production window

1. Record the candidate commit, MySQL/Redis identities and versions, and reviewed mapping arguments. Rehearse on a **fresh disposable production copy** with production-compatible runtimes. The current local baseline is `olm_postmig_7`; keep it read-only and rehearse on a separate copy. It has 10,051 `plasticBags` observations and no retirement columns as checked on 2026-09-14. Install the retirement schema on the rehearsal copy before previewing the command. Earlier `_6` rehearsal figures are historical evidence, not this baseline.
2. Take verified database backups. Record source rows/quantities by category, destination totals, extras, saved quick tags, per-photo/user XP, MySQL metric values, and Redis object counts in every affected global/location/user scope. Record unresolved combinations separately; this command does not fix unrelated tags.
3. Measure the actual mapping, including batch time, summary regeneration and verification. Earlier estimates were about 51 photo batches for plastic bags and 103 for energy cans; size the window from the fresh rehearsal, not those estimates.
4. Freeze uploads, tag edits, approvals and other writes on **every node**. Drain in-flight requests, pause schedules and workers, and verify they are stopped. `horizon:terminate` alone allows a supervisor to restart workers.
5. Deploy the candidate code and apply its two schema migrations with `php artisan migrate --force`. Confirm no unrelated pending migrations will run. Schema and replacement-aware application code must both be live before writes reopen. Clear/restart long-lived application processes as appropriate for the deployed runtime.
6. Run the dry run and review its used categories, destinations, quantities, quick tags, type and XP warning. Apply **only** `plasticBags → plastic_bag`. Do not run `GenerateTagsSeeder`, `composer seed:tags`, `olm:v5` or the temporary brand relationship tooling in this window.
7. Confirm no source observations or source quick tags remain. Compare observation IDs and fields, extras, summary keys and totals, photo/user XP and MySQL metric values against the baseline. Verify the existing new-tag ×5 observation is unchanged. Execute wide and long CSV exports and check the new keys and quantities. Replay must be a no-op.
8. Verify Redis old-object counts decreased by the moved processed quantities and new-object counts increased by the same amount **in each affected scope**, including user scopes. Exclude unprocessed and deleted photos. Global/location object counts use `RedisKeys::objects($scope)`; user object counts use `{u:ID}:tags` fields `obj:OBJECT_ID`. Other tags, aggregate litter, XP and rankings must reconcile with the rehearsal. Do not rely on the command's success message alone.
9. Smoke-test stale object and CLO submissions, ordinary editing and school approval. A mixed photo with another unresolved category/object combination can still reject an edit; acknowledge this limitation rather than claiming it is fixed. Resume traffic only after checks pass.

An interrupted run keeps writes paused. Each completed photo batch is committed; an incomplete batch rolls back. Inspect the error and rerun the exact approved arguments. Do not roll back schema/application code after retirement: stale requests still need the resolver. Restoring pre-migration data requires the backup and a coordinated outage.

## Redis recovery

MySQL and Redis do not share a transaction. Redis failures can leave SQL committed; the collector logs failures, so even a successful command needs independent delta verification. Re-running `processPhoto` cannot repair counters when the stored fingerprint already matches.

The existing recovery command is:

```bash
php artisan olm:redis:rebuild
```

It confirms before flushing the configured Redis database, then replays **all processed non-deleted photos**, using `processed_tags` and `processed_xp`. SQL metrics totals alone cannot reconstruct per-object hashes. Check the target Redis database and all of its contents first, and measure this full rebuild on the rehearsal copy. Keep writes paused through recovery and verification. Do not use `--no-flush` against populated counters: replay adds counts again. A shared Redis database needs a separate reviewed recovery procedure.

## Follow-up PRs

Use `bugs/2026/08/plastic-bag-count` as a source of reviewed implementations and regression tests. The comparison below was made against its tip `8f4e4087`. Port each useful change onto the merged command branch and test it there; the old branch's CLO redirects and historical catalogue are different from this release's object replacements.

The current PR already carries the single-object command, replacement-aware submissions, saved quick-tag migration, retired-object filtering, ownerless metrics fixes and test-database protections. Keep those implementations when bringing over follow-up work.

### Recommended order

1. **Preserve tags through every editor.** Reuse the original branch's pure `useTagEditorState.js` adapter and endpoint-backed editor tests, adapted to the current API. Repair the web editor, admin queue, facilitator queue and team photo modal together with their server contracts. Preserve explicit categories, types, independent brand quantities, all extras and `true`/`false`/`null` collection status. Take the photo-row lock before replacing tags; failed edits must restore both photo fields and observations. Keep school privacy and approval, including editing already-approved photos and updating their metrics. Retain the agreed custom-tag behaviour: separate new cards remain separate observations, while an existing combined observation stays combined. Test quantities 2 and 5 producing 7 custom-tag XP in both frontend and backend. Bring the Node test bridge, Node 22 setup, dependency installation and frontend build into CI with these tests. These are endpoint-backed tests, not browser tests; perform focused editor smoke checks separately.

2. **Allow safe edits while other tags await migration.** This is the main gap the original branch solved through global historical-CLO preparation. Do not copy that requirement into this rollout. Proposed behaviour: when a photo contains `plastic_bag ×2` and an unresolved `randomLitter` observation, changing bags to 3 should preserve the existing `randomLitter` observation unchanged. Design an explicit way to identify and preserve an existing observation, scoped to the authorised photo; do not accept arbitrary new category/object combinations. Changes to the unresolved observation itself still need a defined validation rule. Prove this with a mixed-photo round trip on an unprepared catalogue. Earlier evidence found 3,156 mixed plastic-bag photos and 179,467 other unresolved observations after a plastic-bag run; recount on the fresh rehearsal copy. PR 1 alone does not close this gap.

3. **Make integrity checks useful for staged migrations.** Adapt the original verifier's missing-combination checks, scoped-repair safeguards and recount-after-repair tests. Report remaining observations and quick tags on retired objects, and broken object replacement chains, using this branch's schema. Distinguish missing catalogue entries from stale stored CLO IDs and legitimate extra-only observations. Explain exactly what `--fix` can repair; it must not invent taxonomy. Test that a photo-scoped repair cannot modify another photo. Do not copy checks for historical or redirected CLO columns that this branch does not have.

4. **Make typed-object XP consistent.** Port the original branch's per-observation type-aware scoring with matching frontend calculations and regression tests. Example: `dumping` with type `small` should use the approved small-dumping score. Update the migration's XP warning in the same PR so it agrees with the actual summary calculator. Measure effects on existing photos and decide any rescore separately; do not silently recalculate all users' XP as part of deployment.

5. **Make achievement seeding repeatable.** Port the insert-only timestamp behaviour and repeat-execution tests, including definitions with null tag IDs. Preserve existing achievement definitions and `user_achievements`. Decide separately how achievements attached to retired objects should be treated; the timestamp fix does not transfer them. Run any approved seeding outside the tag-migration window, off peak.

6. **Support category moves or already-typed observations when needed.** Use the old branch's category/type and retry tests as examples, then design the smallest extension compatible with object replacements. Example: moving only `sanitary/gloves` to `medical/gloves` must not retire the shared `gloves` object globally. This needs a category-specific mapping and a rule for stale submissions. Add it when an approved mapping requires it, with interruption and replay coverage.

### Later migrations do not each need a new PR

- After plastic bags passes verification, review and rehearse energy cans as a separate command run. Other supported mappings can follow independently.
- A mapping needs code changes only when its destination declarations, translations, brand configuration or required command behaviour are missing. Review those references as described above.
- Confirm meaning before execution: for example, whether a legacy size distinction should be retained. Do not turn an unreviewed inventory into an approved execution list.

### Work from the original branch that is not required for parity

- Global historical-CLO preparation, `is_selectable` and production `GenerateTagsSeeder` runs belonged to the earlier rollout plan. They are not prerequisites for individual migrations.
- Do not add the old CLO retirement schema or duplicate command alongside this command. Category-specific redirects require their own later design.
- The approved-plan runner and bulk rehearsal scripts are optional tooling if the number of approved runs warrants them. Keep the first run manual and explicit.
- Deprecated-column removal and broad resolver refactors remain separate architectural work. Neither is needed to rename plastic bags.
- A mounted-browser automation harness was not delivered by the original branch. It remains separate new infrastructure, not missing coverage that can simply be copied.

Brand configuration and translation updates for plastic bags belong in the current release, as listed above; they are not deferred by this follow-up sequence.

## Release evidence

Local test results below do not substitute for the fresh-copy rehearsal or CI on the final commit. CI currently runs PHP 8.3/MySQL 8.0/Redis 7, without Node, for pushes and PRs targeting `master` or `upgrade/tagging-2025`. Open a PR to a configured target so the exact release candidate receives CI.

Before production, attach the tested commit, approved arguments, fresh-copy timings and reconciliation results, remaining unresolved counts, and manual smoke-check results. Production execution remains a separate action. No browser or production rehearsal is implied by endpoint-backed tests.

### Local verification — 2026-09-14

- Tested the uncommitted working tree on `fix/tags/migrate-tag`, based on `ac688aa6`, using PHP 8.3.16, local MySQL `olm_test` and Redis database 2.
- Full PHPUnit suite: **1,388 tests, 6,997 assertions, zero failures, one skipped**. The existing `TypesCheckerTest::test_it_unlocks_per_type_achievement` skips because its fixture has no types; achievement changes remain follow-up work.
- `npm run build` passes, with the existing large-chunk warning. PHP syntax and `git diff --check` pass.
- Covered missing source CLOs, explicit destination creation, dry runs, stale object/CLO submissions, type rules, quick tags, observation/extras/timestamp preservation, school approval, ownerless/deleted photos, retry agreement, chains and actual wide/long CSV output.
- The interruption test commits 200 photos, fails the next batch, resumes the remaining photo and verifies replay and Redis rebuild without double-counting. A second MySQL connection verifies advisory-lock contention. This is exception-based interruption coverage, not a process-kill rehearsal.
- No mounted browser session, concurrent editor execution, final-commit CI or fresh production-copy rehearsal was run. Production and `olm_postmig_6` were not modified. Those deployment checks remain pending.
