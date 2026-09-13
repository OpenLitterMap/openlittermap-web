# Post-Migration Tag Cleanup

`olm:migrate-tag plasticBags plastic_bag` is the first approved migration. Later mappings run separately.
Deploying the application does not execute any mapping. Do not rerun `olm:v5` or edit its historical conversion services.

## Preparation and staged editing

`GenerateTagsSeeder` is reserved for first-time local database setup. Do not run it, `CreateAllTagsSeeder` or `composer seed:tags` on production or on a production-copy migration rehearsal. The seeder does not enforce this environment restriction itself.

`TagsConfig` declares current and historical category/object combinations. For each approved migration, `--apply` creates any missing source CLOs as hidden redirects inside the retirement transaction, with `is_selectable=false`. Dry runs report these planned records without writing them. Preserve existing redirects and retired objects. CSV files record review and execution; they do not define taxonomy. See [the deployment plan](PostTagDeployPlan.md) for the revised targeted workflow.

An observation resolves once its recorded category/object has a CLO. Historical entries are hidden from discovery and excluded from object-only category inference, while explicit existing submissions remain accepted. For plastic bags, the command creates the missing `other/plasticBags` redirect while migrating into the existing `other/plastic_bag` entry. No manual source insert is needed.

Migrating plastic bags alone does not make every mixed photo editable: a photo also containing an unresolved randomLitter observation can still fail replacement with 422. Remaining missing combinations and their editing impact must be reported separately from prepared historical entries.

`photo_tags.category_id` and `litter_object_id` are authoritative. The deprecated `category_litter_object_id` may remain null. This command retains its existing destination-pointer backfill; exports and serializers resolve the authoritative IDs.

## Mapping review

[The review register](audit/TagCleanupReview-2026-09-12.csv) covers 74 combinations across 71 source objects, with observed counts, proposed destinations, category/type changes, XP effects and approval state. These counts describe the inspected local baseline, not a fresh production measurement.

- Four combinations are already declared as current and need no migration.
- Only plasticBags → plastic_bag is approved for the first window.
- Pending and disputed mappings are not executable. Information-losing proposals require a decision or a more precise destination.
- Both categories for balloons, brokenglass and straws must be reviewed together. One whole-object command sweeps all its categories; 74 combinations do not imply 74 executions.
- XP effects shown in the register concern object XP; quantity, extras and collection status must also be checked during rehearsal.

## Command guarantees

- Default execution is a read-only dry run; `--apply` writes.
- Missing source CLOs are accepted when observations record their category. The command creates their redirects during retirement. Null source categories and unverifiable old retirements are refused.
- Every destination must be declared, active and selectable. An optional `--type` must be approved for it.
- `--category` explicitly changes category; otherwise each category is retained.
- The retirement transaction records destination CLO and optional type before photo batches begin. Late requests and quick tags follow these redirects, including chains.
- Conflicting retries are refused. Matching completed runs are no-ops; interrupted batches can resume.
- Photos are processed in atomic batches of 200. Summaries, XP and processed-photo metrics are recalculated.
- Redis is not part of the MySQL transaction. Inspect both after any interruption.

## Disposable rehearsal

Keep `olm_postmig_6` read-only. Copy it into a dedicated `olm_rehearsal_*` database. Use Redis database 3 and cache database 4 for rehearsal; never share production or PHPUnit Redis. Confirm those databases are dedicated before using them.

Run the exact candidate commit with production-compatible PHP and MySQL. Review and apply pending schema migrations, then record the integrity baseline:

```bash
php artisan olm:verify-tag-integrity
```

Rehearse the dry run, apply and verification in [the deployment plan](PostTagDeployPlan.md). Verify the dry run changes nothing, then compare full observation data and redirects after application; counts alone cannot establish preservation.

The global verifier will continue to report unrelated missing combinations. Require the approved source to resolve and the migration dry run to pass, with no unexplained new errors after application. Keep all outstanding errors and historical counts in the report. This changes the earlier global-preparation assumption; the remaining editing impact needs explicit review before production. `--photo-id` omits account-wide quick-tag and cycle checks. Do not run `--fix` without reviewing its changes.

`rehearse-first-tag.php` still runs GenerateTagsSeeder and assumes global preparation. Do not use it for this workflow until it is adapted and tested.

`run-approved-tag-migrations.php` currently derives source categories only from existing CLOs. It also needs adapting for missing-source migrations. Use `php artisan olm:migrate-tag` directly on the verified disposable database; retain the helpers' environment guards.

Measure each approved mapping separately. Capture source rows, extras, quick tags, summaries, photo/user XP, MySQL metrics, Redis changes, replay and CSV exports. Test editing a mixed historical photo after the first mapping. Rehearse interruption/resume. Never extrapolate all timings from plastic bags.

## First production window

1. Record the candidate commit, approved arguments, database host/name, backup reference and verified restore procedure. Check Forge deploy ordering before starting.
2. Freeze writes on every node, drain in-flight requests and stop/drain workers. Confirm no active jobs or supervisors can restart them. `horizon:terminate` alone is insufficient.
3. Deploy the candidate while writes remain frozen; apply all pending schema migrations before serving new code.
4. Capture the pre-migration observations, catalogue and integrity baseline. Do not run catalogue seeders or manually insert source CLOs.
5. Review the dry run, including planned source redirects and the existing destination. Report other unresolved combinations and their editing impact.
6. Dry-run and apply only `olm:migrate-tag plasticBags plastic_bag --apply` after checking dry-run counts and examples.
7. Verify source/extra/quick-tag preservation, summaries, XP, MySQL metrics, Redis deltas, replay, CSV exports and mixed historical editing. Require no unexplained new errors; explain remaining missing combinations and historical counts separately.
8. Resume traffic and workers only after verification. Run `AchievementsSeeder` separately off-peak to insert missing definitions; existing achievement timestamps and earned achievements remain unchanged.

An interruption keeps writes paused until inspected and resumed. Keep the full freeze for this first window. Reduced restrictions for later windows need evidence and a separate decision.

## Additional editor corrections

Approved school-photo edits retain verification and approval, and refresh the owner’s metrics. The Photos tab resets the facilitator queue’s pending filter. Typed dumping observations now use the existing small/medium/large XP weights during summary generation; this corrects XP when those photos are edited or regenerated. The inspected baseline contains 35 such observations, none on plastic-bag migration photos.

## Release coverage

Required: endpoint → pure editor adapter → save endpoint → database regression tests, migration/retry tests, catalogue and achievement idempotency, scoped repairs, sequential replacements, full PHPUnit and frontend build. CI uses PHP 8.3, MySQL 8.0, Redis 7 and Node 22. Open the release PR against `master` or `upgrade/tagging-2025`; these are the configured branch triggers.

Mounted-browser automation is deferred infrastructure. Record focused manual checks of all four editors separately; endpoint tests are not browser tests. Confirm the supported mobile build displays null collection status as unknown before shipping. Report whether real concurrent HTTP replacements were reproduced, rather than inferring that from row-lock code alone.

Tests must clear only their configured Redis database with `flushdb()`. Never use `flushall()`; it also deletes local application and rehearsal caches.

Record release verification in the PR: tested commit (or explicitly identified uncommitted patch), approved mappings, exact checks, historical counts and deferred coverage. Passing local tests is not a claim of zero production risk.
