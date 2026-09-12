# Post-Migration Tag Cleanup

`olm:migrate-tag` retires one litter object and moves its data to an approved replacement.

## Mapping list

The command receives the two approved keys (plus `--type` / `--category` where the approval says
so) directly and never chooses or reverses a mapping. Survivors use snake_case singular keys
declared in `TagsConfig`; shadow keys such as `plasticBags` and `randomLitter` are the retired side
(`plasticBags → plastic_bag`, `randomLitter → random_litter`).

**Before the production run, the approved list must be committed** to
`readme/audit/TagRetirements-2026-08.csv`, one row per mapping with its type and category options,
in the order it will be applied. That file currently holds a single voided rehearsal row; the
approvals so far live in the tag-pairings register. `readme/audit/TagPairMigrationManifest-2026-08.csv`
is the audit inventory (every pairing, `PROPOSED`/`PENDING`), not the approved list.

Explicitly excluded from production although rehearsed on a clone, because the mapping is
semantically wrong: `automobile → car_part`, `menstrual → sanitary_pad`, `crisp_small → crisp_packet`.

## What the command does

For one entry, the command:

1. Sets `retired_at` on Tag A and points `merged_into_id` to Tag B (skipped for a pure
   category move, where the object stays live).
2. Requires Tag B's pairing to already exist in every category Tag A's rows sit in (or in the
   `--category` target). Taxonomy is declared in `TagsConfig` and created by the seeder; the run
   never invents a pivot and aborts before any change when one is missing. Rows on an unsanctioned
   source pairing therefore need a decision first: declare the pairing, or move them with `--category`.
3. Records the full approved mapping on each of Tag A's source pivots:
   `category_litter_object.merged_into_clo_id` (the survivor pairing) and `merged_into_type_id`
   (the approved subtype, when `--type` is given). This is what stale clients and saved quick tags
   resolve from, so a v4 composite key keeps its subtype and an object+category move lands in the
   right category. A marked pivot is excluded from the picker by `CategoryObject::active()`.
   Recorded mappings are immutable: a tombstone left by an earlier, different mapping is left
   alone (its chain continues through the survivor it recorded), a tombstone from the same mapping
   is resumed, and a retry that disagrees on category or type aborts. A tombstone from an earlier
   mapping that still has rows or quick tags on it aborts the run too ("re-run that mapping
   first"): the object is not retired until the earlier mapping has finished. The survivor pairing
   must be active, not merely present — a pairing an earlier category move already retired is
   refused. A replay of a mapping that finished exactly as requested exits 0 with "Already
   applied", even after its survivor has itself retired.
4. Backfills `photo_tags.category_litter_object_id` on rows already sitting on Tag B with a null CLO.
5. Repoints `photo_tags` and `user_quick_tags` from A to B, carrying the approved type onto quick
   tags when `--type` is given.
6. Regenerates affected photo summaries.
7. Reprocesses metrics for affected live, previously processed photos.

The mapping lives on the **pivot**, not the object, because a retirement can span categories with
a different survivor in each (`straws` → `softdrinks/straw` and `marine/straw`).
`litter_objects.merged_into_id` alone names only the survivor object and cannot express that.

Step 3 exists because the v5 migration wrote rows straight onto shadow objects before any pivot
existed for them. Those rows never reference Tag A, so the per-photo loop cannot reach them.
Surfaces that derive the pivot from `(category_id, litter_object_id)` — the CSV export — read them
either way; surfaces that follow the stored pointer — quick tags, team tag editing — skip them
until it is set. For `plastic_bag → plasticBags` that is 10,051 rows carrying 12,946 items.

The old object and its pivots remain as tombstones so stale clients can resolve the retirement.
Photos are processed in atomic batches of 200. A failed batch rolls back and can be rerun.

The command refuses self-migrations, retired replacements, conflicting existing retirements,
different XP weights, and source rows that already carry a type.

## Type splits (`--type`)

v4 baked the subtype into the object key — `beer_can` is `can` plus type `beer`. Repointing the
object alone would discard the subtype, and the "already carries a type" guard does **not** catch
this: the source rows have no type yet, so the guard never fires. `--type` makes the split one
approved operation, setting `litter_object_type_id` in the same batched update as the repoint.

```bash
php artisan olm:migrate-tag beer_can can --type=beer --apply
```

The type must already be approved for the survivor pairing in `category_object_types`. The run
refuses otherwise rather than attaching it, because a migration inventing a taxonomy relationship
is what produced the shadow objects in the first place. Types are approved on the survivor pairing
by `TagsConfig` and the seeder before the run.

## Usage

The default is a read-only dry run:

```bash
php artisan olm:migrate-tag plasticBags plastic_bag
```

It reports the mapping, affected row count, total tag quantity, and up to five example photo IDs, then
runs every apply-time validation and exits 1 with "Would fail: …" if any would abort:

```text
DRY RUN: plasticBags (149) → plastic_bag (92)
Rows: 10051
Tags: 12946
Example photo IDs: 123, 456, 789
```

Apply the migration with:

```bash
php artisan olm:migrate-tag plasticBags plastic_bag --apply
```

During apply, a progress bar shows the number of durably migrated rows.

## Before applying

- Update `TagsConfig`, `BrandsConfig`, translations, and documentation for the approved mapping.
- Deploy the code, run `php artisan migrate`, then run the tags seeder (`composer seed:tags`, which
  calls `GenerateTagsSeeder`) so every declared survivor pairing exists. The command never creates a
  pivot, so a mapping whose survivor pairing is missing aborts. New code reads the `retired_at`,
  `merged_into_id` and `merged_into_*` columns on hot paths, so migrate before traffic resumes.
- Put every web node into maintenance mode and drain in-flight tag writes.
- Back up MySQL and run the dry run against the exact database and code being deployed. The dry
  run performs every apply-time check (survivor declared and active, type approved, recorded
  mappings consistent, no interrupted earlier mapping) and exits 1 with "Would fail: …" on any
  of them, so a clean dry run of the whole manifest is meaningful.
- Confirm Redis is available; `--apply` also checks it before starting and before every batch.
- Confirm the reported counts and example photos match the approved mapping.

## After applying

Check that:

- `php artisan olm:verify-tag-integrity` reports no rows or quick tags left on a tombstoned pairing
  (the mapping did not finish — re-run it) and no retirement chain cycles. Its exit code stays 1
  while any undecided pairing still has no pivot, so the full manifest must be applied, and the
  remaining pairings declared or moved, before the gate exits 0.
- Tag A is retired and points to Tag B.
- No `photo_tags` or quick tags still reference Tag A.
- Affected summaries and Redis object counts use Tag B.
- Picker, location, profile, and export surfaces no longer expose Tag A.

The dry run counts only rows sitting on Tag A. It does not report the rows that step 4 backfills,
so a small or zero row count is not evidence of a small change.

The command intentionally has no lifecycle manager, snapshot files, repair mode, or verification mode. If an apply fails, inspect the database and Redis before rerunning it. MySQL batches are resumable, but Redis is not transactionally coupled to MySQL and still requires the checks above.

## Scope

This command performs an object retirement. Type expansion, category reassignment, row deduplication, and deciding future mappings are separate work.

`ClassifyTagsService` remains unchanged because it records the historical v5 migration.
