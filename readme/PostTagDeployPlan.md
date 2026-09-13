# Post-tag deployment plan

**Draft for review — 13 September 2026. No production commands have been executed for this review.**

First window: migrate **`plasticBags` → `plastic_bag` only**. Other mappings remain separate decisions. This plan applies to the current working tree based on `0381a0e3`; record the final candidate commit before rehearsal and deployment.

## Seeder boundary

**GenerateTagsSeeder is for first-time local database setup only. Do not run it on production or on a production-copy migration rehearsal.** This is the deployment rule agreed with the owner; the seeder itself does not enforce an environment restriction.

- Production changes are prepared and reviewed one approved source object at a time.
- Do not run `composer seed:tags` or `CreateAllTagsSeeder` during this window either.
- The migration derives source categories from existing CLOs and observations. Missing source CLOs are accepted when the observations record their category.
- The dry run reports how many missing source redirects it would create, without writing anything.
- `--apply` creates those hidden source CLOs with their destination and optional type already recorded, inside the retirement transaction. No separate insert or preparation command is needed.
- The destination CLO must already exist, be active and selectable, and permit the requested type. Missing destination entries still require a separate approved change.
- Actual null source `category_id` values remain refused. Incomplete previous retirements remain refused because their original mapping cannot be reconstructed safely.

## What each step changes

| Step | Expected result |
| --- | --- |
| Deploy application and schema | Makes the new editor, resolution and historical-selection behaviour available. Schema migrations add fields; the actual pending list must be checked. |
| Plan the approved migration | Validates the destination and reports any missing source redirect records without writing data. |
| Run the plastic-bag migration | Creates any missing source redirects, moves observations and saved quick tags, and regenerates affected photos' summaries and metrics. |
| Run achievements seeder later | Inserts missing achievement definitions during a separate off-peak step. |

Example with a targeted migration:

- A photo has **2 plasticBags + 1 randomLitter**. The command validates the plastic-bag destination and plans its missing source redirect.
- After the first migration it has **2 plastic_bag + 1 randomLitter**.
- If randomLitter still has no matching CLO, replacing all tags on that photo can still return 422. Migrating plastic bags alone does not fix that mixed-photo editing problem.
- Existing observations whose historical CLO has been prepared remain resolvable. Those historical entries stay hidden from new-tag suggestions.

`photo_tags.category_id` and `litter_object_id` remain authoritative. The migration also fills missing deprecated `category_litter_object_id` values on existing destination observations; this does not change their category or object.

### The approximately 190,000 observations without a CLO

Treat this as an earlier estimate until measured against the production copy. Distinguish two cases:

- **The stored `photo_tags.category_litter_object_id` is null:** this alone is allowed. The application resolves the CLO from the recorded category and object.
- **No catalogue entry exists for that category and object:** this prevents resolution. The approved migration creates the missing source redirect and moves those observations to the existing destination.

For example, the plastic-bag migration records one hidden `other/plasticBags` redirect and moves the observations to `other/plastic_bag`. It does not prepare randomLitter or energy cans.

Measure genuinely missing entries before and after the migration with this read-only query:

```sql
SELECT COUNT(*) AS observations_without_matching_clo
FROM photo_tags AS pt
LEFT JOIN category_litter_object AS clo
    ON clo.category_id = pt.category_id
   AND clo.litter_object_id = pt.litter_object_id
WHERE pt.category_id IS NOT NULL
  AND pt.litter_object_id IS NOT NULL
  AND clo.id IS NULL;
```

Record this count before and after the targeted migration. Only the approved source combinations should leave this count; other missing combinations remain errors, not historical entries. Standalone extras without an object are outside this category/object check. The earlier zero-global-errors release gate cannot be met by a plastic-bag-only migration: review the remaining editing impact explicitly before approving a production window.

There is no bulk backfill of approximately 190,000 stored CLO IDs in this plan. Migration commands record source redirects and change observations to their approved replacements one source object at a time.

## Evidence required before scheduling

Record these in the release PR; blank fields mean the window is not cleared to start.

| Item | Evidence to record |
| --- | --- |
| Candidate | Final commit SHA, clean release tree, deployed asset version |
| CI | Passing suite and build for that candidate; PHP 8.3, MySQL 8.0 and Node 22 workflow |
| Rehearsal | Fresh production dump reference, disposable database identity, runtime versions, measured preparation and migration durations, outputs and comparisons |
| Catalogue review | Planned source redirects, existing destination/type checks, preservation evidence and remaining unresolved combinations |
| Production identity | Application environment; resolved MySQL host/database; Redis host, database and prefix; all application and worker nodes |
| Recovery | Backup reference, verified restoration procedure, Redis recovery approach and responsible operator |
| Deployment | Actual Forge command order, maintenance mechanism, worker drain/stop/restart commands, long-lived process reloads |
| First mapping | Exact arguments: `plasticBags plastic_bag --apply`; no category or type change |
| User flows | Manual results for web, admin, facilitator and team-modal editors; supported mobile build handling unknown collection status |

The configured CI branches are `master` and `upgrade/tagging-2025`; use a PR targeting a configured branch. Local passing tests do not substitute for the candidate's CI result.

### Rehearsal scope

- Preserve `olm_postmig_6` read-only. Use a fresh disposable production copy named `olm_rehearsal_*`.
- Existing rehearsal scripts require Redis database 3 and cache database 4. Confirm these are exclusively assigned to the rehearsal before using them; do not clear existing data to satisfy a script.
- Capture the pre-migration catalogue and observation baseline. Prove the dry run writes nothing; verify the approved source redirects and observation changes after apply.
- Review all pending schema migrations, including their locking and timing, before applying them to the copy.
- Exercise apply, interruption/resume, replay, CSV export and mixed historical editing using the exact candidate.
- The existing [rehearsal script](audit/rehearse-first-tag.php) still runs GenerateTagsSeeder and assumes global preparation. Do not use it for this revised workflow until it has been adapted and tested. Capture targeted migration comparisons separately; treat unavailable checksums as missing evidence.
- The [approved-mapping runner](audit/run-approved-tag-migrations.php) is restricted to disposable databases and currently derives reviewed categories only from existing source CLOs. It needs adapting before use with missing source CLOs. Use the Artisan command directly for this workflow; retain the runner's guards.
- Endpoint-backed tests are not browser tests. Record actual manual sessions separately. Mounted-browser automation remains deferred; disclose whether concurrent HTTP replacements were actually reproduced.

## First production window

Execute each stage only after the previous stage passes. Run commands from the deployed release directory using verified production configuration. Do not copy local database or Redis settings into production.

### 1. Freeze and deploy

- Establish the write freeze before merging if a merge automatically deploys.
- Block writes on every node, drain in-flight requests and jobs, and stop workers and scheduled writers. Ensure supervisors cannot restart them during the window. `horizon:terminate` alone does not establish this state.
- Record the backup and actual database identities. Confirm the recovery procedure is available.
- Deploy the candidate with traffic still paused. Inspect `php artisan migrate:status` and approve the actual pending migration list.
- Apply the reviewed schema migrations:

```bash
php artisan migrate --force
```

- Complete the deployment's configuration and asset steps. Keep new application code from serving traffic against incomplete schema or catalogue preparation.

### 2. Record the baseline

```bash
php artisan olm:verify-tag-integrity
```

- Capture source rows, quantities, categories, extras, summaries, XP, metrics and quick tags before migration.
- Missing source CLO errors are expected. Do not create them manually or run a catalogue seeder.
- Record unrelated missing combinations separately. The global verifier remains non-zero while those combinations are unresolved; do not suppress new errors or add `--fix` to bypass validation.
- Any production decision must explicitly address those remaining observations and their editing impact.

### 3. Review the plastic-bag dry run

```bash
php artisan olm:migrate-tag plasticBags plastic_bag
```

- Confirm the source is `plasticBags` and the destination is `plastic_bag`.
- Check source rows, quantities, categories, quick tags and destination entries against the recorded production baseline.
- Confirm every recorded source category has an active selectable destination. Review each source CLO redirect that the dry run proposes to create.
- No `--type` or `--category` is approved for this run. The command migrates the whole source object; unexpected additional source categories require review.
- Require successful validation. If it reports an existing retirement or an already-applied mapping, reconcile that state before proceeding.

### 4. Apply only plastic bags

```bash
php artisan olm:migrate-tag plasticBags plastic_bag --apply
```

- The command creates any missing source CLOs as hidden redirects and records retirement and quick-tag changes in one transaction before processing photo batches.
- It updates photos in batches of 200, including soft-deleted photos. It recalculates summaries and updates metrics for processed, non-deleted photos.
- The intended plastic-bag object XP is unchanged. Full-photo recalculation can expose other pre-existing differences, so compare actual photo/user XP and metrics rather than assuming parity.
- Keep writes frozen until verification completes.

### 5. Verify before reopening

```bash
php artisan olm:verify-tag-integrity
php artisan olm:migrate-tag plasticBags plastic_bag
```

- Require a completed-mapping result on replay, no unresolved plastic-bag mapping errors and no unexplained new integrity errors. Retain the separate report of pre-existing unresolved combinations.
- Confirm no observations or quick tags remain assigned to the old object/entries.
- Reconcile destination row and quantity increases with the recorded source totals.
- Verify observation IDs, categories, types, quantities, collection states and extras were preserved. Explain only the approved object/CLO changes.
- Verify quick-tag quantities and other properties; confirm redirects resolve late submissions using old IDs or keys.
- Compare summaries, photo/user XP and MySQL metrics with the rehearsed expectations.
- Verify Redis object-count decreases and increases against the affected processed photos. A future Redis rebuild does not replace checking whether this run completed consistently.
- Execute a CSV export and check its replacement key and preserved quantities/extras.
- Using controlled access while public writes remain frozen, check plastic-bag editing and mixed photos through the supported editors. Distinguish prepared historical entries from unresolved combinations that still reject replacement. Record any deliberate test edit separately from migration comparisons.
- Record remaining historical counts and missing-CLO counts separately. Other source objects remain unprepared unless separately approved.

Restart workers and resume traffic only after these checks pass. Reload relevant long-lived application processes so they discard cached CLO lookups. Confirm all nodes use the candidate and refreshed catalogue state, then monitor save failures and worker errors.

## Interruption and recovery

- Keep writes paused after any non-zero exit, connection failure or failed comparison.
- Inspect retirement records, remaining source rows, quick tags and MySQL/Redis state before retrying.
- Resume an interrupted migration with the **same approved arguments** after the cause is resolved. The whole migration is not one transaction; earlier batches may already be committed.
- Redis updates are outside the MySQL transaction. Reconcile or rebuild affected Redis data when required; a successful MySQL retry alone does not prove Redis parity.
- Do not reverse the arguments as a rollback. Do not assume reverting application code or rolling back schema undoes migrated data.
- If restoration is necessary, use the reviewed backup procedure while writes remain frozen and restore/rebuild related state consistently.

## Later work

- Run the corrected `AchievementsSeeder` separately off-peak after verifying its candidate version and repeat-run preservation of definitions, timestamps and earned achievements.
- Review and approve later mappings individually in the [mapping register](audit/TagCleanupReview-2026-09-12.csv). Measure their own rehearsals; plastic-bag timing is not an estimate for all mappings.
- Retain the full write freeze for the first window. Any lighter procedure for later windows needs evidence from this run and a separate decision.
- Do not run `olm:v5` or alter/rerun the historical conversion services.

## Review status

This document is based on inspection of [GenerateTagsSeeder](../database/seeds/Tags/GenerateTagsSeeder.php), its [wrapper](../database/seeds/Tags/CreateAllTagsSeeder.php), the [migration command](../app/Console/Commands/tmp/v5/PostMigAug2026/MigrateTag.php) and the [cleanup runbook](PostTagMigrationClean.md).

This review did not query production, execute the seeder, perform a fresh rehearsal, verify Forge configuration or run browser/mobile sessions. Those evidence fields remain open. The exact candidate still needs a fresh production-copy rehearsal; the older rehearsal helpers have not been adapted to this workflow. This document does not certify the production window as ready or risk-free.
