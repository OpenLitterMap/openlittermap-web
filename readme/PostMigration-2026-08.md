# Post-Migration Follow-Up — August 2026

## Pivotless Litter Objects & Silent Export Undercounting

**Created:** 2026-08-03
**Status:** Export visibility fixed. Production data cleanup outstanding.
**Severity:** High — affected every CSV export on production since the v5 migration.

> **Scope note.** This is a **dated incident follow-up**, not a primary migration document.
> It records one specific defect found on 2026-08-03 and what remains open.
> The primary docs are unchanged and remain authoritative:
> `readme/PostMigrationCleanup.md` (v4 code removal backlog), `readme/Tags.md` (canonical
> taxonomy), `readme/ExportData.md` (export system). Fold anything here into those only
> once the outstanding work below is actually done.

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
> It follows that:
> - The confirmed defect is narrow: the migrated `(category, object)` pair had **no pivot
>   row**, and the exporter treated the pivot as authoritative.
> - `FixOrphanedTags` is proposing a **later taxonomy consolidation**
>   (`plasticBags → plastic_bag`), *not* completion of the original migration. It needs
>   product sign-off on the taxonomy, not merely ID validation.

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

`olm_postmig_2` is a current production dump imported locally on 2026-08-03 and reproduces
the defect exactly. Keep it as the **untouched baseline**; do all experiments on a clone.

The v5 migration has been run on production. What has **not** run there is any
post-migration data repair, which is why production carries 189,518 pivotless rows while
the local snapshot carries none. That divergence is why the defect was invisible in local
testing — and it is why importing a production dump locally (planned) is the right next
step before any repair is attempted.

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
created for them.** Whether the naming was intended is the open question above; the missing
pivot is unambiguously a defect, because `photo_tags` then references a (category, object)
pair the taxonomy has no record of.

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
produced a column, so the loss was total rather than partial. `AutoCreateBrandRelationships`
*can* create a pivot alongside the object, which would instead surface one as a stray
duplicate column; it evidently did not do so for these. That path remains a live risk if
the command is ever re-run (see limitation 8).

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

The 603 are the migrated history; the 109 are new post-migration tagging, which correctly
resolves to the canonical object. (The reporter quoted ~80 rather than 109 — their download
was date-filtered. 109 is the full unfiltered export-scope figure.)

**The `plasticBags` mapping in `FixOrphanedTags` was ID-validated against production**
(2026-08-03). Note this validates only that the IDs resolve as stated — it does NOT
authorize the object merge itself (operation B below):

| check | result |
|---|---|
| `orphan_lo_id` 149 resolves to | `plasticBags` ✓ |
| `target_lo_id` 92 resolves to | `plastic_bag` ✓ |
| `target_category_id` 12 resolves to | `other` ✓ |
| `target_clo_id` 111 is (category, object) | (12, 92) ✓ |
| Rows matching the fix predicate | 10,051 / 10,051 ✓ |
| Rows that would be silently skipped | 0 ✓ |

If that merge were run, `plastic_bag` would read **13,210** globally and **712** for team
211.

**Update (2026-08-03):** all **74** mappings have since been structurally validated against
the `olm_postmig_2` production dump — every id resolves to its expected key, every target CLO
belongs to its stated pair, and the 74 mappings cover exactly the 74 pivotless pairs
(189,518 rows). **The target taxonomy is still not approved** — see
`readme/TagAudit-2026-08.md`.

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
header.

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

## Existing post-migration cleanup tooling (written, not yet run on production)

Two commands already exist under `app/Console/Commands/tmp/v5/Migration/`:

| Command | Purpose |
|---|---|
| `olm:fix-orphaned-tags` | Repoints `photo_tags` onto canonical objects/CLOs/types. `--apply` (dry-run by default), `--verify-only`, `--batch=5000`, `--log=` |
| `olm:regenerate-summaries` | Rebuilds `photos.summary` from `photo_tags`. `--orphan-fix` (resumable), `--dry-run`, `--changed-ids=`, `--batch=500`. No metrics/events/Redis side effects |

`FixOrphanedTags::buildMappings()` already contains **74 mappings** covering the shadow objects, including the object/type splits and multi-category cases:

```php
['label' => 'plasticBags',  'orphan_lo_id' => 149, 'target_clo_id' => 111, 'target_lo_id' => 92, 'target_category_id' => 12],
['label' => 'randomLitter', 'orphan_lo_id' => 170, 'target_clo_id' => 121, 'target_lo_id' => 1,  'target_category_id' => 12],
['label' => 'beer_can',     'orphan_lo_id' => 137, 'target_clo_id' => 5,   'target_lo_id' => 5,  'target_category_id' => 2, 'type_id' => 1],
['label' => 'brokenglass',  'orphan_lo_id' => 164, 'target_clo_id' => 3,   'target_lo_id' => 3,  'target_category_id' => 2, 'category_filter' => 2],
['label' => 'brokenglass',  'orphan_lo_id' => 164, 'target_clo_id' => 151, 'target_lo_id' => 3,  'target_category_id' => 16, 'category_filter' => 16],
```

`randomLitter` (33,801 items) is mapped to `other` in the Other category.

> ### ⚠ This tool is not a mechanical referential-integrity repair
>
> Despite the name, `fix-orphaned-tags` changes **object identity**, and in many mappings
> the **category** and **type** as well. `plasticBags → plastic_bag` merges one object into
> another; `beer_can → can` + `type=beer` re-models the tag; `brokenglass` is split across
> two categories by `category_filter`. Those are **taxonomy consolidation decisions**, not
> integrity fixes, and validating that the numeric IDs resolve correctly does **not**
> authorize them. They need product sign-off, not just a preflight.
>
> **Two distinct operations are conflated here, and only the first is mechanically safe:**
>
> | | Operation | Changes | Product-neutral? |
> |---|---|---|---|
> | **A** | Pivot repair | Sets `category_litter_object_id` only | **No — see below** |
> | **B** | Taxonomy consolidation | Changes object / category / type | **No** |
>
> **The minimal fix for the reported bug is A alone:** ensure a pivot row exists for each
> (category, object) pair actually in use, point `photo_tags.category_litter_object_id` at
> it, and leave `category_id`, `litter_object_id`, `quantity` and type untouched. Because
> object identity never changes, wide-export quantities stay correct even in the window
> between the tag update and summary regeneration — which removes the ordering hazard in
> limitation 3 entirely.
>
> **⚠ But A is not product-neutral either.** `GetTagsController::getAllTags()` selects
> `LitterObject::with(['categories:id,key'])->whereHas('categories')` — an object appears in
> the tagging UI **only if it has a pivot row**. That is the sole reason the 62 legacy objects
> are not currently offered to users. Creating pivots for them would make `plasticBags`,
> `beer_can`, `randomLitter` and the rest **selectable in the tag picker alongside their
> canonical equivalents**, and users could start creating new tags on legacy objects.
>
> So A needs either a UI-visibility guard (e.g. exclude `crowdsourced = 1` from
> `getAllTags()`) or its own product decision. **Verify the `/api/tags` response before and
> after any pivot creation.**
>
> B can then be evaluated separately, on its own merits, whenever the taxonomy question in
> the Summary is settled.

On the local `olm_postmig_1` snapshot the data sits on canonical objects with zero orphaned
rows, whereas production has 189,518. The snapshot state is consistent with these cleanup
commands having been applied there, but that has not been confirmed — it may equally be a
differently-produced dump. What matters is the divergence itself: it is why the defect was
invisible in local testing.

---

## Known limitations — for future consideration

### 1. Duplicate columns in exports (active now)

Until the data merge runs, exports show **both** the shadow and canonical column for the
same real-world object — e.g. `plasticBags` (603) *and* `plastic_bag` (109). Totals are
complete but users must sum two columns. This trips up naive downstream scripts.

**Mitigation:** tell data consumers to sum both columns. Note the merge that would collapse them is operation B above — taxonomy consolidation — and is not yet authorized.

### 2. No production data repair has been run

62 pivotless objects still hold live tags on production. Neither operation A (pivot repair)
nor B (taxonomy consolidation) has been run. Until A runs, `photo_tags` keeps referencing
(category, object) pairs absent from the taxonomy; until B is authorized and run, the
duplicate-column situation in limitation 1 persists.

### 3. `fix-orphaned-tags` does NOT regenerate summaries — the two commands must be paired

`CreateCSVExport::map()` reads quantities from `photos.summary` JSON, which stores
`object_id`. `FixOrphanedTags` updates `photo_tags` and stops — it never touches summaries.

**Running `olm:fix-orphaned-tags --apply` alone makes exports worse, not better:** columns
get built from the corrected `photo_tags` while `map()` still looks up shadow object ids in
stale summaries, so the values go blank. `olm:regenerate-summaries --orphan-fix` **must**
follow, ideally per batch rather than once at the end.

### 4. A partial `fix-orphaned-tags` run is not self-resuming

It selects rows by the old-object / NULL-CLO predicate
(`where('litter_object_id', $orphanLoId)->whereNull('category_litter_object_id')`). Once a
row is updated it no longer matches, so a crashed mid-run cannot rediscover what it already
changed.

`$affectedPhotoIds` **is** collected in `processMapping()` but is only ever used for
`count(array_unique(...))` in the run summary — it is never written anywhere. So the list of
photos needing summary regeneration is lost when the process exits, and you fall back on
`regenerate-summaries --orphan-fix` doing its own broader scan. Dump the ids to a file
before mutating and treat batches as the unit of recovery.

### 5. The whole of each mapping is one transaction

`executeBatched()` wraps its entire `while (true)` loop in a single `DB::transaction()`, so
`--batch=5000` bounds each `UPDATE` statement but **not** the transaction. `plasticBags` is
10,051 rows in one transaction and `randomLitter` is 12,821 — on a live `photo_tags` table
that means sustained lock contention and undo-log growth. Prefer a transaction per batch.

### 6. The mappings use hardcoded numeric IDs with no preflight validation

`buildMappings()` hardcodes `orphan_lo_id`, `target_clo_id`, `target_lo_id`,
`target_category_id` and `type_id` as raw integers derived from one specific database.
Nothing verifies that id 137 is actually `beer_can`, that CLO 5 belongs to the stated
category/object, or that type 1 is `beer`. On any database whose ids differ, `--apply`
would **silently reclassify data incorrectly** — a worse outcome than the current defect.

Each mapping already carries a `label`. Add a preflight that resolves every id by key and
aborts on the first mismatch before any write.

### 7. Duplicate logical tags after repointing

If a photo already has a canonical tag *and* a shadow tag for the same
category/object/type, repointing creates two logically identical `photo_tags` rows. Decide
up front whether to merge quantities or leave duplicates, and reconcile before deleting
shadow objects.

### 8. The 29 non-canonical mappings stay as-is — `ClassifyTagsService` is frozen

`normalizeDeprecatedTag()` maps 29 legacy keys to non-canonical object keys
(`plastic_bags` → `plasticBags`). This is **deliberately not being corrected in place**.

> **Decision (2026-08-03): do NOT edit `ClassifyTagsService`.** It is the source of truth
> for what the v5 migration actually did. Changing the mapping table would rewrite the
> historical record and break the ability to reason about or reproduce the migration.
> Any correction belongs in a **new dated post-migration command**, not here.

Note also that the mapping table could not have expressed the canonical target for several
of these even if it wanted to: `MigrationScript.php` has no `litter_object_type` handling,
and `UpdateTagsService`'s `'type'` key is the `Dimension` (brand/material/object), not
`litter_object_type_id`. So `beerCan` → `can` + type `beer` was inexpressible, and
flattening it to a `beer_can` object was a reasonable workaround at the time.
`FixOrphanedTags` is the corrective pass that applies the type dimension.

**The operational guard:** `AutoCreateBrandRelationships` calls `normalizeDeprecatedTag()`
and then `firstOrCreate`s both the object **and** a pivot row. Re-running it after the
cleanup would recreate shadow objects and undo the merge. Treat that command as
**do-not-re-run** unless a new post-migration script supersedes its mapping.

### 9. Column ordering uses PHP sort, not MySQL collation

Object columns are now ordered by `->sortBy('key')` in PHP rather than a SQL
`orderBy('litter_objects.key')`. Case, accents, numeric-looking keys and ties may order
differently than before. Same-data exports remain stable, but if positional stability ever
becomes contractual, use an explicit comparator with an id tie-breaker. Consumers should
key off header names regardless.

### 10. Historical exports were undercounted

Any CSV downloaded between the v5 migration and 2026-08-03 undercounts litter — in some
categories severely. Given OpenLitterMap's 98+ peer-reviewed citations, consider notifying
known data users and researchers.

### 11. Other surfaces not yet audited

Only the CSV export was investigated. Anything else reading `litter_objects.key` or
`summary.object_id` may render shadow keys to users (e.g. `plasticBags` instead of
"Plastic Bag" in photo tag lists). Not yet checked: map popups, profile tag breakdowns,
mobile tag display, achievements.

---

## Outstanding workstream — full tag & key audit

> **The audit has been carried out — see `readme/TagAudit-2026-08.md`** (2026-08-03, against
> the `olm_postmig_2` production dump). Headline results: 198 objects split cleanly into 136
> canonical + 62 legacy with zero unused rows; **16 legacy keys have a canonical twin**
> (`plasticBags`/`plastic_bag` among them); the affected unit is **74 (category, object)
> pairs** across 71 objects — including **9 canonical objects tagged in a category they have
> no pivot for** (10,812 items) that an object-level review misses entirely. The proposed
> consolidation would collapse **39,123 items into generic `other`**, perform **17 category
> moves**, and create **280 collision groups with differing extra-tag sets** that must not be
> merged. The scope notes below remain as the checklist.

**Decided 2026-08-03.** The taxonomy consolidation should not be executed mapping-by-mapping
in isolation. It requires a **complete review of every tag and key, old and new**, as part of
post-migration cleanup. The 74 `FixOrphanedTags` mappings are one input to that review, not
the review itself.

Scope to cover:

| Surface | What to review |
|---|---|
| `litter_objects` | All 198 rows. Which are canonical (`TagsConfig`), which are legacy migrated, which are `crowdsourced` accidents. Decide keep / merge / retire for each |
| `categories` | Canonical set vs `CATEGORY_ALIASES` vs rows actually referenced by `photo_tags` |
| `category_litter_object` | Which pairs should exist. This drives both the export and the tag picker |
| `litter_object_types` | Which types are real, which are legacy, which objects may carry them |
| `materials` | Canonical list vs crowdsourced accretion |
| `custom_tags_new` | ~7k free-text rows. Which should be promoted to objects/brands, which merged, which dropped |
| `brand_list` | Duplicates and casing variants |
| `TagsConfig` | Whether it still reflects the intended taxonomy after all of the above |

Cross-cutting decisions the audit must settle:

1. **Legacy vs canonical** — for each of the **74 pivotless pairs** (62 legacy objects **plus
   9 canonical objects tagged in a category they have no pivot for**), is it retired into a
   canonical object, or given a pivot as a first-class pair?
2. **Type dimension** — which legacy flat objects (`beer_can`, `soda_can`) become
   object+type, and is the migration path taught to express types at all?
3. **UI exposure** — which objects should be selectable in the tag picker. Currently
   governed implicitly by pivot existence (see the warning above); that coupling should be
   explicit.
4. **Row policy** — repoint rows and keep them separate, or merge duplicates? **Settled: keep
   separate.** 280 collision groups carry differing material/brand/custom-tag sets that a
   merge would destroy. Physical deduplication is a separate later operation.
5. **Export header stability** — any rename is a breaking change for downstream researchers.
   Needs a deprecation story, not a silent swap.

Until this audit is done, **do not run any taxonomy consolidation on production**. The
export hotfix already restores full visibility, so there is no pressure to mutate data.

---

## Local rehearsal workflow (production dump → local)

Everything below runs against a **disposable local copy of production**, never production.

1. Import the production dump into an isolated database.
2. Confirm the baseline reproduces the defect: **189,518** pivotless rows, and the quantity
   totals in the impact table above.
3. Save an untouched clone so every experiment is repeatable from a known state.
4. Run `olm:fix-orphaned-tags` **without** `--apply`. Treat the output as a count preview
   only — it is not validation.
5. Run the read-only semantic preflight across all 74 mappings (not yet written).
6. Classify every mapping: pivot-only repair, object merge, category move, type expansion,
   or a combination.
7. Review the intended taxonomy outcome for every non-pivot-only mapping — this is the
   audit above, and it gates everything after this point.
8. On a clone, run `--apply`, immediately regenerate summaries, require zero errors.
9. Compare before/after: row counts, quantity sums, distinct photos, summary contents, XP,
   duplicate logical tags, CSV exports, **and the `/api/tags` response** (see the pivot
   warning — this is where legacy objects would become user-selectable).
10. Restore the clean clone and repeat until the whole process is deterministic and
    restartable.

---

## Recommended run sequence (when the merge is scheduled)

Before applying:

1. Do **not** edit `ClassifyTagsService` (limitation 8). Instead, confirm
   `AutoCreateBrandRelationships` will not be re-run, or supersede it from a new dated
   post-migration command.
2. Add the id→key preflight to `buildMappings()` and abort on any mismatch (limitation 6).
3. Reconcile photos that hold both a canonical and a shadow tag for the same tuple (limitation 7).
4. Capture baseline counts and quantity sums by object key, category, type, team and photo.
5. Take a database backup.

Applying, in restartable batches:

1. Record the batch's photo ids durably (`--changed-ids=`) **before** mutating.
2. `olm:fix-orphaned-tags --apply --batch=…` for the batch.
3. `olm:regenerate-summaries --orphan-fix --from-file=…` for the same batch.
4. Verify summary object ids and quantities against `photo_tags` for the batch.
5. Mark the batch complete.
6. Delete shadow `litter_objects` **only** after proving no references remain anywhere.

After:

- Total quantities unchanged vs baseline.
- No mapped shadow references remain in `photo_tags` or in any `summary`.
- Export totals reconcile against `photo_tags` per team.
- Split and joined exports contain no unexpected duplicate headings.

---

## Verification queries

```sql
-- Total litter invisible in exports (should be 0 after the merge)
SELECT COUNT(*) AS tag_rows, SUM(pt.quantity) AS qty_invisible
FROM photo_tags pt
WHERE pt.category_id IS NOT NULL AND pt.litter_object_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM category_litter_object clo
                   WHERE clo.category_id = pt.category_id
                     AND clo.litter_object_id = pt.litter_object_id);

-- Shadow objects still holding tags (should be empty after the merge)
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

- `readme/PostMigrationCleanup.md` — v4 code removal
- `readme/ExportData.md` — CSV export system
- `readme/Tags.md` — tagging system and canonical `TagsConfig`
- `app/Services/Tags/ClassifyTagsService.php` — deprecated mappings (`normalizeDeprecatedTag`)
- `app/Exports/CreateCSVExport.php` — column derivation
