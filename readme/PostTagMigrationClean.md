# Post-Migration Tag Cleanup

`olm:migrate-tag plasticBags plastic_bag` is the first approved migration. Later mappings run separately.
Deploying the application does not execute any mapping. Do not rerun `olm:v5` or edit its historical conversion services.

## Preparation and staged editing

`TagsConfig` declares current and historical category/object combinations. `GenerateTagsSeeder` creates historical CLOs with `is_selectable=false`, without changing observations, extras, summaries, quick tags or XP. It preserves redirects and never restores retired objects. CSV files record review and execution; they do not define taxonomy.

Historical observations remain editable using their recorded category and object or CLO ID. They are absent from object suggestions, object category lists, CLO lists, search and generated top tags. Saved quick tags and exports still resolve them. Object-only category inference counts only active selectable CLOs. For example, historical sanitary/gloves does not prevent ordinary gloves submissions resolving to medical/gloves.

After preparation, a photo containing plasticBags and randomLitter can be edited before and after the plastic-bag migration. Changing the plastic-bag quantity does not require migrating randomLitter. Unknown category/object combinations still return 422 atomically; the API never substitutes a category.

`photo_tags.category_id` and `litter_object_id` are authoritative. The deprecated `category_litter_object_id` may remain null. This command retains its existing destination-pointer backfill; exports and serializers resolve the authoritative IDs.

## Mapping review

[The review register](audit/TagCleanupReview-2026-09-12.csv) covers 74 combinations across 71 source objects, with observed counts, proposed destinations, category/type changes, XP effects and approval state. These counts describe the inspected local baseline, not a fresh production measurement.

- Four combinations are already declared as current and need no migration.
- Only plasticBags → plastic_bag is approved for the first window.
- Pending and disputed mappings are not executable. Information-losing proposals require a decision or a more precise destination.
- Both categories for balloons, brokenglass and straws must be reviewed together. One whole-object command sweeps all its categories; 74 combinations do not imply 74 executions.
- XP effects shown in the register concern object XP; quantity, extras and collection status must also be checked during rehearsal.

`TagPairMigrationManifest-2026-08.csv` and `TagRetirements-2026-08.csv` remain archived audit records. The old voided reverse-direction rehearsal is not an execution instruction.

## Command guarantees

- Default execution is a read-only dry run; `--apply` writes.
- Every source category must have a CLO before retirement. Missing source CLOs or unverifiable old retirements are refused.
- Every destination must be declared, active and selectable. An optional `--type` must be approved for it.
- `--category` explicitly changes category; otherwise each category is retained.
- The retirement transaction records destination CLO and optional type before photo batches begin. Late requests and quick tags follow these redirects, including chains.
- Conflicting retries are refused. Matching completed runs are no-ops; interrupted batches can resume.
- Photos are processed in atomic batches of 200. Summaries, XP and processed-photo metrics are recalculated.
- Redis is not part of the MySQL transaction. Inspect both after any interruption.

## Disposable rehearsal

Keep `olm_postmig_6` read-only. Copy it into a dedicated `olm_rehearsal_*` database. Use Redis database 3 and cache database 4 for rehearsal; never share production or PHPUnit Redis. Confirm those databases are dedicated before using them.

Run the exact candidate commit with production-compatible PHP and MySQL. Apply schema migrations, then run only:

```bash
php artisan db:seed --class='Database\Seeders\Tags\GenerateTagsSeeder' --force
php artisan olm:verify-tag-integrity
```

Record the post-preparation integrity baseline: **zero errors**, plus historical photo-tag and quick-tag counts. Historical counts are informational. A non-zero error exit is a hold, even when unrelated to plastic bags. `--photo-id` reports only that photo and omits account-wide quick-tag counts. Do not run `--fix` without reviewing the defects it proposes to change.

`rehearse-first-tag.php` prepares and checks the plastic-bag window on a disposable copy, saving a checkpoint under the system temporary directory. Use `--resume` after interruption, or `--verify` to repeat its post-run checks. Keep the checkpoint with the rehearsal evidence. It refuses the protected baseline.

The rehearsal runner reads structured fields and calls Artisan without shell evaluation. It refuses non-disposable databases, incomplete approval, omitted source categories and conflicting whole-object plans. Set database and Redis environment variables explicitly:

```bash
DB_DATABASE=olm_rehearsal_NAME REDIS_DB=3 REDIS_CACHE_DB=4 php readme/audit/run-approved-tag-migrations.php --database=olm_rehearsal_NAME --source=plasticBags
DB_DATABASE=olm_rehearsal_NAME REDIS_DB=3 REDIS_CACHE_DB=4 php readme/audit/run-approved-tag-migrations.php --database=olm_rehearsal_NAME --source=plasticBags --apply
```

Measure each approved mapping separately. Capture source rows, extras, quick tags, summaries, photo/user XP, MySQL metrics, Redis changes, replay and CSV exports. Test editing a mixed historical photo after the first mapping. Rehearse interruption/resume. Never extrapolate all timings from plastic bags.

## First production window

1. Record the candidate commit, approved arguments, database host/name, backup reference and verified restore procedure. Check Forge deploy ordering before starting.
2. Freeze writes on every node, drain in-flight requests and stop/drain workers. Confirm no active jobs or supervisors can restart them. `horizon:terminate` alone is insufficient.
3. Deploy the candidate while writes remain frozen; apply all pending schema migrations before serving new code.
4. Run `GenerateTagsSeeder` directly. **Never use `composer seed:tags` during this window**: it also runs achievements.
5. Record the post-preparation integrity and historical-count baseline; require zero integrity errors.
6. Dry-run and apply only `olm:migrate-tag plasticBags plastic_bag --apply` after checking dry-run counts and examples.
7. Verify source/extra/quick-tag preservation, summaries, XP, MySQL metrics, Redis deltas, replay, CSV exports and mixed historical editing. Require zero integrity errors and explain remaining historical counts.
8. Resume traffic and workers only after verification. Run `AchievementsSeeder` separately off-peak to insert missing definitions; existing achievement timestamps and earned achievements remain unchanged.

An interruption keeps writes paused until inspected and resumed. Keep the full freeze for this first window. Reduced restrictions for later windows need evidence and a separate decision.

## Additional editor corrections

Approved school-photo edits retain verification and approval, and refresh the owner’s metrics. The Photos tab resets the facilitator queue’s pending filter. Typed dumping observations now use the existing small/medium/large XP weights during summary generation; this corrects XP when those photos are edited or regenerated. The inspected baseline contains 35 such observations, none on plastic-bag migration photos.

## Release coverage

Required: endpoint → pure editor adapter → save endpoint → database regression tests, migration/retry tests, catalogue and achievement idempotency, scoped repairs, sequential replacements, full PHPUnit and frontend build. CI uses PHP 8.3, MySQL 8.0, Redis 7 and Node 22. Open the release PR against `master` or `upgrade/tagging-2025`; these are the configured branch triggers.

Mounted-browser automation is deferred infrastructure. Record focused manual checks of all four editors separately; endpoint tests are not browser tests. Confirm the supported mobile build displays null collection status as unknown before shipping. Report whether real concurrent HTTP replacements were reproduced, rather than inferring that from row-lock code alone.

Tests must clear only their configured Redis database with `flushdb()`. Never use `flushall()`; it also deletes local application and rehearsal caches.

The final report must name the tested commit (or explicitly identify an uncommitted patch), approved mappings, exact checks, historical counts and deferred coverage. Passing local tests is not a claim of zero production risk.
