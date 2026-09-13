# Tag cleanup readiness — 2026-09-12

## Verdict

The application changes, permanent regression tests, mapping review and disposable rehearsal tooling are implemented. Local verification passed. **Production execution remains gated** on the committed candidate passing MySQL 8 CI, the supported mobile check, and the deployment/rehearsal checks below.

This is evidence for an **uncommitted working tree**, based on `5c687591f768166a73dddafeb408f2d9fe588164` on `bugs/2026/08/plastic-bag-count`. It is not evidence that a new committed release candidate has passed CI. Nothing was committed or deployed.

The protected `olm_postmig_6` database was only read and dumped. Its schema and data were not migrated. Application writes, browser checks and migration rehearsals used disposable databases; PHPUnit used `olm_test`.

## Implemented behaviour

- Historical category/object combinations are declared in `TagsConfig` and seeded as non-selectable CLOs. Existing observations and saved quick tags remain resolvable while all five discovery paths hide historical choices.
- Object-only inference ignores historical categories. Explicit categories survive replacement and invalid replacements fail atomically.
- Migrations require source CLOs and selectable destinations, record redirects before batches, and reject conflicting or unverifiable retries.
- Integrity output separates actual errors from historical photo-tag and quick-tag counts. Scoped repairs constrain the entire mismatch condition.
- One pure ESM adapter serves web, admin, facilitator and team-modal editors. It preserves observation boundaries, category, object, type, independent brand quantities, other extras and nullable collection status.
- Separate custom cards retain their own quantity/status and XP. Existing combined custom observations remain combined.
- The team modal uses the tagging search and active-tag controls. Approved photos remain accessible and keep their approval when edited; their owner’s metrics are refreshed.
- Admin and team replacements lock the photo row before reading/replacing observations.
- Achievement seeding inserts missing definitions without rewriting existing definitions, timestamps or earned achievements.
- CI now installs Node dependencies and builds the frontend before running the JavaScript-backed PHP tests.

Manual checks also found and fixed the inherited pending filter in Team Photos and the approved-school-photo edit/metrics defect. Typed dumping summaries now apply the existing small/medium/large XP weights consistently with the editor. The baseline has 35 typed dumping observations; none occur on the plastic-bag migration photos. Editing or regenerating those dumping photos can correct their previous XP.

Historical conversion files (`MigrationScript`, `ClassifyTagsService`, `UpdateTagsService`) were not changed or rerun. The deprecated photo-tag CLO column remains nullable; category and object IDs remain authoritative.

## Mapping approval and baseline

[The review register](TagCleanupReview-2026-09-12.csv) contains 74 combinations across 71 source objects:

| State | Combinations | Execution |
| --- | ---: | --- |
| Keep current declaration | 4 | No migration |
| Approved | 1 | Plastic bags only |
| Pending review | 52 | Not executable |
| Disputed | 17 | Not executable |

The only approved arguments are `plasticBags plastic_bag`, without category/type changes or permission to change XP. The structured runner groups whole-object category plans, refuses incomplete/conflicting approval and calls Artisan without shell evaluation. Its approved replay and refusal of unapproved `randomLitter` were exercised.

These counts come from the inspected local baseline, not a freshly imported production snapshot:

| Measurement | Count |
| --- | ---: |
| All baseline photo-tag rows | 617,044 |
| All baseline item quantity | 837,278 |
| Missing-CLO rows before preparation | 189,518 |
| Photos containing those rows | 171,149 |
| Historical rows after preparation | 186,904 |
| Historical rows after plastic bags | 176,853 |
| Historical quick tags after plastic bags | 0 |
| Integrity errors after preparation and after migration | 0 |

Preparation resolves four current combinations as well as adding 70 historical declarations. It does not run the pending mappings. Remaining historical observations are expected and stay editable.

## Automated checks

- Latest independently observed full suite after cleanup: **1,501 tests, 7,471 assertions, one skipped**, in 6 minutes 6 seconds. The earlier retained run had 1,500 tests and 7,513 assertions.
- The existing skipped `TypesCheckerTest::test_it_unlocks_per_type_achievement` requires a type fixture that is absent from its test database. It is not counted as passing coverage.
- Clean `npm ci` followed by `npm run build` passed. The build retains the existing large-chunk warning.
- Permanent tests cover the four real serializer/save endpoint contracts through the Node-imported ESM adapter, historical discovery/resolution, mixed extras, brand quantities, collection states, duplicate-CLO observations, custom-observation XP, type XP, atomic rejection, sequential replacements, catalogue/achievement repeat seeding, scoped repair and migration/retry cases.
- A real second PDO connection hit MySQL lock-wait timeout 1205 while the first held the photo row lock. Sequential endpoint replacements also passed. Simultaneous HTTP replacements were not reproduced.
- `git diff --check` and PHP syntax checks passed.

Local runtimes were PHP 8.3.16, Node 22.22.1 and **MySQL 9.2.0**. Local MySQL is not the production-compatible MySQL 8 gate. CI is configured for PHP 8.3, MySQL 8.0, Redis 7 and Node 22; it runs for pushes and PR targets `master` and `upgrade/tagging-2025`.

## Disposable migration evidence

`olm_rehearsal_readiness_20260912` and `olm_rehearsal_final_20260912` were copied from the protected baseline. The first was subsequently used for synthetic editor smoke checks. The final copy was retained for migration verification.

- Preparation and repeated preparation preserved checksums for observations, extras, summaries, quick tags, photo/user XP, metrics and achievements.
- Plastic bags moved **10,051 observations / 12,946 items**. The destination went from 253 rows / 264 items to 10,304 rows / 13,210 items.
- An uninterrupted first apply took **49.777 seconds** on this machine. Do not use this to predict another mapping or production timing.
- An actual SIGKILL stopped a later run after 400 committed rows, leaving 9,651 source rows. The final isolated resume took **68.868 seconds** and passed preservation, integrity and replay checks.
- The interruption repeat restored only the bag mapping on the disposable copy. It retained the empty metric buckets created by the first run; it was not another pristine database import.
- Every observation ID, photo association, quantity and collection status, every extra, all photo/user XP and all nonzero MySQL metric values were preserved. A baseline comparison also found zero unexpected category/object/type changes across all 617,044 observations.
- All **10,051 affected photo summaries and processed-tag snapshots** matched the baseline after accounting for the expected object/key change and regenerated CLO IDs. Observation ordering was ignored in this comparison.
- Quick tags were preserved/redirected and completed replay changed no data. Permanent tests cover synthetic quick tags as well as the baseline check.
- The first run created **433 additional all-zero metric buckets**. Existing metric values did not change and existing rows were not deleted. These empty buckets are reported separately from metric-value preservation.
- Wide and long CSV exports actually executed: **9,366,337 bytes** and **2,695,763 bytes**. Files remain in restricted temporary export directories; no exports were emailed or published.

Redis was checked from empty dedicated rehearsal databases. **5,492 comparisons** across 1,160 location scopes and 426 users passed: source −12,946 and destination +12,946, including object rankings. These are migration deltas, not a populated production-cache parity check. The same comparisons still passed after the final PHPUnit suite.

### Local Redis incident

During an earlier full test run, seven pre-existing `Redis::flushall()` calls cleared all local Redis databases, including application caches and the rehearsal data. MySQL was unaffected. The unscoped calls were removed or replaced; cleanup now uses the configured test Redis database, including the shared TestCase cleanup. The available evidence does not identify which earlier process triggered the clearing; Claude reports it was likely an earlier run on the base commit. Their affected tests passed, the isolated Redis rehearsal was repeated, and the final full suite preserved its results. **Local application Redis caches need rebuilding.**

## Manual browser checks

These were actual manual in-app-browser interactions against a local server using synthetic accounts/photos in the disposable rehearsal database. They are separate from the endpoint-backed automated tests. The server and browser were stopped afterward.

- **Web:** edited plastic-bag quantity beside historical `randomLitter`; saved and reloaded brand quantity, material/custom extras and unknown/false collection states.
- **Admin:** edited the same mixed photo, saved and approved it; confirmed the queue cleared and the saved values remained.
- **Facilitator:** edited and approved a school photo with historical and current tags plus extras.
- **Team modal:** navigated from the review queue to approved photos, opened the repaired dark tagging panel, edited quantity, searched/added dumping, selected Small, saved and reopened. Approval stayed intact and stored summary XP matched the editor; processed XP included the separate upload award.

No mounted-browser automation harness was added. The supported mobile repository/build was not available here, so null collection-status rendering was not verified on mobile.

## Cleanup review and release recommendation — 2026-09-13

Stop discretionary refactoring and freeze a release candidate. No known unresolved code blocker remained after this review; the production gates below still apply.

- Independently repeated the full suite and frontend build after Claude’s cleanup; both passed with the totals above.
- Executed the simplified rehearsal script with `--verify` on the completed disposable copy: preservation, no-op replay, row-lock timeout and both CSV exports passed. Executed the approved-plan runner in dry-run mode. The revised Redis verifier passed all 5,492 comparisons, including after the suite and browser checks. This was verification of an existing rehearsal, not a fresh migration apply after cleanup.
- Found one cleanup regression in TeamPhotoList: filtering by a nonexistent tag, switching to Overview, and returning to Photos cleared the controls but left the empty filtered results. Restored the refresh on every Photos mount; the same browser sequence then restored approved photo 550966.
- Opened the refactored team modal, checked saved historical/current tags and extras, searched Bottle, added Alcohol/Bottle and selected Beer. Cancelled the temporary edit. These focused checks do not replace the earlier four-editor save checks or establish a new browser automation harness.
- Corrected historical key naming and category-inference documentation, and moved the migration-agreement comment back to its method.
- The final browser tab and local server were closed. No production action was taken and the protected MySQL baseline remained read-only.

The cleanup-run temporary logs were no longer available when this report was finalised after the interruption. The counts and results above were observed directly in the preceding tool responses; the earlier evidence bundle remains a separate, older record. Obtain durable CI output for the frozen commit rather than treating a source manifest as proof of execution.

## Remaining production gates

1. Commit/review the candidate and obtain green CI through a PR targeting a configured branch, including MySQL 8. Any subsequent code change needs appropriate verification.
2. Repeat the first-window rehearsal from a fresh production snapshot using that exact commit and production-compatible runtimes. Record the new counts and measure the actual approved command.
3. Confirm the supported mobile build renders null collection status as unknown.
4. Verify Forge deployment order, explicit database identities, backups/restore, every web node’s write freeze, in-flight requests and drained/stopped workers. `horizon:terminate` alone is insufficient.
5. Prepare the catalogue before recording the integrity baseline. Apply only plastic bags, then verify the recorded data/metrics/Redis checks and mixed historical editing before resuming traffic.
6. Run the corrected achievements seeder separately off-peak. Review and rehearse each later whole-object mapping before approving its argument fields.

An interruption keeps writes paused until inspected and resumed. Use [the deployment runbook](../PostTagMigrationClean.md); no production action was taken during this implementation.

## Evidence identity

The local evidence bundle is at:

`/Users/seanlynch/.codex/visualizations/2026/09/08/01a082aa-bbd1-7d32-b80b-631045806786/production-readiness-2026-09-12/`

The original bundle contains the earlier PHPUnit/JUnit and build logs, interruption/resume results, summary comparison, Redis verification, runner approval/refusal results and the original manifest. That original manifest predates Claude’s cleanup and must not be used to identify the current candidate.

Original manifest SHA-256: `543eba9e9fd33a9f46c71c20a6089231f65a966a6334ca1a38bd8a6376b5dd89`.

The database dump and CSV contents are not copied into the evidence bundle. This report does not claim the branch is 100% bug-free or authorize production execution.

Current source manifest: `/Users/seanlynch/.codex/visualizations/2026/09/08/01a082aa-bbd1-7d32-b80b-631045806786/production-readiness-2026-09-12/cleanup-review-2026-09-13/current-source-manifest.json` (51 files; excludes this report). SHA-256: `d7a9000c90e1c32e67ff388e758b1a46408511be6257377a23a174f3f9c82021`. This identifies the uncommitted source, not a CI-tested commit.
