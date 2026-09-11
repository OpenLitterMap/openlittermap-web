# Post-Migration Tag Cleanup

`olm:migrate-tag` retires one litter object and moves its data to an approved replacement.

## Mapping list

Approved mappings are recorded in `readme/audit/TagRetirements-2026-08.csv`. The command receives the two approved keys directly and never chooses or reverses a mapping.

The first approved mapping is:

```text
other--plastic_bag: plastic_bag (92) → plasticBags (149)
```

## What the command does

For one entry, the command:

1. Sets `retired_at` on Tag A and points `merged_into_id` to Tag B (skipped for a pure
   category move, where the object stays live).
2. Creates Tag B's category pivot where needed. With `--category` the target pivot must already
   exist — the run refuses to create it.
3. Records the full approved mapping on each of Tag A's source pivots:
   `category_litter_object.merged_into_clo_id` (the survivor pairing) and `merged_into_type_id`
   (the approved subtype, when `--type` is given). This is what stale clients and saved quick tags
   resolve from, so a v4 composite key keeps its subtype and an object+category move lands in the
   right category. A marked pivot is excluded from the picker by `CategoryObject::active()`.
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
is what produced the shadow objects in the first place. A survivor CLO the run creates itself has
no approved types, so `--type` cannot be used with one.

## Usage

The default is a read-only dry run:

```bash
php artisan olm:migrate-tag plastic_bag plasticBags
```

It reports only the mapping, affected row count, total tag quantity, and up to five example photo IDs:

```text
DRY RUN: plastic_bag (92) → plasticBags (149)
Rows: 253
Tags: 264
Example photo IDs: 123, 456, 789
```

Apply the migration with:

```bash
php artisan olm:migrate-tag plastic_bag plasticBags --apply
```

During apply, a progress bar shows the number of durably migrated rows.

## Before applying

- Update `TagsConfig`, `BrandsConfig`, translations, and documentation for the approved mapping.
- Put every web node into maintenance mode and drain in-flight tag writes.
- Back up MySQL and run the dry run against the exact database and code being deployed.
- Confirm Redis is available; `--apply` also checks it before starting and before every batch.
- Confirm the reported counts and example photos match the approved mapping.

## After applying

Check that:

- Tag A is retired and points to Tag B.
- No `photo_tags` or quick tags still reference Tag A.
- No `photo_tags` row on Tag B has a null `category_litter_object_id`.
- Affected summaries and Redis object counts use Tag B.
- Picker, location, profile, and export surfaces no longer expose Tag A.

The dry run counts only rows sitting on Tag A. It does not report the rows that step 3 backfills
or the export columns the new pivot unlocks, so a small or zero row count is not evidence of a
small change.

The command intentionally has no lifecycle manager, snapshot files, repair mode, or verification mode. If an apply fails, inspect the database and Redis before rerunning it. MySQL batches are resumable, but Redis is not transactionally coupled to MySQL and still requires the checks above.

## Scope

This command performs an object retirement. Type expansion, category reassignment, row deduplication, and deciding future mappings are separate work.

`ClassifyTagsService` remains unchanged because it records the historical v5 migration.
