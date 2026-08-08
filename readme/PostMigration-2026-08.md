# Post-Migration Incident — August 2026

## Pivotless Litter Objects & Silent Export Undercounting

**Created:** 2026-08-03 · **Last revised:** 2026-08-08
**Status:** Export visibility fixed (v5.13.1). Production data cleanup outstanding.
**Severity:** High — affected every CSV export on production since the v5 migration.

> **This is an incident record.** It documents one defect found on 2026-08-03: what broke, why,
> what was measured, and what shipped. It is the evidence base, not a plan.
>
> **The forward process lives in `readme/PostTagMigrationClean.md`** — the retirement list, the
> per-tag pipeline, the verification surfaces and the rehearsal loop. Anything in this file that
> proposed a cleanup workflow has been removed rather than left to rot alongside it.
>
> Also authoritative for their own subjects: `readme/Tags.md` (taxonomy),
> `readme/ExportData.md` (export system), `readme/PostMigrationCleanup.md` (v4 code removal).

---

## Summary

**The proven defect is in the export layer, not the migration.** ~190k `photo_tags` rows
sit on litter objects that have **no `category_litter_object` pivot row**. `CreateCSVExport`
treated the pivot as authoritative for which columns exist, so every one of those quantities
was silently dropped from exports — no column, no warning, no error.

**No data was ever lost.** All quantities are intact in `photo_tags` with correct
categories and quantities.

> **Resolved 2026-08-03.** `plastic_bags → plasticBags` **was the intended migration
> representation**. These objects are not mistakes and must not be described as such.
>
> It follows that the confirmed defect is narrow: the migrated `(category, object)` pair had
> **no pivot row**, and the exporter treated the pivot as authoritative. Which of the competing
> keys should ultimately survive is a taxonomy question, settled per tag in
> `readme/PostTagMigrationClean.md`, not a consequence of this defect.

Reported by a team member of team 211 (Iowa City Litter Pickers), who saw a plastic bag
count of ~80 against a previous dataset showing ~338.

### State of play

| | Production (= `olm_postmig_2`) | Older local `olm_postmig_1` |
|---|---|---|
| v5 tag migration | **Run** | Run |
| Any post-migration data repair | **Not run** | Unknown — state is consistent with it |
| Pivotless tag rows | **189,518** | 0 |
| Tags on `plasticBags` (149) | 10,051 | 0 |
| Tags on `plastic_bag` (92) | 253 | 10,073 |

`olm_postmig_2` is a production dump imported locally on 2026-08-03 and reproduces the defect
exactly. Re-verified unmutated on 2026-08-08: 189,518 pivotless rows / 277,169 items. It is
restorable from the owner's `.sql` source-of-truth dump, so it doubles as the rehearsal
database.

The v5 migration has been run on production. What has **not** run there is any
post-migration data repair, which is why production carries 189,518 pivotless rows while
the older local snapshot carries none. That divergence is why the defect was invisible in
local testing.

---

## Root cause

Two separate bugs compounded:

### 1. The migration created objects outside `TagsConfig` — and no pivot rows for them

29 legacy v4 keys map to object keys that are not present in `TagsConfig`:

```php
'plastic_bags' => ['object' => 'plasticBags'],   // TagsConfig has plastic_bag
'bags_litter'  => ['object' => 'bagsLitter'],    // TagsConfig has bags_litter
'beerCan'      => ['object' => 'beer_can'],      // TagsConfig has can + type beer
```

`classifyNewKey()` (`ClassifyTagsService.php:118`) falls back to
`LitterObject::firstOrCreate([...], ['crowdsourced' => true])` for any key it doesn't
recognise, so each of these minted a real `litter_objects` row. 62 such objects exist and
currently hold tags.

**The consequential part is not the naming — it is that no `category_litter_object` row was
created for them.** `photo_tags` then references a (category, object) pair the taxonomy has no
record of.

### 2. `CreateCSVExport` derived columns from the pivot

The constructor intersected two different sources — the pivot for column *identity*, and
`photo_tags` for column *activity*:

```php
'objects' => $cat->litterObjects                                  // ← pivot
    ->filter(fn ($obj) => in_array($obj->id, $activeObjMap[$cat->id] ?? []))  // ← photo_tags
```

A tagged object with no pivot row never produced a column. `map()` still computed its
quantity into `$tagLookup`, but nothing read it. Silent loss.

Note the live tagging path (`AddTagsToPhotoAction`) resolves existing CLO ids only and
**cannot** create these objects. `normalizeDeprecatedTag` is reachable only from
`UpdateTagsService` (migration) and `AutoCreateBrandRelationships`.

Measured on production, **all 62 of these objects have `pivot_links = 0`** — none of them
produced a column, so the loss was total rather than partial.

---

## Production impact (measured 2026-08-03)

| Metric | Value |
|---|---|
| Tag rows invisible in exports | **189,518** |
| Litter items invisible in exports | **277,169** |
| Pivotless objects holding tags | **62** (all with `pivot_links = 0`) |
| Items on pivotless objects | 266,357 |
| Items on **canonical** objects missing a pivot for that category | **10,812** (9 pairs, 6,932 rows) |
| Pivotless (category, object) **pairs** | **74** across 71 objects |
| Duplicate pivot rows | 0 |

Largest affected objects:

| Object | Items |
|---|---|
| `randomLitter` | 33,801 |
| `beer_can` | 26,488 |
| `water_bottle` | 21,889 |
| `energy_can` | 20,931 |
| `beer_bottle` | 18,074 |
| `soda_can` | 18,034 |
| `bottletops` | 13,018 |
| `plasticBags` | 12,946 |
| `cigarette_box` | 10,245 |
| `dump` | 9,733 |

Migration fidelity was verified independently and is **exact**. For `other.plastic_bags`,
every surviving photo's v4 value equals its v5 tag quantity (12,946 = 12,946, zero
mismatched photos across 10,073 photos). The migration preserved every item and every
quantity; what it did not do is create pivot rows for the objects it wrote them to.

Team 211 breakdown — true plastic bag count is **712**, export showed **109**:

| object | items | visible pre-fix |
|---|---|---|
| `plasticBags` (149) | 603 | ✗ |
| `plastic_bag` (92) | 109 | ✓ |

The 603 are the migrated history; the 109 are new post-migration tagging. (The reporter quoted
~80 rather than 109 — their download was date-filtered. 109 is the full unfiltered
export-scope figure.)

All **74** pivotless pairs were structurally validated against the `olm_postmig_2` dump on
2026-08-03 — every id resolves to its expected key, and the 74 pairs account for exactly the
189,518 rows. The aggregate analysis is in `readme/audit/TagCleanupSummary-2026-08.md`, with
the row-level evidence in `readme/audit/LitterObjectInventory-2026-08.csv`.

---

## What was fixed (2026-08-03, v5.13.1)

**1. Columns are derived from tags, not the pivot.** `CreateCSVExport` builds
category/object columns from `photo_tags` and resolves keys via a `LitterObject` lookup,
with no dependency on `category_litter_object`. Any tagged object is guaranteed a column.

**2. Colliding joined headers are disambiguated.** Joined-format headers are synthesized as
`{type}_{object}`, so a bare object keyed `beer_can` (no type) collided with canonical `can`
+ type `beer` — two distinct columns sharing one header. Associative CSV readers
(`csv.DictReader`, many ETL tools) silently drop one, recreating the undercount.

**Only the legacy side is renamed** (`legacy__beer_can__137`); the canonical typed column
keeps its established `beer_can` header, because consumers read that today and renaming both
would break every one of them. A final pass guarantees no renamed key can shadow an existing
header **within that category block**. Headers are not globally unique across categories, and
never were — category separator columns delimit them.

**129,334 items across 13 objects** are exposed to this — every pivotless key that
parses as `{canonical_type}_{canonical_object}`: `beer_can` (26,488), `water_bottle`
(21,889), `energy_can` (20,931), `beer_bottle` (18,074), `soda_can` (18,034),
`cigarette_box` (10,245), `spirits_bottle` (3,457), `sports_bottle` (3,277),
`juice_carton` (2,327), `juice_bottle` (1,940), `milk_bottle` (935), `milk_carton` (874),
`wine_bottle` (863). That is an upper bound — an actual collision also requires the
canonical object+type pairing to appear in the same export scope.

Regression tests in `tests/Unit/Exports/CreateCSVExportTest.php`:
- `test_tagged_object_without_a_category_pivot_still_gets_a_column`
- `test_joined_headings_disambiguate_shadow_and_canonical_collisions`

All 277k items are now visible in exports. Verified no change to clean data
(user 4051: `plastic_bag` = 1,576 before and after, identical column counts across
`split`, `joined`, and `split,joined`).

---

## ⚠ `olm:fix-orphaned-tags` — do not run

The command remains in the tree under `app/Console/Commands/tmp/v5/Migration/` as an
unapproved historical artifact. It bundles 74 unreviewed taxonomy decisions into one
irreversible run, changes object identity, category and type, and hardcodes numeric ids with
no preflight. **It is superseded and must not be executed.** The approved process is
`readme/PostTagMigrationClean.md`.

---

## Outstanding

### 1. Duplicate columns in exports (active now)

Until the retirements run, exports show **both** competing columns for the same real-world
object — e.g. `plasticBags` (603) *and* `plastic_bag` (109). Totals are complete but users must
sum two columns, which trips up naive downstream scripts.

**Mitigation:** tell data consumers to sum both columns.

### 2. No production data repair has been run

62 objects still hold live tags on production against (category, object) pairs absent from the
taxonomy. Nothing has been changed on production beyond the export fix.

### 3. Historical exports were undercounted

Any CSV downloaded between the v5 migration and 2026-08-03 undercounts litter — in some
categories severely. Given OpenLitterMap's 98+ peer-reviewed citations, consider notifying
known data users and researchers. **Nothing has been sent; this is unowned.**

---

## Verification queries

```sql
-- Tag rows referencing a (category, object) pair with no pivot.
-- 189,518 today; trends to zero as retirements create pivots for the surviving keys.
SELECT COUNT(*) AS tag_rows, SUM(pt.quantity) AS qty_invisible
FROM photo_tags pt
WHERE pt.category_id IS NOT NULL AND pt.litter_object_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM category_litter_object clo
                   WHERE clo.category_id = pt.category_id
                     AND clo.litter_object_id = pt.litter_object_id);

-- Migration-minted objects still holding tags. Note that some of these are intended
-- SURVIVORS (e.g. plasticBags) whose crowdsourced flag is cleared on retirement of their
-- twin — so this is a worklist, not an error count.
SELECT lo.id, lo.`key`, COUNT(pt.id) AS tag_rows, SUM(pt.quantity) AS qty
FROM litter_objects lo
JOIN photo_tags pt ON pt.litter_object_id = lo.id
WHERE lo.crowdsourced = 1
GROUP BY lo.id, lo.`key` ORDER BY qty DESC;

-- Per-team reconciliation, v4 source vs v5 (example: team 211)
SELECT
  (SELECT COALESCE(SUM(o.plastic_bags),0) FROM other o
     JOIN photos p ON p.other_id = o.id WHERE p.team_id = 211) AS v4_plastic_bags,
  (SELECT COALESCE(SUM(pt.quantity),0) FROM photo_tags pt
     JOIN photos p ON p.id = pt.photo_id
     JOIN litter_objects lo ON lo.id = pt.litter_object_id
    WHERE p.team_id = 211 AND lo.`key` IN ('plastic_bag','plasticBags')) AS v5_plastic_bags;
```

---

## Related

- `readme/PostTagMigrationClean.md` — **the forward process**: retirement list and per-tag pipeline
- `readme/audit/TagCleanupSummary-2026-08.md` — aggregate analysis of the 74 pairs
- `readme/audit/LitterObjectInventory-2026-08.csv` — row-level evidence for all 198 objects
- `readme/PostMigrationCleanup.md` — v4 code removal
- `readme/ExportData.md` — CSV export system
- `readme/Tags.md` — tagging system and canonical `TagsConfig`
- `app/Services/Tags/ClassifyTagsService.php` — deprecated mappings (`normalizeDeprecatedTag`), frozen
- `app/Exports/CreateCSVExport.php` — column derivation
