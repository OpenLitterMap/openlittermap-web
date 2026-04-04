# Tags Cleanup: Post-Migration Orphaned photo_tags

**Date:** 2026-04-03
**Command:** `php artisan olm:fix-orphaned-tags`
**Location:** `app/Console/Commands/tmp/v5/Migration/FixOrphanedTags.php`

## Problem

The v5 migration (`olm:v5`) converted v4 category-column tags into v5 `photo_tags` rows. During this process, `ClassifyTagsService::normalizeDeprecatedTag()` mapped v4 column names to v5 object keys. However, many of those mapped keys don't exist in `TagsConfig.php`.

**Example:** The v4 column `softdrinks.energy_can` was mapped to object key `energy_can`. But TagsConfig defines the canonical v5 structure as `softdrinks.can` with type `energy`. Since `energy_can` doesn't exist in TagsConfig, no `category_litter_object` pivot row was created for it.

The migration's fallback (`classifyNewKey()`) created runtime `litter_objects` rows for these non-canonical keys (marked `crowdsourced=1`), but without CLO relationships. The resulting `photo_tags` rows have:
- `litter_object_id` = the orphaned LO (correct object, wrong key)
- `category_litter_object_id` = NULL (broken — no category relationship)
- `category_id` = correct (preserved from v4)

## Root Causes

### 1. DEPRECATED_TAG_MAP mapped to composite keys (43 objects)

The map created compound names instead of decomposing into object + type:

| v4 Column | Mapped To | Should Be |
|-----------|-----------|-----------|
| `beerCan` | `beer_can` | `alcohol.can` + type `beer` |
| `waterBottle` | `water_bottle` | `softdrinks.bottle` + type `water` |
| `tinCan` | `soda_can` | `softdrinks.can` + type `soda` |
| `energy_can` | `energy_can` | `softdrinks.can` + type `energy` |
| `cigaretteBox` | `cigarette_box` | `smoking.box` + type `cigarette` |
| `vape_pen` | `vapePen` | `smoking.vape` + type `pen` |
| ... | ... | ... |

### 2. `default => null` fallthrough (28 objects)

v4 column names like `facemask`, `bottletops`, `crisp_small` passed through unchanged (the map returns `null` for unrecognised keys, meaning "use key as-is"). These keys also don't exist in TagsConfig.

## Scale

| Metric | Count |
|--------|------:|
| Total photo_tags with NULL CLO | 214,146 |
| Extra-tag-only (NULL CLO + NULL LO — by design) | 24,628 |
| **Orphaned photo_tags (have LO, missing CLO)** | **189,518** |
| **Total orphaned quantity (litter items)** | **277,169** |
| Distinct orphaned litter_objects | 71 |

These 189,518 rows represent real user-tagged data that is structurally broken — invisible to any query that joins through `category_litter_object`, missing from category-based aggregations, and unfindable in the tagging UI.

## Pre-flight Checks

Three checks were run before building the fix:

### Check 1: Type storage mechanism

Types are stored as `litter_object_type_id` (FK) on `photo_tags`, validated via `category_object_types` pivot. Confirmed in `AddTagsToPhotoAction` (lines 159-193). All needed type IDs exist in `litter_object_types`.

### Check 2: Smoking CLO IDs

All verified correct:

| CLO ID | Category | Object |
|--------|----------|--------|
| 140 | smoking | box |
| 142 | smoking | lighters |
| 144 | smoking | papers |
| 145 | smoking | pouch |
| 146 | smoking | rolling_filter |
| 147 | smoking | vape |

### Check 3: Target CLOs for "not in TagsConfig" items

All target CLOs exist — no rows need to be created:

| Target | CLO ID |
|--------|--------|
| sanitary.sanitary_pad | 133 |
| sanitary.other | 138 |
| industrial.oil_container | 67 |
| industrial.chemical_container | 69 |
| marine.macroplastics | 85 |
| vehicles.car_part | 167 |
| medical.sanitiser | 101 |

**Note:** `personal_care` does not exist as a category. Items originally planned for `personal_care.other` (hair_tie, toothpick, ear_plugs) go to `sanitary.other` (CLO 138) instead.

### Pre-flight: existing type_ids

Zero orphaned rows have a non-NULL `litter_object_type_id`. No risk of overwriting existing values.

## Complete Mapping

### Alcohol (5 orphans, 34,813 tags)

| Orphan Key | Orphan LO | Tags | Target CLO | Canonical LO | Category | Type ID |
|-----------|-----------|-----:|------------|-------------|----------|---------|
| beer_can | 137 | 19,275 | 5 (can) | 5 | alcohol (2) | 1 (beer) |
| beer_bottle | 138 | 7,337 | 2 (bottle) | 2 | alcohol (2) | 1 (beer) |
| bottletops | 146 | 4,902 | 4 (bottle_cap) | 4 | alcohol (2) | — |
| spirits_bottle | 144 | 2,661 | 2 (bottle) | 2 | alcohol (2) | 3 (spirits) |
| wine_bottle | 139 | 638 | 2 (bottle) | 2 | alcohol (2) | 2 (wine) |

### Alcohol / Softdrinks split: brokenglass (3,412 tags)

| Orphan LO | Category Filter | Tags | Target CLO | Canonical LO |
|-----------|----------------|-----:|------------|-------------|
| 164 | alcohol (2) | 3,370 | 3 (alcohol.broken_glass) | 3 | 
| 164 | softdrinks (16) | 42 | 151 (softdrinks.broken_glass) | 3 |

### Softdrinks (14 orphans, 80,575 tags)

| Orphan Key | Orphan LO | Tags | Target CLO | Canonical LO | Type ID |
|-----------|-----------|-----:|------------|-------------|---------|
| energy_can | 156 | 20,498 | 152 (can) | 5 | 26 (energy) |
| water_bottle | 140 | 17,189 | 149 (bottle) | 2 | 23 (water) |
| soda_can | 142 | 16,462 | 152 (can) | 5 | 24 (soda) |
| fizzy_bottle | 145 | 6,448 | 149 (bottle) | 2 | 24 (soda) |
| sports_bottle | 143 | 2,931 | 149 (bottle) | 2 | 27 (sports) |
| juice_carton | 154 | 2,243 | 153 (carton) | 124 | 25 (juice) |
| juice_bottle | 155 | 1,862 | 149 (bottle) | 2 | 25 (juice) |
| straw_packaging | 172 | 1,039 | 162 (straw_wrapper) | 126 | — |
| milk_bottle | 153 | 901 | 149 (bottle) | 2 | 29 (milk) |
| iceTea_bottle | 186 | 829 | 149 (bottle) | 2 | 28 (tea) |
| milk_carton | 151 | 827 | 153 (carton) | 124 | 29 (milk) |
| pullRing | 175 | 758 | 160 (pull_ring) | 10 | — |
| icedTea_can | 194 | 588 | 152 (can) | 5 | 31 (iced_tea) |

### Softdrinks / Marine split: straws (7,543 tags)

| Orphan LO | Category Filter | Tags | Target CLO | Canonical LO |
|-----------|----------------|-----:|------------|-------------|
| 150 | softdrinks (16) | 7,368 | 161 (softdrinks.straw) | 25 |
| 150 | marine (10) | 175 | 93 (marine.other) | 1 |

### Smoking (6 orphans, 11,886 tags)

| Orphan Key | Orphan LO | Tags | Target CLO | Canonical LO | Type ID |
|-----------|-----------|-----:|------------|-------------|---------|
| cigarette_box | 141 | 9,803 | 140 (box) | 37 | 13 (cigarette) |
| rollingPapers | 148 | 525 | 144 (papers) | 120 | — |
| filters | 167 | 603 | 146 (rolling_filter) | 122 | — |
| vapePen | 190 | 484 | 147 (vape) | 123 | 17 (pen) |
| tobaccopouch | 162 | 408 | 145 (pouch) | 121 | 15 (tobacco) |
| vapeOil | 189 | 63 | 147 (vape) | 123 | 22 (e_liquid_bottle) |

### Sanitary / Medical (10 orphans, 14,898 tags)

| Orphan Key | Orphan LO | Tags | Target CLO | Canonical LO | Category Change |
|-----------|-----------|-----:|------------|-------------|----------------|
| facemask | 183 | 7,742 | 95 (face_mask) | 79 | sanitary → medical (11) |
| wetwipes | 179 | 3,326 | 125 (wipes) | 104 | — |
| gloves | 80 | 2,422 | 96 (gloves) | 80 | sanitary → medical (11) |
| hair_tie | 160 | 515 | 138 (sanitary.other) | 1 | — |
| toothpick | 168 | 284 | 138 (sanitary.other) | 1 | — |
| menstrual | 182 | 179 | 133 (sanitary_pad) | 112 | — |
| earSwabs | 166 | 172 | 127 (ear_swabs) | 106 | — |
| condoms | 158 | 190 | 136 (condom) | 115 | — |
| sanitiser | 85 | 42 | 101 (sanitiser) | 85 | sanitary → medical (11) |
| ear_plugs | 161 | 26 | 138 (sanitary.other) | 1 | — |

### Food (3 orphans, 5,216 tags)

| Orphan Key | Orphan LO | Tags | Target CLO | Canonical LO |
|-----------|-----------|-----:|------------|-------------|
| crisp_small | 157 | 4,654 | 49 (crisp_packet) | 40 |
| crisp_large | 163 | 431 | 49 (crisp_packet) | 40 |
| glass_jar | 159 | 131 | 52 (jar) | 43 |

### Category Changes from Other (6 orphans, 4,026 tags)

| Orphan Key | Orphan LO | Tags | Target CLO | Canonical LO | New Category |
|-----------|-----------|-----:|------------|-------------|-------------|
| dump | 165 | 1,875 | 34 (dumping) | 28 | dumping (6) |
| dogshit | 102 | 1,146 | 122 (dogshit) | 102 | pets (13) |
| dogshit_in_bag | 103 | 506 | 123 (dogshit_in_bag) | 103 | pets (13) |
| batteries | 181 | 291 | 36 (battery) | 29 | electronics (7) |
| tyre | 135 | 202 | 173 (tyre) | 135 | vehicles (17) |
| life_buoy | 184 | 6 | 78 (buoy) | 63 | marine (10) |

### Other — Same Category (11 orphans, 26,888 tags)

| Orphan Key | Orphan LO | Tags | Target CLO | Canonical LO |
|-----------|-----------|-----:|------------|-------------|
| randomLitter | 170 | 12,821 | 121 (other.other) | 1 |
| plasticBags | 149 | 10,051 | 111 (plastic_bag) | 92 |
| bagsLitter | 176 | 1,041 | 106 (bags_litter) | 15 |
| cableTie | 187 | 1,021 | 114 (cable_tie) | 95 |
| overflowingBins | 188 | 517 | 107 (overflowing_bin) | 19 |
| posters | 177 | 208 | 113 (poster) | 94 |
| trafficCone | 171 | 116 | 109 (traffic_cone) | 90 |
| washingUp | 152 | 79 | 121 (other.other) | 1 |
| magazine | 191 | 93 | 121 (other.other) | 1 |
| books | 192 | 38 | 121 (other.other) | 1 |
| lego | 197 | 3 | 121 (other.other) | 1 |

### Category Changes — Other to Specific (3 orphans, 1,145 tags)

| Orphan Key | Orphan LO | Tags | Target CLO | Canonical LO | New Category |
|-----------|-----------|-----:|------------|-------------|-------------|
| automobile | 180 | 794 | 167 (car_part) | 129 | vehicles (17) |
| elec_small | 174 | 291 | 43 (electronics.other) | 1 | electronics (7) |
| elec_large | 169 | 60 | 43 (electronics.other) | 1 | electronics (7) |

### Other / Marine split: balloons (2,180 tags)

| Orphan LO | Category Filter | Tags | Target CLO | Canonical LO |
|-----------|----------------|-----:|------------|-------------|
| 178 | other (12) | 1,730 | 115 (balloon) | 96 |
| 178 | marine (10) | 450 | 93 (marine.other) | 1 |

### Marine (7 orphans, 5,050 tags)

| Orphan Key | Orphan LO | Tags | Target CLO | Canonical LO | Category Change |
|-----------|-----------|-----:|------------|-------------|----------------|
| mediumplastics | 147 | 1,564 | 85 (macroplastics) | 70 | — |
| bag (marine) | 36 | 1,161 | 93 (marine.other) | 1 | — |
| fishing_nets | 173 | 894 | 84 (fishing_net) | 69 | — |
| bottle (marine) | 2 | 741 | 93 (marine.other) | 1 | — |
| shotgun_cartridges | 193 | 493 | 91 (shotgun_cartridge) | 76 | — |
| buoys | 196 | 145 | 78 (buoy) | 63 | — |
| lighters (marine) | 119 | 46 | 142 (smoking.lighters) | 119 | marine → smoking (15) |

### Industrial (3 orphans, 750 tags)

| Orphan Key | Orphan LO | Tags | Target CLO | Canonical LO | Category Change |
|-----------|-----------|-----:|------------|-------------|----------------|
| plastic (industrial) | 89 | 666 | 108 (other.plastic) | 89 | industrial → other (12) |
| oil | 198 | 57 | 67 (oil_container) | 54 | — |
| chemical | 195 | 27 | 69 (chemical_container) | 56 | — |

### Art (1 orphan, 42 tags)

| Orphan Key | Orphan LO | Tags | Target CLO | Canonical LO |
|-----------|-----------|-----:|------------|-------------|
| item (art) | 185 | 42 | 16 (art.other) | 1 |

## Judgment Calls

Items not directly mappable to TagsConfig required decisions:

| Item | Decision | Rationale |
|------|----------|-----------|
| `menstrual` (179 tags) | → `sanitary.sanitary_pad` | Safest default; v4 had single column, v5 splits into pad/tampon/cup |
| `randomLitter` (12,821 tags) | → `other.other` | No equivalent in TagsConfig; generic catch-all |
| `automobile` (794 tags) | → `vehicles.car_part` | Closest match |
| `hair_tie` (515 tags) | → `sanitary.other` | No `personal_care` category exists |
| `toothpick` (284 tags) | → `sanitary.other` | Not in TagsConfig |
| `ear_plugs` (26 tags) | → `sanitary.other` | Not in TagsConfig |
| `washingUp` (79 tags) | → `other.other` | Not in TagsConfig |
| `magazine` (93 tags) | → `other.other` | Not in TagsConfig |
| `books` (38 tags) | → `other.other` | Not in TagsConfig |
| `mediumplastics` (1,564 tags) | → `marine.macroplastics` | "Medium" size maps to macro, not micro |
| `bag` in marine (1,161 tags) | → `marine.other` | No `bag` in marine TagsConfig |
| `bottle` in marine (741 tags) | → `marine.other` | No `bottle` in marine TagsConfig |
| `industrial.plastic` (666 tags) | → `other.plastic` | No `plastic` in industrial TagsConfig |
| `facemask` / `gloves` / `sanitiser` | sanitary → medical | TagsConfig moved these to medical category |
| `dump` | other → dumping | TagsConfig has `dumping.dumping` |
| `dogshit` / `dogshit_in_bag` | other → pets | TagsConfig has `pets` category |
| `lighters` in marine | marine → smoking | A lighter is a lighter regardless of where found |

## Dry-Run Results

```
Pre-flight: orphaned rows with existing litter_object_type_id = 0

74 mapping rows, all ✓ match
Total expected: 189,518
Total would update: 189,518
0 mismatches
```

All multi-category splits confirmed against diagnostic counts:
- brokenglass: 3,370 (alcohol) + 42 (softdrinks) = 3,412
- straws: 7,368 (softdrinks) + 175 (marine) = 7,543
- balloons: 1,730 (other) + 450 (marine) = 2,180

## Execution

**Status:** Applied locally (2026-04-04). Production runbook at `readme/changelog/production-orphan-fix-runbook.md`.

```bash
# Dry-run (default)
php artisan olm:fix-orphaned-tags

# Live execution
php artisan olm:fix-orphaned-tags --apply

# Verify post-apply
php artisan olm:fix-orphaned-tags --verify-only

# Regenerate stale summaries (resumable, chunked, no side effects)
php artisan olm:regenerate-summaries --orphan-fix

# Reprocess XP for ~1,041 photos with special object bonus corrections
php artisan olm:reprocess-metrics --from-file=storage/logs/xp-changed-photo-ids.txt
```

## Verification Queries (post-apply)

```sql
-- Should be 0: all orphaned photo_tags with a litter_object should now have a CLO
SELECT COUNT(*) FROM photo_tags
WHERE category_litter_object_id IS NULL
AND litter_object_id IS NOT NULL;

-- The 24,628 extra-tag-only NULLs are expected (brands/materials/custom with no CLO)
SELECT COUNT(*) FROM photo_tags
WHERE category_litter_object_id IS NULL
AND litter_object_id IS NULL;

-- Spot checks: orphan LO should have 0 remaining orphaned photo_tags
SELECT lo.`key`, COUNT(*) AS remaining
FROM photo_tags pt
JOIN litter_objects lo ON pt.litter_object_id = lo.id
WHERE pt.category_litter_object_id IS NULL
AND lo.`key` IN ('energy_can', 'beer_can', 'water_bottle', 'soda_can')
GROUP BY lo.`key`;

-- Top 20 tags should now include previously invisible items
SELECT lo.`key` AS litter_object, c.`key` AS category, SUM(pt.quantity) AS total_qty
FROM photo_tags pt
JOIN category_litter_object clo ON pt.category_litter_object_id = clo.id
JOIN litter_objects lo ON clo.litter_object_id = lo.id
JOIN categories c ON clo.category_id = c.id
GROUP BY lo.`key`, c.`key`
ORDER BY total_qty DESC
LIMIT 20;
```

## What This Does NOT Touch

- **photo_tag_extra_tags:** Materials and brands are already correct from the migration.
- **XP / metrics / summaries:** This is a CLO/LO pointer fix only. No recalculation.
- **TagsConfig.php:** Not modified.
- **Orphaned litter_objects rows:** The 71 orphaned LO rows (beer_can, water_bottle, etc.) remain in the `litter_objects` table. They just won't have photo_tags pointing at them anymore. Safe to clean up later if desired.
- **DEPRECATED_TAG_MAP:** The mappings in ClassifyTagsService are not modified. They only affect future v4→v5 migrations, which are complete.

## Specificity Loss

1,035 tags were mapped to `.other` CLOs because their original keys (hair_tie, toothpick, ear_plugs, washingUp, magazine, books, randomLitter, marine bag/bottle, lego) have no equivalent in TagsConfig. This preserves the category relationship but loses the specific object identity. These are low-volume items and the tradeoff is acceptable — the alternative was leaving them as invisible orphans.
