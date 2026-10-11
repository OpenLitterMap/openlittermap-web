# Remaining tag migrations

Local snapshot: **11 October 2026, 15:26 Europe/Dublin**, database **`olm_postmig_7`**, branch commit **`4544e4ec`**. Production counts and completion are unverified.

**65 object keys remain proposed for remapping across 68 category/object combinations, affecting 156,355 photo-tag records.** Another **4 keys** need catalogue links rather than object migration, affecting **2,614 records**. Together: **69 keys, 72 combinations, 158,969 records, 243,292 litter items and 144,624 distinct photos**. Photo counts overlap across mappings and must not be summed.

This is a review list, not an approved batch execution plan. Proposed destinations and review decisions come from [the September audit](TagCleanupReview-2026-09-12.csv); record counts and catalogue checks were refreshed from the local database. All currently observed object/category pairs without a CLO are accounted for below. Extra-only observations are excluded. Valid pairs outside that audit are not a newly reviewed taxonomy.

| Work | Object keys | Category/object combinations | Records | Litter items |
| --- | ---: | ---: | ---: | ---: |
| Mapping approval pending | 48 | 51 | 148,651 | 228,847 |
| Disputed meaning or destination | 17 | 17 | 7,704 | 8,716 |
| Keep key; repair catalogue | 4 | 4 | 2,614 | 5,729 |

## Completed locally

| Source | Replacement | Type | Remaining source records | Local retirement recorded |
| --- | --- | --- | ---: | --- |
| `plasticBags` | `plastic_bag` | — | 0 | 14 September 2026 |
| `energy_can` | `can` | `energy` | 0 | 11 October 2026 |

Both source objects have recorded replacements and no remaining source observations. Energy-can SQL counts, collection totals, extras and aggregate photo XP matched the saved baseline; summary and processed-snapshot checks passed for 20,498 inferred affected photos. However, the subsequent Redis audit found 1,246 mismatched old-object, can-object and energy-type counters across global, location and user scopes. At that audit, the global old-object count was −20,931 instead of zero. Redis reconciliation remains outstanding; do not treat the SQL completion as a fully verified rollout.

## Proposed mappings that preserve category

All entries remain pending approval. “Catalogue present” means the destination object, category/object link (CLO), and requested type association exist locally; it is not release approval. The command retires the source object across **all** its categories. Shared sources such as `balloons`, `brokenglass` and `straws` must be reviewed together and migrated once. No remaining audited source observations have an existing type.

### alcohol

| Old object | New object | Type | Records | Items | Preparation or decision |
| --- | --- | --- | ---: | ---: | --- |
| `beer_bottle` | `bottle` | `beer` | 7,337 | 18,074 | Catalogue present; mapping approval pending |
| `beer_can` | `can` | `beer` | 19,275 | 26,488 | Catalogue present; mapping approval pending |
| `bottletops` | `bottle_cap` | — | 4,902 | 13,018 | Catalogue present; mapping approval pending |
| `brokenglass` | `broken_glass` | — | 3,370 | 8,015 | Catalogue present; mapping approval pending |
| `spirits_bottle` | `bottle` | `spirits` | 2,661 | 3,457 | Catalogue present; mapping approval pending |
| `wine_bottle` | `bottle` | `wine` | 638 | 863 | Catalogue present; mapping approval pending |

### marine

| Old object | New object | Type | Records | Items | Preparation or decision |
| --- | --- | --- | ---: | ---: | --- |
| `balloons` | `balloon` | — | 450 | 635 | Destination CLO missing |
| `buoys` | `buoy` | — | 145 | 189 | Catalogue present; mapping approval pending |
| `fishing_nets` | `fishing_net` | — | 894 | 1,154 | Catalogue present; mapping approval pending |
| `mediumplastics` | `medium_plastic` | — | 1,564 | 4,004 | Destination object missing; Destination CLO missing |
| `shotgun_cartridges` | `shotgun_cartridge` | — | 493 | 725 | Catalogue present; mapping approval pending |
| `straws` | `straw` | — | 175 | 226 | Destination CLO missing |

### other

| Old object | New object | Type | Records | Items | Preparation or decision |
| --- | --- | --- | ---: | ---: | --- |
| `bagsLitter` | `bags_litter` | — | 1,041 | 2,239 | Object XP changes by +20,151 total |
| `balloons` | `balloon` | — | 1,730 | 1,856 | Also needs marine/balloon CLO |
| `cableTie` | `cable_tie` | — | 1,021 | 1,203 | Catalogue present; mapping approval pending |
| `overflowingBins` | `overflowing_bin` | — | 517 | 524 | Catalogue present; mapping approval pending |
| `posters` | `poster` | — | 208 | 268 | Catalogue present; mapping approval pending |
| `randomLitter` | `random_litter` | — | 12,821 | 33,801 | Destination object missing; Destination CLO missing |
| `trafficCone` | `traffic_cone` | — | 116 | 136 | Catalogue present; mapping approval pending |

### sanitary

| Old object | New object | Type | Records | Items | Preparation or decision |
| --- | --- | --- | ---: | ---: | --- |
| `condoms` | `condom` | — | 190 | 366 | Catalogue present; mapping approval pending |
| `earSwabs` | `ear_swabs` | — | 172 | 294 | Catalogue present; mapping approval pending |
| `wetwipes` | `wipes` | — | 3,326 | 6,052 | Catalogue present; mapping approval pending |

### smoking

| Old object | New object | Type | Records | Items | Preparation or decision |
| --- | --- | --- | ---: | ---: | --- |
| `cigarette_box` | `box` | `cigarette` | 9,803 | 10,245 | Catalogue present; mapping approval pending |
| `filters` | `rolling_filter` | — | 603 | 852 | Catalogue present; mapping approval pending |
| `rollingPapers` | `papers` | — | 525 | 546 | Catalogue present; mapping approval pending |
| `tobaccopouch` | `pouch` | `tobacco` | 408 | 434 | Catalogue present; mapping approval pending |
| `vapeOil` | `vape` | `e_liquid_bottle` | 63 | 74 | Catalogue present; mapping approval pending |
| `vapePen` | `vape` | `pen` | 484 | 582 | Catalogue present; mapping approval pending |

### softdrinks

| Old object | New object | Type | Records | Items | Preparation or decision |
| --- | --- | --- | ---: | ---: | --- |
| `brokenglass` | `broken_glass` | — | 42 | 145 | Catalogue present; mapping approval pending |
| `fizzy_bottle` | `bottle` | `soda` | 6,448 | 7,831 | Catalogue present; mapping approval pending |
| `iceTea_bottle` | `bottle` | `tea` | 829 | 851 | Catalogue present; mapping approval pending |
| `icedTea_can` | `can` | `iced_tea` | 588 | 607 | Catalogue present; mapping approval pending |
| `juice_bottle` | `bottle` | `juice` | 1,862 | 1,940 | Catalogue present; mapping approval pending |
| `juice_carton` | `carton` | `juice` | 2,243 | 2,327 | Catalogue present; mapping approval pending |
| `milk_bottle` | `bottle` | `milk` | 901 | 935 | Catalogue present; mapping approval pending |
| `milk_carton` | `carton` | `milk` | 827 | 874 | Catalogue present; mapping approval pending |
| `pullRing` | `pull_ring` | — | 758 | 1,168 | Catalogue present; mapping approval pending |
| `soda_can` | `can` | `soda` | 16,462 | 18,034 | Catalogue present; mapping approval pending |
| `sports_bottle` | `bottle` | `sports` | 2,931 | 3,277 | Catalogue present; mapping approval pending |
| `straw_packaging` | `straw_wrapper` | — | 1,039 | 1,111 | Catalogue present; mapping approval pending |
| `straws` | `straw` | — | 7,368 | 8,438 | Also needs marine/straw CLO |
| `water_bottle` | `bottle` | `water` | 17,189 | 21,889 | Catalogue present; mapping approval pending |

`bagsLitter → bags_litter` changes the object score from 1 to 10 XP per item, implying +20,151 object XP for these 2,239 items. This is not a prediction of the whole-photo XP delta; all affected photos are rescored. The command reports XP differences but does not enforce approval.

## Proposed category moves

These 9 mappings require category-move support or a separately reviewed procedure. The current `olm:migrate-tag` command preserves category and cannot implement them. In particular, never retire a shared object globally just to move one category’s observations.

| Source category/object | Proposed destination category/object | Records | Items |
| --- | --- | ---: | ---: |
| `other/batteries` | `electronics/battery` | 291 | 363 |
| `other/dogshit` | `pets/dogshit` | 1,146 | 1,383 |
| `other/dogshit_in_bag` | `pets/dogshit_in_bag` | 506 | 649 |
| `other/dump` | `dumping/dumping` | 1,875 | 9,733 |
| `other/life_buoy` | `marine/buoy` | 6 | 11 |
| `other/tyre` | `vehicles/tyre` | 202 | 436 |
| `sanitary/facemask` | `medical/face_mask` | 7,742 | 7,910 |
| `sanitary/gloves` | `medical/gloves` | 2,422 | 2,562 |
| `sanitary/sanitiser` | `medical/sanitiser` | 42 | 53 |

## Disputed mappings

These 17 suggestions are **not approved for execution**. Preserve the existing meaning until a destination is agreed; generic `other` replacements can discard useful information.

| Source category/object | Disputed destination | Records | Items | Reason |
| --- | --- | ---: | ---: | --- |
| `art/item` | `art/other` | 42 | 43 | Would replace a specific object with generic other |
| `food/crisp_large` | `food/crisp_packet` | 431 | 469 | May lose size/material detail or change object meaning |
| `food/crisp_small` | `food/crisp_packet` | 4,654 | 5,037 | May lose size/material detail or change object meaning |
| `food/glass_jar` | `food/jar` | 131 | 188 | May lose size/material detail or change object meaning |
| `industrial/chemical` | `industrial/chemical_container` | 27 | 36 | May lose size/material detail or change object meaning |
| `industrial/oil` | `industrial/oil_container` | 57 | 69 | May lose size/material detail or change object meaning |
| `marine/lego` | `other/other` | 3 | 3 | Would replace a specific object with generic other |
| `other/automobile` | `vehicles/car_part` | 794 | 987 | Category and object interpretation need agreement |
| `other/books` | `other/other` | 38 | 71 | Would replace a specific object with generic other |
| `other/ear_plugs` | `sanitary/other` | 26 | 46 | Would replace a specific object with generic other |
| `other/elec_large` | `electronics/other` | 60 | 100 | Would replace a specific object with generic other |
| `other/elec_small` | `electronics/other` | 291 | 385 | Would replace a specific object with generic other |
| `other/hair_tie` | `sanitary/other` | 515 | 546 | Would replace a specific object with generic other |
| `other/magazine` | `other/other` | 93 | 124 | Would replace a specific object with generic other |
| `other/washingUp` | `other/other` | 79 | 102 | Would replace a specific object with generic other |
| `sanitary/menstrual` | `sanitary/sanitary_pad` | 179 | 201 | May lose size/material detail or change object meaning |
| `sanitary/toothpick` | `sanitary/other` | 284 | 309 | Would replace a specific object with generic other |

## Catalogue repairs without renaming

These 4 category/object pairs should retain their keys. Their CLO entries are absent locally. Prepare reviewed, targeted catalogue repairs; `olm:migrate-tag OLD OLD` cannot repair them. Do not run a broad production seeder as a shortcut.

| Category/object | Records | Items |
| --- | ---: | ---: |
| `industrial/plastic` | 666 | 2,937 |
| `marine/bag` | 1,161 | 1,662 |
| `marine/bottle` | 741 | 1,070 |
| `marine/lighters` | 46 | 60 |

## Refresh and execution

Refresh this list after each verified mapping. Recount `photo_tags` by category/object, compare those pairs with `category_litter_object`, and inspect recorded retirements plus saved quick tags. Keep the September audit as historical evidence; this list records the current local backlog.

Before another apply, reconcile Redis, approve the mapping, review application and translation references, and follow the [migration runbook](../PostTagMigrationClean.md#rehearsal-and-production-window). Preview with `php artisan olm:migrate-tag OLD NEW` and add `--type=TYPE` where listed. Add `--apply` only for the separately reviewed run with writes paused.
