# Tag Cleanup — Unresolved Decisions & Aggregate Impact

**Generated:** 2026-08-03 · **Source:** `olm_postmig_2` (production dump), read-only
**Nothing has been changed.** These are proposals awaiting review.

| Deliverable | Rows | sha256 (first 16) |
|---|---:|---|
| `LitterObjectInventory-2026-08.csv` | 198 | `1b0fc5ab0b5090e4` |
| `LitterObjectDecisions-2026-08.csv` | 198 | `4aca73cc21b18418` |
| `TagPairMigrationManifest-2026-08.csv` | 74 | `a5c6a4c7dc5a4d44` |

Deterministic ordering: inventory/decisions by `object_key`, manifest by
`(source_category_key, source_object_key)`. Regenerating from the same dump reproduces
identical checksums.

---

## Aggregate impact

**All 74 affected pairs total exactly 277,169 items** — matching the pivotless total, so the
manifest has complete coverage with no gaps or overlaps.

| Operation | Pairs | Items |
|---|---:|---:|
| Object merge | 67 | 269,089 |
| Type expansion | 19 | 139,713 |
| Category move | **17** | 28,164 |
| → generic `other` | **15** | **39,123** |

Operations overlap (a pair can be merge + type + category move), so columns do not sum.

**Object dispositions (198 total):**

| Disposition | Objects |
|---|---:|
| keep | 127 |
| merge (proposed) | 47 |
| **REVIEW — merge to generic** | **13** |
| **REVIEW — canonical object, category has no pivot** | **9** |
| RETAIN (recommended) — crisp sizes | 2 |

---

## Unresolved decisions

### D1 — 15 pairs / 39,123 items collapse into generic `other`

**5 recommended REJECT (3,596 items)** — canonical objects already exist, they just lack a
pivot in that category:

| Pair | Items | Proposed | Recommendation |
|---|---:|---|---|
| marine / `bag` | 1,662 | marine/`other` | add marine pivot for `bag` |
| marine / `bottle` | 1,070 | marine/`other` | add marine pivot for `bottle` |
| marine / `balloons` | 635 | marine/`other` | add marine pivot for `balloon` |
| marine / `straws` | 226 | marine/`other` | add marine pivot for `straw` |
| marine / `lego` | 3 | other/`other` | keep in marine — known marine litter |

**10 recommended REVIEW (35,527 items)** — no canonical equivalent exists; either accept the
loss or add a new object: `randomLitter` (33,801), `hair_tie` (546), `elec_small` (385),
`toothpick` (309), `magazine` (124), `washingUp` (102), `elec_large` (100), `books` (71),
`ear_plugs` (46), `item` (43).

`randomLitter` is genuinely generic and probably fine. The rest are specific identifiable
objects.

### D2 — 17 category moves (28,164 items)

Seven come from canonical objects tagged in a category with no pivot (`industrial/plastic`,
`sanitary/gloves`, `other/dogshit`, `other/dogshit_in_bag`, `other/tyre`, `marine/lighters`,
`sanitary/sanitiser`); ten from legacy objects. Largest single move is `facemask`
sanitary → medical (7,910). Each needs confirmation that the destination category is intended.

### D3 — Row policy: repoint, do not merge

Simulated over all 74 mappings: **705 logical duplicate groups**, 1,454 rows, 3,067 items;
597 collide with an existing canonical tag; 317 carry extra tags; **280 have differing
material/brand/custom-tag sets**. Physically merging those rows destroys those dimensions.

**Decision required:** confirm the cleanup repoints rows and keeps them physically separate.
Row-level deduplication becomes a separate later operation with a dimension-aware rule.

### D4 — Crisp sizes cannot be preserved

`crisp_small` (5,037) and `crisp_large` (469) both target `food/crisp_packet`. `TagsConfig`
defines `crisp_packet` with materials only, and `photo_tags` has **no size or state column** —
there is nowhere to record size per observation. Marked `RETAIN (recommended)` pending a
per-observation size model.

### D5 — 34 objects carry duplicate candidates

17 normalised-key groups. **Candidates only — never auto-approved.** One is flagged as almost
certainly a false positive: `paper` (other, 31,919) vs `papers` (smoking, 6 — rolling papers).
Disjoint categories; must stay separate. Any group flagged
`NORMALISED MATCH BUT DISJOINT CATEGORIES` needs the same scrutiny.

### D6 — 21 objects are referenced by `BrandsConfig`

Retiring any of them without updating `app/Tags/BrandsConfig.php` means
`AutoCreateBrandRelationships` recreates the object **and its pivot** on its next run, silently
undoing the cleanup. `BrandsConfig` must change in the same commit. Known references include
`plasticBags` (12×), `beer_can` (13×), `crisp_small` (4×), `crisp_large` (3×).

### D7 — New objects are product + schema decisions

Anything added under D1 also needs: a canonical key and category placement in `TagsConfig`;
translations in `resources/js/langs/*/litter.json`; tag-picker visibility (governed by pivot
existence via `GetTagsController::getAllTags()`); a new CSV export column; and a compatibility
note for downstream consumers.

---

## How to review

1. Work `LitterObjectDecisions-2026-08.csv` — 71 rows are not `keep`; those are the decisions.
   Fill `review_status`, `approved_by`, `approved_date`.
2. Then `TagPairMigrationManifest-2026-08.csv` — confirm each of the 74 pairs, especially the
   15 marked `op_to_generic_other = yes` and the 17 with `op_category_move = yes`.
3. `LitterObjectInventory-2026-08.csv` is the evidence base — measured counts plus reference
   counts across `TagsConfig`, `BrandsConfig`, `ClassifyTagsService`, `FixOrphanedTags`,
   translations and the rest of `app/`.

The approved manifest becomes the **immutable input** to the cleanup command. The command must
read it, not re-infer mappings.

**Do not write the cleanup command until these are approved.** The export fix already restores
full visibility, so nothing here is urgent.

---

## Regenerating

Read-only against `olm_postmig_2`; no writes. Classification: an object is *canonical* if its
key appears in `TagsConfig::get()`; a pair is *pivotless* if it has no `category_litter_object`
row. Proposed targets are read from `FixOrphanedTags::buildMappings()` by reflection —
that command is otherwise untouched, as are `ClassifyTagsService` and the migration scripts.
