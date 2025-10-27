# OpenLitterMap v5 Brand Migration Guide

## Overview

The v5 migration consolidates brands from two legacy sources into a unified system:
1. **Official brands** - Columns in the old `brands` table (~100 brands)
2. **Custom brand tags** - User-created entries in `custom_tags` table with format `brand:xxx` or `brand=xxx` (thousands)

All 2,686 unique brands are now unified in the `brandslist` table and need to be intelligently matched to litter objects.

Note: Brand entries like `7up=3` indicate the brand "7up" with a quantity of 3, not data errors.

## The Brand-Object Relationship Challenge

Photos contain mixed litter scenarios:
- **Single brand, single object**: Straightforward (e.g., just a Coke can)
- **Single brand, multiple objects**: Common for fast food (e.g., McDonald's with cup + packaging + lid)
- **Multiple brands, multiple objects**: Complex scenes (e.g., Coke can + Marlboro butts)

**The Problem**: Automatically discovering relationships from mixed photos can create incorrect associations like `coke → cigarette_butts`.

**The Solution**: A multi-phase discovery process based on actual usage patterns in the data.

## Migration Process

### Phase 1: 1-to-1 Discovery (DefineBrandRelationships)

Identifies clear relationships from photos with exactly ONE brand and ONE object:

```bash
php artisan olm:define-brand-relationships
```

**Results from production data:**
- Processes ~61,000 eligible photos
- Creates 611 unique brand-object pairs
- 93.4% success rate for eligible photos
- Covers 98 unique brands

### Phase 2: High-Usage Brands (≥50 photos)

```bash
php artisan olm:auto-create-brand-relationships --min-usage=50 --min-percentage=10 --apply
```

**Results:**
- Creates 138 relationships for 73 brands
- High confidence based on substantial data

### Phase 3: Medium-Usage Brands (≥10 photos)

```bash
php artisan olm:auto-create-brand-relationships --min-usage=10 --min-percentage=25 --apply
```

**Results:**
- Creates 446 relationships for 273 brands
- Medium confidence with higher threshold

### Phase 4: All Remaining Brands (≥1 photo)

```bash
php artisan olm:auto-create-brand-relationships --min-usage=1 --min-percentage=50 --apply
```

**Results:**
- Creates 2,340 relationships for 1,695 brands
- Lower confidence for rare brands
- Includes brands with only single occurrences

## Database Structure

```sql
-- Brand registry
brandslist                    -- All 2,686 brands with unique IDs
├── id
├── key (e.g., 'coke', 'marlboro')
└── timestamps

-- Brand-object relationships
taggables                     -- Links brands to category-object combinations
├── category_litter_object_id -- References pivot table
├── taggable_type            -- 'App\Models\Litter\Tags\BrandList'
├── taggable_id              -- Brand ID from brandslist
└── quantity                 -- Occurrence count

-- Pivot table
category_litter_object        -- Valid category-object combinations
├── category_id
└── litter_object_id
```

## Migration Commands Summary

### Complete Process
```bash
# 1. Reset if needed
php artisan olm:v5:reset --force

# 2. Discover 1-to-1 relationships
php artisan olm:define-brand-relationships

# 3. Create relationships for high-usage brands
php artisan olm:auto-create-brand-relationships --min-usage=50 --min-percentage=10 --apply

# 4. Create relationships for medium-usage brands
php artisan olm:auto-create-brand-relationships --min-usage=10 --min-percentage=25 --apply

# 5. Create relationships for all remaining brands
php artisan olm:auto-create-brand-relationships --min-usage=1 --min-percentage=50 --apply

# 6. Run the full migration
php artisan olm:v5
```

## Final Statistics (Production Data)

**Brand Coverage:**
- Total brands in system: 2,686
- Brands with defined relationships: ~2,139 (79.6%)
- Brands without relationships: ~547 (20.4%)

**Relationship Breakdown:**
- Phase 1: 611 relationships (98 brands)
- Phase 2: 138 relationships (73 brands)
- Phase 3: 446 relationships (273 brands)
- Phase 4: 2,340 relationships (1,695 brands)
- **Total: 3,535 brand-object relationships**

**Photo Coverage:**
- Photos with brands: 91,869
- Photos covered by relationships: ~85,000+ (>92%)
- Photos with undefined brands: ~6,000 (<8%)

## How the Migration Uses Relationships

During photo migration (`UpdateTagsService`):

1. **Check existing pivots**: Query `taggables` table for brand-object relationships
2. **First match wins**: Brand attaches to first object with a pivot relationship
3. **Fallback to quantity matching**: If no pivot exists, match by quantity
4. **Brands-only tag**: Unmatched brands create a brands-only PhotoTag
5. **Create relationship**: Successful matches create/update pivot for future use

## Common Scenarios

### High-Confidence Relationships (Many Photos)
```
McDonald's (appears in 10,322 photos):
- mcdonalds → cup (30.2% of photos)
- mcdonalds → packaging (28.1% of photos)
- mcdonalds → lid (11.8% of photos)

Marlboro (appears in 5,090 photos):
- marlboro → butts (93.8% of photos)
```

### Medium-Confidence Relationships (10-50 Photos)
```
7up (appears in 41 photos):
- 7up → soda_can (78.0% of photos)

Aquafina (appears in 24 photos):
- aquafina → water_bottle (70.8% of photos)
```

### Low-Confidence Relationships (1-5 Photos)
```
365 (appears in 1 photo):
- 365 → water_bottle (100% of 1 photo)
- 365 → lid (100% of 1 photo)
```

## Data Quality Considerations

**Confidence Levels by Phase:**
- **Phase 1 (1-to-1)**: Highest confidence - clear single brand/object relationships
- **Phase 2 (50+ photos)**: High confidence - substantial data for patterns
- **Phase 3 (10+ photos)**: Medium confidence - reasonable patterns emerge
- **Phase 4 (1+ photos)**: Low confidence - may reflect coincidental co-occurrence

**Trade-offs:**
- Better to have 80% solid relationships than none at all
- Brands without relationships fall back to brands-only tags (data preserved)
- Single-photo relationships may be noise but ensure no data loss

## Verification

After migration, verify brand attachments:

```sql
-- Check brand attachments
SELECT 
    b.key as brand,
    c.key as category,
    lo.key as object,
    COUNT(*) as occurrences
FROM photo_tag_extra_tags ptet
JOIN photo_tags pt ON ptet.photo_tag_id = pt.id
JOIN brandslist b ON ptet.tag_type_id = b.id
JOIN categories c ON pt.category_id = c.id
JOIN litter_objects lo ON pt.litter_object_id = lo.id
WHERE ptet.tag_type = 'brand'
GROUP BY b.key, c.key, lo.key
ORDER BY occurrences DESC
LIMIT 20;

-- Check brands without relationships
SELECT COUNT(*) as undefined_brands
FROM brandslist b
WHERE NOT EXISTS (
    SELECT 1 FROM taggables t 
    WHERE t.taggable_type = 'App\\Models\\Litter\\Tags\\BrandList' 
    AND t.taggable_id = b.id
);
```

## Summary

The v5 brand migration:
1. **Consolidates** all 2,686 brands into `brandslist` table
2. **Discovers** relationships through multi-phase analysis with varying thresholds
3. **Creates** 3,535 brand-object relationships covering 80% of brands
4. **Preserves** all data - undefined brands create brands-only tags
5. **Handles** edge cases with quantity matching and fallbacks

This approach balances data coverage with relationship quality, ensuring no user data is lost while creating meaningful brand-object associations where patterns exist.
