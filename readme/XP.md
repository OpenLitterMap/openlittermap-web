# OpenLitterMap — XP System

## XP Values

| Action | XP | Notes |
|--------|-----|-------|
| Upload a photo | **5** | Base XP, always awarded |
| Each litter object tagged | **1** | Multiplied by quantity |
| Each material tagged | **2** | Multiplied by parent tag's quantity |
| Each brand tagged | **3** | Brands have their own independent quantity |
| Each custom tag | **1** | Multiplied by parent tag's quantity |
| Picked up | **5** | Per object (×quantity) when `photo_tags.picked_up = true` |

### Special Object Overrides

Some objects award more than the default 1 XP per item:

| Object Key | XP per item |
|------------|-------------|
| `dumping_small` | 10 |
| `dumping_medium` | 25 |
| `dumping_large` | 50 |
| `bags_litter` | 10 |

These are litter size categories — tagging a large item rewards more because it takes more effort to document and pick up.

---

## Formula

```
Total XP = Upload + Objects + Materials + Brands + Custom Tags + Picked Up Bonus

Upload       = 5 (always)
Objects      = Σ(quantity × object_xp)         object_xp = 1 (or special override)
Materials    = Σ(parent_quantity × 2)           per material on each tag
Brands       = Σ(brand_quantity × 3)            brands use their OWN quantity
Custom Tags  = Σ(parent_quantity × 1)           per custom tag on each tag
Picked Up    = Σ(quantity × 5)                  per tag where picked_up=true AND has object
```

### Quantity Rules

- **Objects**: `quantity` is set by the user (e.g., "3 cigarette butts"). XP = `quantity × xp_per_object`.
- **Materials**: Set membership — each material on a tag uses the **parent tag's quantity** as the multiplier. If you tag 3 bottles with plastic and glass, that's `3×2 + 3×2 = 12` material XP.
- **Brands**: Independent quantities — each brand has its own quantity. If you tag Coca-Cola (qty 2) on a can, that's `2×3 = 6` brand XP regardless of the parent tag's quantity.
- **Custom tags**: Same as materials — use the parent tag's quantity as the multiplier.

### Extra-Tag-Only (Loose) Tags

PhotoTags with null CLO (brand-only, material-only, custom-only) receive **no object XP** — `XpCalculator::calculateFromFlatSummary()` only awards object XP when `object_id > 0`. They still earn their extra-tag XP:

- Brand-only tag: `brand_quantity × 3` XP
- Material-only tag: `parent_quantity × 2` XP
- Custom-only tag: `parent_quantity × 1` XP

These tags also do not count toward `totalLitter` (`GeneratePhotoSummaryService` only counts litter when `objectId > 0`).

---

## Example

A user photographs litter on the ground, tags it, and picks some up:

```
Tag 1: Cigarette Butt (qty 5, picked_up = true)
  → Object:    5 × 1 = 5 XP
  → Picked Up: 5 × 5 = 25 XP

Tag 2: Plastic Bottle (qty 2, picked_up = true), materials: [plastic], brands: [Coca-Cola qty 1]
  → Object:    2 × 1 = 2 XP
  → Material:  2 × 2 = 4 XP  (parent qty × material XP)
  → Brand:     1 × 3 = 3 XP  (brand's own qty × brand XP)
  → Picked Up: 2 × 5 = 10 XP

Tag 3: Large item (qty 1, picked_up = false)
  → Object:  1 × 50 = 50 XP  (special override)
  → Picked Up: 0 XP  (not picked up)

Calculation:
  Upload:      5
  Objects:     5 + 2 + 50 = 57
  Materials:   4
  Brands:      3
  Custom Tags: 0
  Picked Up:   25 + 10 = 35  (per-tag: qty × 5 where picked_up=true)
  ─────────────
  Total:       104 XP
```

---

## Levels

XP accumulates into levels. Thresholds are flat (not exponential).

| Level | XP Required | Title |
|-------|-------------|-------|
| 1 | 0 | Noob |
| 2 | 100 | Noobish |
| 3 | 500 | Post-Noob |
| 4 | 1,000 | Litter Wizard |
| 5 | 5,000 | Trash Warrior |
| 6 | 10,000 | Early Guardian |
| 7 | 15,000 | Trashmonster |
| 8 | 50,000 | Force of Nature |
| 9 | 100,000 | Planet Protector |
| 10 | 200,000 | Galactic Garbagething |
| 11 | 500,000 | Interplanetary |
| 12 | 1,000,000 | SuperIntelligent LitterMaster |

`LevelService::getUserLevel($xp)` returns: `level`, `title`, `xp_into_level`, `xp_for_next`, `xp_remaining`, `progress_percent`.

Frontend reads `user.next_level.title` and `user.next_level.progress_percent` for the XP bar.

---

## Admin XP

Admins earn **1 XP** per verification action (approve, delete, re-tag, reset). Awarded via `rewardXpToAdmin()` which increments the user's DB `xp` column and updates their Redis leaderboard score.

---

## When XP Is Processed

1. User uploads a photo → **5 upload XP** awarded immediately to `users.xp` + metrics (public photos only; school photos deferred)
2. User adds tags → `GeneratePhotoSummaryService` calculates tag XP (including per-tag picked_up bonus), stores in `photo.xp`
3. `TagsVerifiedByAdmin` event fires (immediate for all non-school users)
4. `ProcessPhotoMetrics` listener → `MetricsService::processPhoto()` adds full XP (upload + tag) to leaderboards and metrics

School students: Upload XP and tag XP are both deferred until teacher approval. `processPhoto()` → `doCreate()` handles everything in one pass.

---

## Critical Rule: Never Hardcode XP Values

**Always use `XpScore` enum** — never hardcode XP integers anywhere in PHP or return them as literals in API responses.

```php
// WRONG — hardcoded value will drift out of sync
'xp_awarded' => 5,

// RIGHT — single source of truth
'xp_awarded' => XpScore::Upload->xp(),
```

The `XpScore` enum (`app/Enums/XpScore.php`) is the **single source of truth** for all XP values. Use:
- `XpScore::Upload->xp()` — upload XP (5)
- `XpScore::Object->xp()` — default object XP (1)
- `XpScore::getObjectXp($key, $typeKey = null)` — object XP with special overrides. For dumping objects with a type (small/medium/large), the type determines XP (10/25/50)
- `XpScore::getTagXp($type)` — map string type to XP
- `XpScore::Brand->xp()`, `XpScore::Material->xp()`, `XpScore::CustomTag->xp()`, `XpScore::PickedUp->xp()`

Test assertions may use literal integers (they verify the enum works), but all production code must reference the enum.

---

## Key Files

| File | Purpose |
|------|---------|
| `app/Enums/XpScore.php` | XP multiplier values (single source of truth) |
| `app/Services/Tags/XpCalculator.php` | XP calculation from tags or summary |
| `app/Services/Tags/GeneratePhotoSummaryService.php` | Builds summary + calculates XP + applies per-tag picked-up bonus |
| `app/Listeners/Metrics/ProcessPhotoMetrics.php` | Listener that triggers MetricsService from TagsVerifiedByAdmin |
| `resources/js/views/General/Tagging/v2/useXpCalculator.js` | Frontend XP preview (mirrors backend logic with literal integers) |
| `config/levels.php` | Level thresholds |
| `app/Services/LevelService.php` | Maps XP to level info |
| `app/Helpers/helpers.php` | `rewardXpToAdmin()` |
| `tests/Feature/Tags/v2/CalculatePhotoXpTest.php` | XP calculation tests |

**Note:** The frontend `useXpCalculator.js` uses literal integers (e.g., `3` for brands, `5` for picked_up) because it has no access to the PHP enum. These must be kept in sync manually with `XpScore`.
