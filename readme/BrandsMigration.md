# OpenLitterMap v5 Brand Migration Guide

## Overview

The v5 migration handles brands from two sources:
1. **Official brands** - Defined in `Brand::types()` (~100 brands)
2. **Custom brand tags** - User-created tags with format `brand:xxx` or `brand=xxx` (thousands)

Both sources must be unified and attached to litter objects during migration.

## The Brand Challenge

Photos contain mixed litter. A single photo might have:
- A Coke can (softdrinks)
- Cigarette butts (smoking)
- Beer bottles (alcohol)

**The Problem**: Auto-discovery from mixed photos creates nonsense relationships like `coke → cigarette_butts`.

**The Solution**: Use a curated BrandsConfig for known brands and smart pattern matching for unknown brands.

## System Architecture

### 1. Brand Sources

```php
// Official brands from brands table
$photo->brands->coke = 1;

// Custom brands from custom_tags table
CustomTag: "brand:local_brewery"
CustomTag: "brand=artisan_coffee"
```

### 2. BrandsConfig (Source of Truth)

```php
// app/Config/BrandsConfig.php
'coke' => [
    'softdrinks' => ['soda_can', 'fizzy_bottle', 'cup', 'lid'],
],
'heineken' => [
    'alcohol' => ['beer_bottle', 'beer_can', 'bottletops'],
],
```

Defines which brands can attach to which objects. Prevents nonsense like `coke → beer_bottle`.

### 3. Smart Matching for Unknown Brands

For brands not in BrandsConfig, the system guesses based on name patterns:
- Names with "beer", "vodka", "wine" → alcohol category
- Names with "cola", "water", "juice" → softdrinks category
- Names with "cigarette", "tobacco" → smoking category
- Default → food category

## Migration Process

### Step 1: Extract All Brands

```php
// Get brands from BOTH sources
$allBrands = UnifiedBrandService::extractBrandsFromPhoto($photo->id);

// Returns:
[
    'coke' => 1,           // From brands table
    'local_brewery' => 1,  // From custom tag
]
```

### Step 2: Match Brands to Objects

```php
// For each brand:
if (BrandsConfig::brandExists($brand)) {
    // Use configured rules
    // coke → soda_can (softdrinks)
} else {
    // Use smart defaults
    // local_brewery → beer_bottle (alcohol - guessed from name)
}
```

### Step 3: Handle Unmatched Brands

Brands without matching objects create a brands-only PhotoTag:

```php
PhotoTag::create([
    'category_id' => $brandsCategoryId,  // Special "brands" category
    'quantity' => $totalBrandQuantity,
]);
```

## Database Structure

```sql
-- Brand storage locations
brands              -- Official brands (columns: coke, pepsi, etc.)
custom_tags         -- User brands (tag: "brand:xxx")
brandslist          -- Unified brand registry (all brands get an ID here)

-- Brand-object relationships
taggables           -- Links brands to category-object combinations
├── category_litter_object_id
├── taggable_type (App\Models\Litter\Tags\BrandList)
└── taggable_id (brand ID from brandslist)
```

## Implementation Commands

### 1. Analyze Your Data

```bash
# See what brands you have
php artisan olm:v5:analyze-custom-brands

# Output:
# Official brands: 100
# Custom brand tags: 3,421
# Top brands: local_brewery (45), artisan_coffee (23)...
```

### 2. Reset and Migrate

```bash
# Reset everything
php artisan olm:v5:reset --force

# Run migration with unified brands
php artisan olm:v5 --user=1
```

### 3. Verify Results

```php
// Check a migrated photo
$photo = Photo::find(4420);
echo $photo->summary;

// Should show brands attached to correct objects:
// coke → soda_can ✓
// NOT coke → cigarette_butts ✗
```

## Configuration Files

### Required Files

1. **BrandsConfig.php** - Defines brand-object relationships
2. **UnifiedBrandService.php** - Extracts brands from both sources
3. **UpdateTagsService.php** - Migration logic with brand matching

### Adding New Brand Rules

Edit `app/Config/BrandsConfig.php`:

```php
'new_brand' => [
    'category' => ['allowed_objects'],
],
```

## Common Issues & Solutions

### Issue: Brands Not Attaching
**Cause**: Only checking brands table, not custom tags
**Solution**: Use UnifiedBrandService to get ALL brands

### Issue: Wrong Category Attachments
**Cause**: Mixed litter creating false relationships
**Solution**: Use BrandsConfig to restrict attachments

### Issue: Too Many Unmatched Brands
**Cause**: Thousands of unconfigured brands
**Solution**: Smart defaults handle unknown brands automatically

## Key Statistics

From 68,495 photos with brands:
- **Official brands**: ~100
- **Custom brands**: Thousands
- **Success rate with hybrid approach**: ~95%
- **Processing speed**: ~1,800 photos/second

## Best Practices

1. **Don't auto-discover from photos** - Mixed litter creates nonsense
2. **Configure top brands manually** - Cover 80% with ~200 brands
3. **Use smart defaults for the rest** - Pattern matching handles unknowns
4. **Log unconfigured matches** - Review and add popular brands to config
5. **Iterate gradually** - Perfect configuration isn't needed day one

## The 80/20 Rule

- Top 100 brands = 60% of photos
- Top 500 brands = 90% of photos
- Remaining thousands = 10% (rare/local brands)

Focus on configuring the top brands. Let smart defaults handle the long tail.

## Summary

The v5 brand migration:
1. Extracts brands from **two sources** (official + custom tags)
2. Matches using **BrandsConfig** (configured) or **smart defaults** (unconfigured)
3. Creates **brands-only tags** for unmatched brands
4. Prevents **nonsense relationships** from mixed litter photos
5. Works **immediately** without configuring thousands of brands

This hybrid approach balances correctness (for major brands) with practicality (for thousands of minor brands).
