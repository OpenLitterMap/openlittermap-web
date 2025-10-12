# OpenLitterMap v5 Tagging System

## Overview

OpenLitterMap v5 introduces a flexible, hierarchical tagging system that allows precise classification of litter items. Each photo can have multiple tags organized by categories, objects, and their properties (materials, brands, and custom attributes).

## Core Concepts

### Tag Hierarchy

```
Photo
├── PhotoTag (Primary tagged item)
│   ├── Category (e.g., "smoking", "food", "softdrinks")
│   ├── LitterObject (e.g., "butts", "wrapper", "bottle")
│   ├── Quantity (How many of this item)
│   └── PhotoTagExtraTags (Additional properties)
│       ├── Materials (e.g., "plastic", "glass", "aluminium")
│       ├── Brands (e.g., "coca-cola", "marlboro", "mcdonalds")
│       └── CustomTags (User-defined tags)
```

### Database Structure

```
photos
├── id
├── user_id
├── summary (JSON) - Cached tag structure
├── xp (INT) - Calculated experience points
├── total_tags (INT) - Total item count
├── total_brands (INT) - Total brand count
├── processed_at (TIMESTAMP) - When metrics were processed
├── processed_tags (TEXT) - Cached tags for metrics
└── migrated_at (TIMESTAMP) - v5 migration timestamp

photo_tags
├── id
├── photo_id
├── category_id
├── litter_object_id
├── custom_tag_primary_id (for custom-only tags)
├── quantity
└── picked_up (BOOLEAN)

photo_tag_extra_tags
├── photo_tag_id
├── tag_type (material|brand|custom_tag)
├── tag_type_id
├── quantity
└── index
```

## Photo Summary Structure

Each photo maintains a `summary` JSON field with this structure:

```json
{
    "tags": {
        "2": {
            "15": {
                "quantity": 5,
                "materials": {
                    "3": 5,
                    "7": 5
                },
                "brands": {
                    "12": 3,
                    "18": 2
                },
                "custom_tags": {}
            }
        }
    },
    "totals": {
        "total_tags": 10,
        "total_objects": 5,
        "by_category": {
            "2": 5
        },
        "materials": 10,
        "brands": 5,
        "custom_tags": 0
    },
    "keys": {
        "categories": {"2": "smoking"},
        "objects": {"15": "butts"},
        "materials": {"3": "plastic", "7": "paper"},
        "brands": {"12": "marlboro", "18": "camel"},
        "custom_tags": {}
    }
}
```

**Key meanings:**
- `"2"` = Category ID (smoking)
- `"15"` = Object ID (butts)
- `"3"` = Material ID (plastic) with quantity 5
- `"7"` = Material ID (paper) with quantity 5
- `"12"` = Brand ID (marlboro) with quantity 3
- `"18"` = Brand ID (camel) with quantity 2

## XP (Experience Points) System

XP rewards users for tagging litter:

| Action           | XP Value    |
|------------------|-------------|
| Upload           | 5           |
| Standard Object  | 1 per item  |
| Material         | 2 per item  |
| Brand            | 3 per item  |
| Custom Tag       | 1 per item  |
| Picked Up        | +5 bonus    |
| Special Objects: |             |
| - Small item     | 10 per item |
| - Medium item    | 25 per item |
| - Large item     | 50 per item |
| - Bags of Litter | 10 per item |

### XP Calculation Example

```
Photo with:
- 5 cigarette butts with 2 brands
- Marked as picked up

XP = 5 (upload) 
   + 5 × 1 (5 objects)
   + 5 × 3 (5 brand instances across butts)
   + 5 (picked up bonus)
   = 30 XP
```

## Tag Migration from v4 to v5

### Old Format (v4)
```php
[
    'smoking' => [
        'butts' => 5,
        'cigaretteBox' => 1
    ],
    'brands' => [
        'marlboro' => 3,
        'camel' => 2
    ]
]
```

### New Format (v5)
```php
PhotoTag::create([
    'photo_id' => $photo->id,
    'category_id' => 2,  // smoking
    'litter_object_id' => 15,  // butts
    'quantity' => 5,
    'picked_up' => true
]);

// Attach brands as extra tags
$photoTag->attachExtraTags([
    ['id' => 12, 'quantity' => 3],  // marlboro
    ['id' => 18, 'quantity' => 2],  // camel
], 'brand', 0);
```

## Special Cases

### 1. Brands-Only Photos
When a photo only has brands without specific objects:

```php
PhotoTag::create([
    'photo_id' => $photo->id,
    'category_id' => $brandsCategoryId,
    'quantity' => $totalBrandQuantity,
    'picked_up' => !$photo->remaining
]);
```

### 2. Custom Tags Only
For photos with only custom tags:

```php
PhotoTag::create([
    'photo_id' => $photo->id,
    'custom_tag_primary_id' => $customTag->id,
    'quantity' => $quantity,
    'picked_up' => !$photo->remaining
]);
```

### 3. Deprecated Tag Mapping
Old tags are automatically mapped to new equivalents:

| Old Tag                | New Object     | Materials Added |
|------------------------|----------------|-----------------|
| `beerBottle`           | `beer_bottle`  | `[glass]`       |
| `beerCan`              | `beer_can`     | `[aluminium]`   |
| `coffeeCups`           | `cup`          | `[paper]`       |
| `plasticFoodPackaging` | `packaging`    | `[plastic]`     |
| `waterBottle`          | `water_bottle` | `[plastic]`     |

### 4. Unknown Tags
Unknown tags are automatically created as new objects:

```php
// Tag "mystery_item" doesn't exist
$created = LitterObject::firstOrCreate(
    ['key' => 'mystery_item'],
    ['crowdsourced' => true]
);
```

## Time-Series Metrics

All photos contribute to time-series metrics across multiple dimensions:

### Timescales
- **0**: All-time aggregate
- **1**: Daily
- **2**: Weekly (ISO week)
- **3**: Monthly
- **4**: Yearly

### Location Hierarchy
- Global (id: 0)
- Country
- State
- City

### Metrics Tracked
- `uploads`: Number of photos
- `tags`: Total tag count
- `brands`: Total brand count
- `litter`: Total litter items
- `xp`: Experience points

## Redis Caching Structure

Redis stores aggregated metrics for fast access:

```
{g}:stats                    // Global stats
{c:123}:stats               // Country stats
{s:456}:stats              // State stats
{ci:789}:stats            // City stats
{u:111}:stats            // User stats

{g}:rank:objects         // Global object rankings
{g}:rank:brands         // Global brand rankings
{c:123}:rank:materials // Country material rankings
```

## Processing Pipeline

1. **Photo Upload** → Initial record created
2. **Tag Addition** → User adds tags via UI
3. **UpdateTagsService** → Migrates old format to new
4. **GeneratePhotoSummaryService** → Creates JSON summary & calculates XP
5. **TimeSeriesService** → Updates MySQL metrics
6. **RedisMetricsCollector** → Updates Redis caches
7. **AchievementEngine** → Evaluates user achievements

## Validation Rules

- Quantities must be positive integers
- Category-Object relationships must be valid
- Materials/Brands must be attached to objects
- Custom tags can be standalone or attached
- XP calculation uses enum-defined values
- Fingerprinting prevents duplicate processing

## API Response Format

```json
{
  "photo_id": 12345,
  "tags": {
    "smoking": {
      "butts": {
        "quantity": 5,
        "materials": ["plastic", "paper"],
        "brands": ["marlboro", "camel"]
      }
    }
  },
  "metrics": {
    "total_items": 5,
    "total_brands": 2,
    "xp_earned": 30
  },
  "location": {
    "country": "Ireland",
    "state": "Munster",
    "city": "Cork"
  }
}
```

## Best Practices

1. **Always use IDs** for database operations, keys for display
2. **Batch operations** when processing multiple photos
3. **Use transactions** for data consistency
4. **Cache aggressively** but invalidate precisely
5. **Log unknown tags** for future classification
6. **Validate quantities** before database writes
7. **Track processing state** via fingerprints

## Migration Command

```bash
# Migrate all photos
php artisan olm:v5

# Migrate specific user
php artisan olm:v5 --user=123

# Custom batch size
php artisan olm:v5 --batch=1000
```

## Troubleshooting

### Common Issues

1. **Missing XP for special objects**: Ensure object keys match exactly (case-sensitive)
2. **Duplicate processing**: Check `processed_fp` fingerprint field
3. **Memory issues during migration**: Reduce batch size
4. **Redis inconsistency**: Flush cache and rebuild from MySQL

