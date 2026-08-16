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

1. Sets `retired_at` on Tag A and points `merged_into_id` to Tag B.
2. Creates Tag B's category pivot where needed.
3. Repoints `photo_tags` and `user_quick_tags` from A to B.
4. Regenerates affected photo summaries.
5. Reprocesses metrics for affected live, previously processed photos.

The old object and its pivots remain as tombstones so stale clients can resolve the retirement.
Photos are processed in atomic batches of 200. A failed batch rolls back and can be rerun.

The command refuses self-migrations, retired replacements, conflicting existing retirements,
different XP weights, and typed tags.

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
- Affected summaries and Redis object counts use Tag B.
- Picker, location, profile, and export surfaces no longer expose Tag A.

The command intentionally has no lifecycle manager, snapshot files, repair mode, or verification mode. If an apply fails, inspect the database and Redis before rerunning it. MySQL batches are resumable, but Redis is not transactionally coupled to MySQL and still requires the checks above.

## Scope

This command performs an object retirement. Type expansion, category reassignment, row deduplication, and deciding future mappings are separate work.

`ClassifyTagsService` remains unchanged because it records the historical v5 migration.
