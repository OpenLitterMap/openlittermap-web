# OpenLitterMap v5 Brand Migration Process

## Executive Summary

The v5 migration consolidates **2,452 unique brands** from two legacy sources into a unified system with AI-validated brand-object relationships. This document reflects the complete migration strategy with all lessons learned during implementation.

## Current State (November 2024)

### Brand Sources
- **Total unique brands found**: 2,452
- **Official brands table**: 100 brands (via `photos.brands_id`)
- **Custom brand tags**: 2,383 brands (via `custom_tags.tag` containing "brand")
- **Overlap**: 31 brands exist in both sources
- **CSV loaded**: 2,400 brands (small discrepancy due to special characters)

### Photo Coverage
- **Photos with brands**: 88,688 (17.7% of ~500,000 total photos)
- **Photos with official brands**: ~68,495
- **Photos with custom brand tags**: ~20,193
- **Total brand-object relationships**: 9,655
- **Average objects per brand**: 3.9

### Object Distribution
- **Total unique objects in system**: 146
- **Categories**: 13 (softdrinks, alcohol, food, smoking, coffee, material, etc.)
- **Most common object**: softdrinks.energy_can (18.6% of photos)

## Migration Architecture

### Database Structure

```sql
-- Target: Consolidated brand registry
brandslist                    
├── id (primary key)
├── key (e.g., 'adidas', 'McDonald\'s', '7-Eleven')  -- CASE-SENSITIVE
└── timestamps

-- Source 1: Official brands (100 brands)
photos.brands_id → brands table

-- Source 2: Custom tags (2,383 brands)
custom_tags.tag WHERE tag LIKE '%brand%'
-- Formats: 'brand:coke', 'brand=pepsi', 'brand:coke=3'

-- Future: Brand-object relationships
taggables                     
├── category_litter_object_id
├── taggable_type ('App\Models\Litter\Tags\BrandList')
├── taggable_id (brand ID from brandslist)
└── quantity
```

### Important: Brand Key Preservation

Brand keys must be preserved EXACTLY as they exist in `brandslist`:
- **Case-sensitive**: 'adidas' ≠ 'Adidas' ≠ 'ADIDAS'
- **Special characters preserved**: "McDonald's", "7-Eleven", "Abbott Laboratories"
- **No normalization during extraction or validation**

## Migration Process

### Phase 1: Data Extraction

**Command**: `php artisan olm:extract-brands`

**Process**:
1. Queries photos with EITHER `brands_id` OR custom brand tags
2. Extracts brands from both sources preserving original keys
3. Handles custom tag formats: `brand:xxx`, `brand=xxx`, `brand:xxx=3`
4. Collects ALL objects in system for context
5. Tracks co-occurrence counts and percentages

**Outputs**:
- `brand-relationships-*.csv` - All brand-object pairs with frequencies
- `objects-catalog-*.csv` - Complete catalog of all 146 objects
- `brand-summary-*.json` - Now includes ALL brands (not just top 100)

**Key Statistics**:
```
Top Brands by Volume:
1. redbull     - 12,529 photos [official]
2. mcdonalds   - 10,267 photos [both sources]
3. heineken    - 6,593 photos [official]
4. coke        - 5,712 photos [official]
5. marlboro    - 5,061 photos [official]

Brand Distribution:
- 1 photo:        1,141 brands (46.5%)
- 2-9 photos:     853 brands (34.8%)
- 10-99 photos:   372 brands (15.2%)
- 100-999 photos: 74 brands (3.0%)
- 1000+ photos:   12 brands (0.5%)
```

### Phase 2: AI Validation

**Command**: `php artisan olm:validate-brands`

**Critical Validation Rules** (Updated After Testing):
- ✅ **VALID**: Brand MANUFACTURES or SELLS the item
- ❌ **INVALID**: Items just "found near" brand products in litter
- **PERCENTAGE ANALYSIS**: High percentages (80-100%) are strong validation signals

**Percentage Interpretation Guidelines**:
- **80-100% of brand photos**: EXTREMELY STRONG signal this is their product - likely VALID
- **60-79% of brand photos**: Strong indicator - investigate if it's their main product
- **40-59% of brand photos**: Moderate signal - could be their product line
- **20-39% of brand photos**: Weak signal - needs other evidence
- **<20% of photos**: Likely just coincidental litter - probably INVALID

**Examples of Correct Validation**:
```
adidas + other.clothing = VALID (they manufacture clothing)
adidas + softdrinks.energy_can = INVALID (they don't make drinks)
McDonald's + food.paperfoodpackaging = VALID (their branded packaging)
McDonald's + smoking.butts = INVALID (they don't sell cigarettes)
abant + softdrinks.waterbottle (100%) = VALID (likely a water brand)
unknown_brand + item (90%+) = VALID (likely their specialized product)
```

**Cost**: ~$0.005 per brand × 2,452 brands = ~$12.50 total

**Validation Testing Workflow**:
```bash
# 1. Test individual brands first
php artisan olm:validate-brands --brand=adidas
php artisan olm:validate-brands --brand=coke
php artisan olm:validate-brands --brand="McDonald's"  # Note: exact case

# 2. Test by letter (case-insensitive first character)
php artisan olm:validate-brands --letter=a --dry-run  # Preview
php artisan olm:validate-brands --letter=a            # Execute

# 3. Process all brands
php artisan olm:validate-brands --all
```

### Phase 3: Configuration Generation

**Command**: `php artisan olm:validate-brands --export`

**Output**: `BrandsConfig_generated.php`

```php
class BrandsConfig {
    const BRAND_OBJECTS = [
        // CLOTHING BRANDS - Only clothing items
        'adidas' => [
            'other' => ['clothing'],
        ],
        'Nike' => [
            'other' => ['clothing'],
        ],
        
        // BEVERAGE BRANDS - Only drink containers
        'coke' => [
            'softdrinks' => ['tincan', 'fizzydrinkbottle', 'bottlelid'],
        ],
        
        // TOBACCO BRANDS - Only smoking items
        'marlboro' => [
            'smoking' => ['butts', 'cigarettebox', 'smoking_plastic'],
        ],
        
        // FAST FOOD - Their branded packaging
        'mcdonalds' => [
            'food' => ['paperfoodpackaging', 'plasticfoodpackaging'],
            'softdrinks' => ['paper_cups', 'plastic_lids'],
            'coffee' => ['coffeecups'],
        ],
        
        // RETAILERS - Many private label products
        'albertheijn' => [
            'softdrinks' => ['waterbottle', 'juice_carton'],
            'food' => ['packaging', 'sweetwrappers'],
            'other' => ['plastic_bags'],
            // ... many more they sell
        ],
    ];
}
```

## Lessons Learned & Issues Resolved

### Issue 1: Over-Inclusive AI Validation
**Problem**: Initial prompt marked everything as valid (adidas → beer cans ✅)
**Solution**: Stricter prompt requiring MANUFACTURE/SELL relationship

### Issue 2: Brand Key Normalization
**Problem**: Converting to lowercase broke database matching
**Solution**: Preserve exact keys from `brandslist` table

### Issue 3: Quoted Brand Names in CSV
**Problem**: Brands with spaces exported as `"7-Eleven"` with quotes
**Solution**: Handle quoted strings in CSV parsing

### Issue 4: Missing Brands in Summary
**Problem**: Only top 100 brands in summary, missing low-volume brands
**Solution**: Export ALL brands in `brands_by_photo_count` array

### Issue 5: Custom Tag Formats
**Problem**: Various formats: `brand:coke`, `brand=pepsi`, `brand:coke=3`
**Solution**: Regex extraction handling all formats

### Issue 6: High-Percentage Relationships Rejected
**Problem**: Unknown brands with 100% association to one object were marked invalid
**Solution**: Added percentage interpretation guidelines - high percentages are strong validation signals

## Complete Migration Workflow

```bash
# 1. Extract all brand-object relationships
php artisan olm:extract-brands
# Verify: Should show 2,452 unique brands

# 2. Test validation with strict individual brands
php artisan olm:validate-brands --brand=adidas
# Verify: Only other.clothing should be valid

php artisan olm:validate-brands --brand=marlboro  
# Verify: Only smoking items should be valid

# 3. Test letter-based processing
php artisan olm:validate-brands --letter=z --dry-run
# Should show few brands

php artisan olm:validate-brands --letter=a --dry-run
# Should show ~47 brands including adidas, amstel, etc.

# 4. Process all brands (after successful tests)
php artisan olm:validate-brands --all --min-count=2

# 5. Generate configuration
php artisan olm:validate-brands --export

# 6. Review generated config
cat storage/app/BrandsConfig_generated.php

# 7. Merge into production
cp storage/app/BrandsConfig_generated.php app/Tags/BrandsConfig.php
# Manual review and adjustments

# 8. Run actual migration
php artisan olm:v5
```

## Validation Quality Guidelines

### High-Confidence Validation (Auto-Approve)
- **Clothing brands** → clothing items only
- **Beverage brands** → drink containers only
- **Tobacco brands** → smoking items only
- **Electronics brands** → NO litter items (unless packaging)
- **Unknown brands with 80%+ association** → Likely their product

### Medium-Confidence (Review Recommended)
- **Fast food** → food packaging, cups, straws
- **Confectionery** → candy wrappers, chocolate packaging
- **Coffee chains** → coffee cups, lids, stirrers

### Special Cases
- **Retailers** (Tesco, Albert Heijn) → Many items (private label products)
- **Parent companies** → Include subsidiary products
- **Regional brands** → May need manual research
- **Unknown brands** → Use percentage as primary indicator

## Cost Optimization Strategies

### Process by Volume Tiers
```bash
# Tier 1: Very high volume (1000+ photos) - Most important
php artisan olm:validate-brands --min-photos=1000  # ~12 brands, $0.06

# Tier 2: High volume (100-999 photos)
php artisan olm:validate-brands --min-photos=100 --max-photos=999  # ~74 brands, $0.37

# Tier 3: Medium volume (10-99 photos)  
php artisan olm:validate-brands --min-photos=10 --max-photos=99  # ~372 brands, $1.86

# Tier 4: Low volume (2-9 photos)
php artisan olm:validate-brands --min-photos=2 --max-photos=9  # ~853 brands, $4.27

# Tier 5: Single photo brands (optional)
php artisan olm:validate-brands --min-photos=1 --max-photos=1  # ~1,141 brands, $5.71
```

### Process Alphabetically
```bash
for letter in {a..z}; do
    echo "Processing letter: $letter"
    php artisan olm:validate-brands --letter=$letter
    sleep 2  # Rate limiting
done
```

## Verification Queries

```sql
-- Check brand extraction coverage
SELECT 
    COUNT(DISTINCT b.key) as brands_with_relationships,
    (SELECT COUNT(*) FROM brandslist) as total_brands,
    ROUND(COUNT(DISTINCT b.key) * 100.0 / (SELECT COUNT(*) FROM brandslist), 1) as coverage_percent
FROM brandslist b
WHERE EXISTS (
    SELECT 1 FROM taggables t 
    WHERE t.taggable_type = 'App\\Models\\Litter\\Tags\\BrandList' 
    AND t.taggable_id = b.id
);

-- Verify brand key preservation
SELECT key, COUNT(*) as count
FROM brandslist
WHERE key != LOWER(key)  -- Has uppercase letters
   OR key LIKE '%\'%'     -- Has apostrophes
   OR key LIKE '%-%'      -- Has hyphens
   OR key LIKE '% %'      -- Has spaces
LIMIT 20;
-- Should show brands like "McDonald's", "7-Eleven", etc.

-- Check validation results
SELECT 
    JSON_EXTRACT(validation_result, '$.brand') as brand,
    JSON_EXTRACT(validation_result, '$.brand_type') as type,
    JSON_LENGTH(JSON_EXTRACT(validation_result, '$.valid')) as valid_count,
    JSON_LENGTH(JSON_EXTRACT(validation_result, '$.invalid')) as invalid_count
FROM brand_validations
ORDER BY valid_count DESC
LIMIT 20;
```

## File Format Reference

### brand-relationships-*.csv
```csv
Brand,Category,Object,Count,Brand_Photo_Count,Percentage,Rank_In_Brand
adidas,other,clothing,4,27,14.8,1
adidas,softdrinks,energy_can,5,27,18.5,2
"7-Eleven",food,sweetwrappers,10,45,22.2,1
```
Note: Brands with spaces are quoted

### brand-validations/*.json
```json
{
  "brand": "adidas",
  "brand_type": "clothing",
  "valid": ["other.clothing"],
  "invalid": [
    "softdrinks.energy_can",
    "alcohol.beercan",
    "smoking.butts"
  ],
  "notes": "Adidas manufactures sportswear and athletic accessories"
}
```

### brand-summary-*.json
```json
{
  "generated_at": "2024-11-09T14:56:14Z",
  "photos_processed": 88688,
  "total_brands": 2452,
  "brands_by_photo_count": [
    // Now includes ALL 2,452 brands, not just top 100
  ],
  "top_100_brands": [
    // Kept for backward compatibility
  ]
}
```

## Success Metrics

### Coverage
- ✅ **2,452** brands extracted (100% of discovered brands)
- ✅ **88,688** photos processed
- ✅ **9,655** brand-object relationships identified

### Validation Quality Indicators
- **Expected approval rate**: 20-30% for most brands
- **Clothing brands**: Should have 1-3 valid items
- **Beverage brands**: Should have 3-10 valid items
- **Retailers**: Should have 20-50+ valid items
- **Unknown brands with 80%+**: Should be validated as their products
- **Red flag**: If everything is valid, prompt is too inclusive

### Processing Metrics
- **Cost per brand**: ~$0.005
- **Processing time**: ~1 second per brand
- **Total estimated cost**: $12.50
- **Total estimated time**: ~40 minutes for all brands

## Next Steps

1. ✅ **Complete**: Extract all 2,452 brands with proper key preservation
2. **In Progress**: Validate with percentage-aware AI prompts
3. **TODO**: Review high-volume brands manually
4. **TODO**: Generate final BrandsConfig.php
5. **Future**:
    - Continuous learning from new photos
    - Consolidate duplicate/similar brands in brandslist
    - Add image validation for ambiguous cases ($200-300 extra)

---

*Last Updated: November 9, 2024*
*Status: Enhanced validation to recognize high-percentage relationships*
*Key Learning: High percentage (80%+) associations are strong indicators of actual brand products*
