# Tag & Key Audit — August 2026

**Created:** 2026-08-03
**Source database:** `olm_postmig_2` (production dump, imported locally)
**Status:** Analysis complete. **Taxonomy decisions outstanding — nothing has been changed.**

Companion to `readme/PostMigration-2026-08.md` (the export incident). That document explains
*why* this audit exists; this one is the actual review of every object key.

---

## Baseline — the local copy reproduces production exactly

| | Value |
|---|---|
| Photos | 536,971 |
| Photo tags | 617,044 |
| Litter objects | 198 |
| Category↔object pivots | 175 |
| Custom tags | 7,228 |
| Materials / types / categories | 40 / 33 / 17 |
| **Pivotless tag rows** | **189,518** |
| **Pivotless items** | **277,169** |

The pivotless figures match production exactly, so every number below is trustworthy.

Reproduce with:

```sql
SELECT COUNT(*) AS tag_rows, SUM(pt.quantity) AS qty
FROM photo_tags pt
WHERE pt.category_id IS NOT NULL AND pt.litter_object_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM category_litter_object clo
                   WHERE clo.category_id = pt.category_id
                     AND clo.litter_object_id = pt.litter_object_id);
```

---

## The unit of work is the (category, object) PAIR, not the object

> **Correction (2026-08-03).** An earlier version of this audit classified *objects*. That
> is the wrong unit. The migration, the pivot table, the exporter and `FixOrphanedTags` all
> operate on **(category, object) pairs**. An object can be perfectly healthy in one category
> and pivotless in another — and nine of them are.

| | Count |
|---|---:|
| **Pivotless (category, object) pairs** | **74** |
| Distinct objects involved | **71** |
| — legacy object keys (not in `TagsConfig`) | 62 |
| — **canonical object keys, wrong category** | **9** |

The 74 `FixOrphanedTags` mappings cover exactly these 74 pairs — verified by simulation,
which touched exactly **189,518** rows, matching the pivotless total precisely.

### Object-level inventory (context only)

| Class | Meaning | Objects | Items |
|---|---|---:|---:|
| **A** | Canonical + has a pivot *somewhere* | 136 | 542,496 |
| **C** | Legacy, no pivot anywhere, has tags | 62 | 266,357 |
| **E** | Unused, zero tags — deletable | 0 | 0 |

**There are no unused rows to delete.** Every legacy object carries live tags, so none can
simply be dropped — each needs a decision.

But note class A is misleading on its own: it means "has a pivot for *some* category", not
"has a pivot for every category it is tagged in". Nine canonical objects fail the latter.

### ⚠ The nine canonical objects tagged in a category they have no pivot for

**6,932 rows / 10,812 items.** These are entirely absent from any object-level review, and
seven of the nine are category moves:

| Current pair | Items | Proposed target | Operation |
|---|---:|---|---|
| industrial / `plastic` | 2,937 | other / `plastic` | **CATEGORY MOVE** |
| sanitary / `gloves` | 2,562 | medical / `gloves` | **CATEGORY MOVE** |
| marine / `bag` | 1,662 | marine / **`other`** | → generic |
| other / `dogshit` | 1,383 | pets / `dogshit` | **CATEGORY MOVE** |
| marine / `bottle` | 1,070 | marine / **`other`** | → generic |
| other / `dogshit_in_bag` | 649 | pets / `dogshit_in_bag` | **CATEGORY MOVE** |
| other / `tyre` | 436 | vehicles / `tyre` | **CATEGORY MOVE** |
| marine / `lighters` | 60 | smoking / `lighters` | **CATEGORY MOVE** |
| sanitary / `sanitiser` | 53 | medical / `sanitiser` | **CATEGORY MOVE** |

The `dogshit` → pets and `tyre` → vehicles moves look like straightforward category
rationalisation. **`marine/bag` → `marine/other` and `marine/bottle` → `marine/other` are
lossy and should be rejected** — marine bags and bottles are exactly the observations marine
litter research cares about, and `bag`/`bottle` already exist as canonical objects. Give
marine a pivot for them instead.

Legacy items by category:

| Category | Legacy objects | Items |
|---|---:|---:|
| softdrinks | 15 | 90,358 |
| alcohol | 6 | 69,915 |
| other | 22 | 67,909 |
| sanitary | 8 | 17,747 |
| smoking | 6 | 12,733 |
| marine | 10 | 9,728 |
| food | 3 | 5,694 |
| industrial | 3 | 3,042 |
| art | 1 | 43 |

---

## The duplicate-key question

> *"there are 2 keys for plastic_bag and plasticBags. we should only have 1."*

Correct — and it is **not** an isolated case. **16 legacy keys have a canonical twin**
meaning the same real-world object:

| Legacy key | Items | Canonical twin | Items | Note |
|---|---:|---|---:|---|
| `plasticBags` (149) | 12,946 | `plastic_bag` (92) | 264 | the reported case |
| `straws` (150) | 8,664 | `straw` (25) | 261 | legacy spans marine+softdrinks |
| `brokenglass` (164) | 8,160 | `broken_glass` (3) | 107 | both span alcohol+softdrinks |
| `facemask` (183) | 7,910 | `face_mask` (79) | 17 | **different categories** — sanitary vs medical |
| `balloons` (178) | 2,491 | `balloon` (96) | 28 | legacy spans marine+other |
| `bagsLitter` (176) | 2,239 | `bags_litter` (15) | 161 | |
| `cableTie` (187) | 1,203 | `cable_tie` (95) | 17 | |
| `pullRing` (175) | 1,168 | `pull_ring` (10) | 5 | |
| `fishing_nets` (173) | 1,154 | `fishing_net` (69) | 4 | |
| `shotgun_cartridges` (193) | 725 | `shotgun_cartridge` (76) | 81 | |
| `overflowingBins` (188) | 524 | `overflowing_bin` (19) | 6 | **different categories** — other vs civic |
| `condoms` (158) | 366 | `condom` (115) | 1 | |
| `earSwabs` (166) | 294 | `ear_swabs` (106) | 19 | |
| `posters` (177) | 268 | `poster` (94) | 6 | |
| `buoys` (196) | 189 | `buoy` (63) | 0 | canonical twin is unused |
| `trafficCone` (171) | 136 | `traffic_cone` (90) | 1 | |

In every pair the **legacy key holds the overwhelming majority of the data** and the
canonical twin holds only post-migration tagging. That is the expected shape: history landed
on the legacy key, new tagging goes to the canonical one.

The variance is only naming style — camelCase (`plasticBags`, `cableTie`) or plural
(`straws`, `condoms`, `posters`) versus canonical snake_case singular.

### ⚠ One false positive — do NOT merge

| Key | Category | Items | |
|---|---|---:|---|
| `paper` (93) | other | 31,919 | **both canonical** |
| `papers` (120) | smoking | 6 | rolling papers — a genuinely different object |

My normalisation flagged these as duplicates because it strips a trailing `s`. They are
distinct objects in distinct categories and **must stay separate**. Any automated
duplicate-detection over this table needs this exception.

---

## Full review — all 62 legacy objects

`PROPOSED TARGET` is what `FixOrphanedTags` would do. **None of it is approved.**

| # | Legacy key | Items | Used in | Proposed target | Operation |
|---:|---|---:|---|---|---|
| 1 | `randomLitter` | 33,801 | other | other/**other** | merge → generic |
| 2 | `beer_can` | 26,488 | alcohol | alcohol/can | merge + type `beer` |
| 3 | `water_bottle` | 21,889 | softdrinks | softdrinks/bottle | merge + type `water` |
| 4 | `energy_can` | 20,931 | softdrinks | softdrinks/can | merge + type `energy` |
| 5 | `beer_bottle` | 18,074 | alcohol | alcohol/bottle | merge + type `beer` |
| 6 | `soda_can` | 18,034 | softdrinks | softdrinks/can | merge + type `soda` |
| 7 | `bottletops` | 13,018 | alcohol | alcohol/bottle_cap | merge |
| 8 | `plasticBags` | 12,946 | other | other/plastic_bag | merge |
| 9 | `cigarette_box` | 10,245 | smoking | smoking/box | merge + type `cigarette` |
| 10 | `dump` | 9,733 | other | dumping/dumping | merge + **CATEGORY MOVE** |
| 11 | `straws` | 8,438 / 226 | softdrinks / marine | softdrinks/straw · marine/**other** | merge + split |
| 12 | `brokenglass` | 8,015 / 145 | alcohol / softdrinks | alcohol/broken_glass · softdrinks/broken_glass | merge + split |
| 13 | `facemask` | 7,910 | sanitary | medical/face_mask | merge + **CATEGORY MOVE** |
| 14 | `fizzy_bottle` | 7,831 | softdrinks | softdrinks/bottle | merge + type `soda` |
| 15 | `wetwipes` | 6,052 | sanitary | sanitary/wipes | merge |
| 16 | `crisp_small` | 5,037 | food | food/crisp_packet | merge (size lost) |
| 17 | `mediumplastics` | 4,004 | marine | marine/macroplastics | merge |
| 18 | `spirits_bottle` | 3,457 | alcohol | alcohol/bottle | merge + type `spirits` |
| 19 | `sports_bottle` | 3,277 | softdrinks | softdrinks/bottle | merge + type `sports` |
| 20 | `balloons` | 1,856 / 635 | other / marine | other/balloon · marine/**other** | merge + split |
| 21 | `juice_carton` | 2,327 | softdrinks | softdrinks/carton | merge + type `juice` |
| 22 | `bagsLitter` | 2,239 | other | other/bags_litter | merge |
| 23 | `juice_bottle` | 1,940 | softdrinks | softdrinks/bottle | merge + type `juice` |
| 24 | `cableTie` | 1,203 | other | other/cable_tie | merge |
| 25 | `pullRing` | 1,168 | softdrinks | softdrinks/pull_ring | merge |
| 26 | `fishing_nets` | 1,154 | marine | marine/fishing_net | merge |
| 27 | `straw_packaging` | 1,111 | softdrinks | softdrinks/straw_wrapper | merge |
| 28 | `automobile` | 987 | other | vehicles/car_part | merge + **CATEGORY MOVE** |
| 29 | `milk_bottle` | 935 | softdrinks | softdrinks/bottle | merge + type `milk` |
| 30 | `milk_carton` | 874 | softdrinks | softdrinks/carton | merge + type `milk` |
| 31 | `wine_bottle` | 863 | alcohol | alcohol/bottle | merge + type `wine` |
| 32 | `filters` | 852 | smoking | smoking/rolling_filter | merge |
| 33 | `iceTea_bottle` | 851 | softdrinks | softdrinks/bottle | merge + type `tea` |
| 34 | `shotgun_cartridges` | 725 | marine | marine/shotgun_cartridge | merge |
| 35 | `icedTea_can` | 607 | softdrinks | softdrinks/can | merge + type `iced_tea` |
| 36 | `vapePen` | 582 | smoking | smoking/vape | merge + type `pen` |
| 37 | `hair_tie` | 546 | other | sanitary/**other** | merge → generic + **CATEGORY MOVE** |
| 38 | `rollingPapers` | 546 | smoking | smoking/papers | merge |
| 39 | `overflowingBins` | 524 | other | other/overflowing_bin | merge |
| 40 | `crisp_large` | 469 | food | food/crisp_packet | merge (size lost) |
| 41 | `tobaccopouch` | 434 | smoking | smoking/pouch | merge + type `tobacco` |
| 42 | `elec_small` | 385 | other | electronics/**other** | merge → generic + **CATEGORY MOVE** |
| 43 | `condoms` | 366 | sanitary | sanitary/condom | merge |
| 44 | `batteries` | 363 | other | electronics/battery | merge + **CATEGORY MOVE** |
| 45 | `toothpick` | 309 | sanitary | sanitary/**other** | merge → generic |
| 46 | `earSwabs` | 294 | sanitary | sanitary/ear_swabs | merge |
| 47 | `posters` | 268 | other | other/poster | merge |
| 48 | `menstrual` | 201 | sanitary | sanitary/sanitary_pad | merge |
| 49 | `buoys` | 189 | marine | marine/buoy | merge |
| 50 | `glass_jar` | 188 | food | food/jar | merge |
| 51 | `trafficCone` | 136 | other | other/traffic_cone | merge |
| 52 | `magazine` | 124 | other | other/**other** | merge → generic |
| 53 | `washingUp` | 102 | other | other/**other** | merge → generic |
| 54 | `elec_large` | 100 | other | electronics/**other** | merge → generic + **CATEGORY MOVE** |
| 55 | `vapeOil` | 74 | smoking | smoking/vape | merge + type `e_liquid_bottle` |
| 56 | `books` | 71 | other | other/**other** | merge → generic |
| 57 | `oil` | 69 | industrial | industrial/oil_container | merge |
| 58 | `ear_plugs` | 46 | other | sanitary/**other** | merge → generic + **CATEGORY MOVE** |
| 59 | `item` | 43 | art | art/**other** | merge → generic |
| 60 | `chemical` | 36 | industrial | industrial/chemical_container | merge |
| 61 | `life_buoy` | 11 | other | marine/buoy | merge + **CATEGORY MOVE** |
| 62 | `lego` | 3 | marine | other/**other** | merge + **CATEGORY MOVE** |

**Operation mix:** 36 plain merges · 19 merge+type · 10 category moves · **0 unmapped**.

---

## Decisions requiring sign-off

### 0. ⚠ Do NOT physically merge `photo_tags` rows (simulated, read-only)

Projecting all 74 mappings without writing anything:

| | Count |
|---|---:|
| Rows the mappings touch | 189,518 |
| **Logical duplicate groups after merge** | **705** |
| Rows in those groups | 1,454 |
| Items in those groups | 3,067 |
| Groups colliding with an existing canonical tag | 597 |
| Groups with more than one remapped row | 140 |
| Groups containing extra tags (material/brand/custom) | 317 |
| **Groups whose colliding rows have DIFFERENT extra-tag sets** | **280** |

Those 280 groups are the hazard. Two rows that become logically identical on
(photo, category, object, type) may still carry **different materials, brands or custom
tags**. Collapsing them would silently destroy those dimensions.

**Therefore the cleanup must repoint rows and preserve them as separate physical
`photo_tags` rows.** Row-level deduplication is a *separate, later* operation needing an
explicit dimension-aware rule. No `picked_up` conflicts were found.

### 1. ⚠ 39,123 items would collapse into a generic `other` object

This is the most consequential finding, and it is **not** what "fixing orphaned tags"
suggests. These legacy objects have **no canonical equivalent in `TagsConfig`**, so the
proposed mapping discards their meaning:

| Legacy key | Items | Becomes |
|---|---:|---|
| `randomLitter` | 33,801 | other/other |
| `hair_tie` | 546 | sanitary/other |
| `elec_small` | 385 | electronics/other |
| `toothpick` | 309 | sanitary/other |
| `magazine` | 124 | other/other |
| `washingUp` | 102 | other/other |
| `elec_large` | 100 | electronics/other |
| `books` | 71 | other/other |
| `ear_plugs` | 46 | sanitary/other |
| `item` | 43 | art/other |
| `lego` | 3 | other/other |
| `balloons` (marine part) | 635 | marine/other |
| `straws` (marine part) | 226 | marine/other |
| **`bag`** (canonical, marine) | **1,662** | marine/other |
| **`bottle`** (canonical, marine) | **1,070** | marine/other |
| **Total** | **39,123** | |

`randomLitter` alone is 33,801 items — arguably fine, since "random litter" *is* generic.
But `magazine`, `books`, `lego`, `toothpick`, `hair_tie` and `ear_plugs` are specific,
identifiable objects. Collapsing them into `other` is **irreversible information loss** in a
dataset used for peer-reviewed research.

**Options:** (a) accept the loss; (b) add canonical objects to `TagsConfig` for the ones
worth keeping (`magazine`, `book`, `lego`, `toothpick`, `hair_tie`, `ear_plugs`) and map to
those; (c) keep those legacy objects as first-class canonical objects and just give them
pivots. **Recommendation: (b)** — small `TagsConfig` additions preserve the data at trivial
cost.

### 2. Seventeen category moves

These reclassify data across categories and change what every category-level total reports.
**Ten come from legacy objects, seven from the canonical-but-wrong-category pairs above:**

| Legacy key | Items | Move |
|---|---:|---|
| industrial/`plastic` | 2,937 | industrial → other |
| sanitary/`gloves` | 2,562 | sanitary → medical |
| other/`dogshit` | 1,383 | other → pets |
| other/`dogshit_in_bag` | 649 | other → pets |
| other/`tyre` | 436 | other → vehicles |
| marine/`lighters` | 60 | marine → smoking |
| sanitary/`sanitiser` | 53 | sanitary → medical |

…plus the ten from legacy objects:

| Legacy key | Items | Move |
|---|---:|---|
| `dump` | 9,733 | other → dumping |
| `facemask` | 7,910 | sanitary → medical |
| `automobile` | 987 | other → vehicles |
| `hair_tie` | 546 | other → sanitary |
| `elec_small` | 385 | other → electronics |
| `batteries` | 363 | other → electronics |
| `elec_large` | 100 | other → electronics |
| `ear_plugs` | 46 | other → sanitary |
| `life_buoy` | 11 | other → marine |
| `lego` | 3 | marine → other |

Most look defensible (`dump` → dumping, `batteries` → electronics). `facemask`
sanitary → medical moves 7,910 items and is the largest; worth confirming that medical is
the intended home post-COVID. `lego` marine → other is odd given lego is a well-known
*marine* litter item — likely wrong.

### 3. Size cannot be preserved on crisps — the data model has no place for it

`crisp_small` (5,037) and `crisp_large` (469) both map to `food/crisp_packet`, merging two
distinct measurements into one.

**Correction:** an earlier version of this audit said `TagsConfig` supports a `sizes`
dimension so the distinction could be kept. That is wrong at the observation level:

- `TagsConfig` defines `crisp_packet` with **`materials` only** — no `sizes`.
- **`photo_tags` has no size or state column at all.** `UpdateTagsService` never records a
  selected size per observation.

So there is nowhere to put "small" once the objects are merged. Config can attach size
*states* to taxonomy pivots, but nothing persists a chosen size on an individual tag.

**Safest interim choice: keep `crisp_small` and `crisp_large` as distinct canonical objects**
until a real per-observation size model exists. Same logic applies to any other legacy pair
encoding a size (`rope_small/medium/large`, `styro_*`) if they resurface.

### 4. Naming convention

Canonical is **snake_case singular**. The legacy set contains camelCase (`plasticBags`,
`cableTie`, `randomLitter`, `iceTea_bottle`) and plurals (`straws`, `condoms`, `posters`).
Once merged the legacy keys disappear, so no rename of a *canonical* key is required —
which is the good outcome, because renaming canonical keys would break export headers that
researchers depend on.

### 5. Export header impact

After the merge, the CSV columns for all 62 legacy keys disappear and their quantities move
into the canonical columns. For consumers this is a **breaking change** — e.g. `plasticBags`
vanishes and `plastic_bag` jumps from 264 to 13,210. This needs an announcement, not a
silent swap. See `readme/PostMigration-2026-08.md` limitation 10.

### 6. Tag-picker exposure

Merging removes the legacy objects entirely, so they never become user-selectable — this is
strictly better than the pivot-only repair, which *would* expose all 62 in the tag picker
(`GetTagsController::getAllTags()` filters on `whereHas('categories')`).

---

### 7. Retiring a key is not a one-line change — `BrandsConfig` still references them

Adding canonical objects (decision 1) and retiring legacy ones has a wider blast radius than
the `litter_objects` table:

| Surface | Impact |
|---|---|
| **`app/Tags/BrandsConfig.php`** | Still references legacy keys: `plasticBags` (12×), `beer_can` (13×), `crisp_small` (4×), `crisp_large` (3×) |
| `AutoCreateBrandRelationships` | Reads `BrandsConfig` and `firstOrCreate`s object **and pivot** — would **recreate retired legacy objects** after any cleanup |
| `/api/tags` tag picker | New canonical objects become user-selectable |
| Translations | `resources/js/langs/*/litter.json` needs keys for anything new |
| Exports | Column set changes for every consumer |
| Achievements / reports | Any hardcoded key references |

**`BrandsConfig` must be updated in the same change as any key retirement**, or the next run
of the brand command silently undoes the cleanup.

---

## What is and isn't validated

**Structurally validated** against `olm_postmig_2` — all 74 mappings:

- Exactly 74 mappings covering exactly 74 pivotless pairs, touching exactly 189,518 rows.
- Every object/category/type/CLO id exists and resolves to the expected key.
- Every target CLO belongs to its stated (category, object) pair.
- Every affected pair matches exactly one mapping — no overlap, no gaps.
- No affected row has an existing type id that would be overwritten.

**Not validated — the target taxonomy itself.** Structural correctness says the mappings will
execute cleanly on this database. It says nothing about whether the destinations are right.
Decisions 0–3 and 7 are all semantic and remain open.

---

## Recommendation

1. **Decision 0 first** — the cleanup must repoint rows, not merge them. 280 groups would
   otherwise lose material/brand/custom-tag distinctions. This is a design constraint, not
   a preference.
2. **Decision 1** — adding ~8 canonical objects (incl. marine `bag`/`bottle` pivots) avoids
   almost all 39,123 items of information loss.
3. **Decision 3** — keep `crisp_small`/`crisp_large` split; there is nowhere to store size.
4. Review all 17 category moves, especially `facemask` (7,910) and `lego` (marine → other,
   probably wrong).
5. Update `BrandsConfig` in lockstep with any retirement (decision 7).
6. Only then write the manifest-driven cleanup command and rehearse on a disposable clone.

No production change is needed for the reported bug — the export fix already restores full
visibility. This audit is about taxonomy quality, and should proceed at its own pace.

---

## How this audit was produced

Read-only queries against `olm_postmig_2`, cross-referenced with `TagsConfig::get()` and the
mapping array read out of `FixOrphanedTags::buildMappings()` by reflection. No data was
modified. Classification logic: an object is *canonical* if its key appears in `TagsConfig`,
and *pivotless* if it has no `category_litter_object` row.
