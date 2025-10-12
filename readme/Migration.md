# OpenLitterMap v4 to v5 Migration Guide

## Overview

This guide documents the migration process from OpenLitterMap's v4 tagging system (category-based columns) to the v5 system (normalized PhotoTags with relationships). The migration preserves all existing data while establishing a more flexible, maintainable structure.

## Architecture Changes

### v4 Structure (Legacy)
```
photos
├── smoking_id → smoking table (columns: butts, lighters, cigaretteBox...)
├── alcohol_id → alcohol table (columns: beerCan, beerBottle, wineBottle...)
├── brands_id → brands table (columns: marlboro, cocacola, heineken...)
└── [other category]_id → [category] table
```

### v5 Structure (New)
```
photos
├── photo_tags (normalized tags)
│   ├── category_id → categories table
│   ├── litter_object_id → litter_objects table
│   └── photo_tag_extra_tags (materials, brands, custom)
└── summary (JSON cache of all tags)
```

## Migration Services

### 1. UpdateTagsService
**Purpose**: Orchestrates the migration of a single photo's tags from v4 to v5.

**Key Methods**:
- `getTags()` - Retrieves v4 tags from old category relationships
- `parseTags()` - Classifies tags into objects, brands, materials
- `createPhotoTags()` - Creates new PhotoTag records with relationships

**Important**: Does NOT merge single object+brand in getTags() - this is handled after parsing.

### 2. ClassifyTagsService
**Purpose**: Maps old tag keys to new objects and identifies tag types.

**Key Methods**:
- `classify()` - Determines if a tag is an object, brand, material, or custom
- `normalizeDeprecatedTag()` - Maps old keys to new keys with materials
- `materialMap()` - Provides material ID lookup cache

### 3. GeneratePhotoSummaryService
**Purpose**: Creates the JSON summary and calculates XP after migration.

## Migration Rules

### Rule 1: Single Object + Single Brand
When a photo has exactly 1 object and 1 brand, they are automatically associated:

```php
// Before parsing:
['softdrinks' => ['tinCan' => 1], 'brands' => ['coke' => 1]]

// After migration:
PhotoTag for soda_can with coke brand attached
+ Creates pivot relationship for future use
```

### Rule 2: Multiple Objects/Brands - Pivot Lookup
For multiple items, brands are matched using database relationships:

1. **Check existing pivots** - Query `taggables` table for brand-object relationships
2. **First match wins** - Brand attaches to first object with a pivot relationship
3. **Create relationship** - Successful matches create/update pivot for future use

### Rule 3: Quantity Matching (Fallback)
If no pivot exists, match by quantity:

```php
// Tags: beer_bottle => 3, coffee_cup => 1, heineken => 3
// Result: heineken attaches to beer_bottle (quantity match)
```

**Important**: Ambiguous quantities (multiple objects with same count) result in no attachment.

### Rule 4: No Fallback Attachment
Unmatched brands are logged but NOT arbitrarily attached. Manual review required.

## Deprecated Tag Mappings

Old v4 column names are automatically mapped to v5 objects with appropriate materials:

| Category | Old Key | New Object | Materials Added |
|----------|---------|------------|-----------------|
| alcohol | beerBottle | beer_bottle | [glass] |
| alcohol | beerCan | beer_can | [aluminium] |
| alcohol | spiritBottle | spirits_bottle | [glass] |
| alcohol | wineBottle | wine_bottle | [glass] |
| coffee | coffeeCups | cup | [paper] |
| coffee | coffeeLids | lid | [plastic] |
| food | sweetWrappers | wrapper | [plastic] |
| food | paperFoodPackaging | packaging | [paper] |
| food | plasticFoodPackaging | packaging | [plastic] |
| softdrinks | tinCan | soda_can | [aluminium] |
| softdrinks | waterBottle | water_bottle | [plastic] |
| softdrinks | fizzyDrinkBottle | fizzy_bottle | [plastic] |
| smoking | cigaretteBox | cigarette_box | [cardboard] |
| smoking | vape_pen | vapePen | [plastic, metal] |

Full mapping in `ClassifyTagsService::normalizeDeprecatedTag()`

## Brand-Object Relationships

### Pivot Table Structure
```sql
category_litter_object
├── category_id
└── litter_object_id

taggables
├── category_litter_object_id
├── taggable_type (BrandList::class)
├── taggable_id (brand_id)
└── quantity
```

### Creating Relationships
During migration, when a single brand is successfully matched to a single object:

```php
// Auto-create pivot for future use
CategoryObject::firstOrCreate([
    'category_id' => $categoryId,
    'litter_object_id' => $objectId
]);

Taggable::firstOrCreate([
    'category_litter_object_id' => $categoryObject->id,
    'taggable_type' => BrandList::class,
    'taggable_id' => $brandId
]);
```

## Processing Flow

```
1. Photo::tags() retrieves v4 data
   ↓
2. UpdateTagsService::getTags() 
   - Returns raw tags WITHOUT merging
   ↓
3. UpdateTagsService::parseTags()
   - Classifies each tag (object/brand/material)
   - Handles deprecated mappings
   - Separates into groups and globalBrands
   ↓
4. UpdateTagsService::createPhotoTags()
   - Creates PhotoTag for each object
   - Attaches materials from mappings
   ↓
5. Brand Matching Logic:
   - Single+Single: Auto-associate & create pivot
   - Multiple: Use pivot lookup, then quantity
   ↓
6. GeneratePhotoSummaryService::run()
   - Creates JSON summary
   - Calculates XP
   ↓
7. Mark photo as migrated (migrated_at timestamp)
```

## Special Cases

### 1. Unknown Tags
Tags not in the database are auto-created as objects:
```php
LitterObject::firstOrCreate(
    ['key' => 'mystery_item'],
    ['crowdsourced' => true]
);
```

### 2. Brands in Wrong Categories
Brands found in non-brand categories are extracted to globalBrands:
```php
// Input: softdrinks => ['tinCan' => 1, 'coke' => 1]
// Result: 
//   - tinCan → object in softdrinks
//   - coke → globalBrand for matching
```

### 3. Custom Tags
Legacy customTags are preserved and attached to the last PhotoTag:
```php
$photo->customTags // Old relationship
→ PhotoTagExtraTags with tag_type='custom_tag'
```

### 4. Empty Photos
Photos with no tags are marked as migrated without creating PhotoTags.

## Common Issues & Solutions

### Issue 1: Brands Not Attaching
**Symptom**: Brand exists but isn't attached to any object
**Cause**: No pivot relationship and no unique quantity match
**Solution**:
1. Check if pivot exists in database
2. Verify quantities are unique
3. Create manual pivot if logical relationship exists

### Issue 2: Wrong Material Associations
**Symptom**: Object has incorrect materials
**Cause**: Deprecated tag mapping includes default materials
**Solution**: Materials are informational only; can be corrected post-migration

### Issue 3: Duplicate Processing
**Symptom**: Photo processed multiple times
**Check**: `migrated_at` timestamp should prevent reprocessing
**Solution**: Add check at start of UpdateTagsService::updateTags()

### Issue 4: Memory Issues
**Symptom**: Migration crashes on large datasets
**Solution**: Process in smaller batches (default 500)
```bash
php artisan olm:v5 --batch=100
```

## Testing Migration

### Unit Tests Required
1. **Single object + single brand** - Auto-association
2. **Pivot lookup** - Existing relationships honored
3. **Quantity matching** - Unique quantities match
4. **Ambiguous quantities** - No incorrect attachments
5. **Deprecated mappings** - Old keys convert correctly
6. **Material attachments** - Materials added from mappings

### Manual Verification
```sql
-- Check migration status
SELECT COUNT(*) FROM photos WHERE migrated_at IS NULL;

-- Verify brand attachments
SELECT pt.*, ptet.* 
FROM photo_tags pt
JOIN photo_tag_extra_tags ptet ON pt.id = ptet.photo_tag_id
WHERE ptet.tag_type = 'brand';

-- Check orphaned brands
SELECT * FROM photo_tag_extra_tags 
WHERE tag_type = 'brand' 
AND photo_tag_id NOT IN (
    SELECT id FROM photo_tags WHERE litter_object_id IS NOT NULL
);
```

## Rollback Strategy

If migration needs reverting:

1. **Backup first**: Always backup before migration
2. **Partial rollback**: Can reset specific photos:
   ```sql
   UPDATE photos SET migrated_at = NULL WHERE id IN (...);
   DELETE FROM photo_tags WHERE photo_id IN (...);
   ```
3. **Full rollback**: Restore from backup
4. **Re-run**: Fix issues and re-run migration

## Performance Considerations

- **Batch Processing**: Default 500 photos per batch
- **Memory Usage**: ~50MB per 1000 photos
- **Processing Time**: ~1-2 seconds per photo
- **Database Locks**: Minimal, uses transactions per photo
- **Redis Impact**: Updates deferred to background jobs

## Command Options

```bash
# Full migration
php artisan olm:v5

# Specific user
php artisan olm:v5 --user=123

# Custom batch size
php artisan olm:v5 --batch=1000

# Dry run (no commits)
php artisan olm:v5 --dry-run

# Verbose output
php artisan olm:v5 -v

# Only unmigrated photos
php artisan olm:v5 --unmigrated
```

## Post-Migration Validation

After migration, verify:

1. ✓ All photos have `migrated_at` timestamp
2. ✓ PhotoTag count matches expected
3. ✓ Brand associations are logical
4. ✓ Materials are attached correctly
5. ✓ XP calculations are accurate
6. ✓ Summary JSON is properly formatted
7. ✓ No orphaned records in old tables

## Future Improvements

1. **Machine Learning** for brand-object matching
2. **Confidence scores** for ambiguous matches
3. **Bulk editing tools** for correcting associations
4. **Migration analytics** dashboard
5. **Automated testing** of edge cases
