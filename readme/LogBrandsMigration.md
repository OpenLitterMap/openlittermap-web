# OpenLitterMap v5 Brand Migration: Complete Control Approach

## Overview

The v5 migration requires precise brand-object relationships to ensure data quality. Instead of automatic discovery that creates incorrect associations (like "coke → cigarette_butts"), we provide two approaches:
1. **Letter-by-letter analysis** for manageable chunks
2. **Export ALL with YES/NO** for complete manual control

## The Problem We're Solving

Automatic brand discovery created nonsensical relationships:
- ❌ Coke associated with cigarette butts
- ❌ Marlboro associated with beer cans
- ❌ McDonald's associated with condoms

These false associations occur because photos contain multiple brands and objects that co-occur by coincidence, not relationship.

## Two Approaches Available

### Approach 1: Letter-by-Letter Analysis (Manageable Chunks)

Good for systematic review when you have time to process incrementally.

```bash
# Analyze letter A
php artisan olm:log-brand-relationships --letter=A --export

# Continue through alphabet
php artisan olm:log-brand-relationships --letter=B --export
# ... etc
```

### Approach 2: Export ALL for Manual Review (Complete Control)

Perfect when you want to see everything at once and have complete control.

```bash
# Export ALL brand relationships with YES/NO column
php artisan olm:log-brand-relationships --all

# Creates: ALL-brands-MASTER-[timestamp].csv
```

## The ALL Export Method (Recommended for Data Perfectionists)

### What You Get

A complete CSV with EVERY brand-object relationship found, including:

| Column | Description |
|--------|-------------|
| **Include?** | Smart YES/NO default for each relationship |
| **Brand** | Brand key |
| **Letter** | First letter (for sorting/filtering) |
| **Category** | Litter category |
| **Object** | Object key |
| **Photo Count** | Number of photos with this co-occurrence |
| **Brand Total** | Total occurrences of this brand |
| **Percentage** | % of brand occurrences with this object |
| **Confidence** | HIGH/MEDIUM/LOW/NOISE rating |
| **In Config?** | Already in BrandsConfig? |
| **Config Allows?** | Does current config allow this? |
| **Example Photo IDs** | Sample photos for verification |
| **Auto Reason** | Why system chose YES or NO |

### Smart YES/NO Logic

The system provides intelligent defaults based on:

#### Automatic YES when:
- **High percentage** (≥30%) - "High percentage (35%)"
- **Medium confidence** (≥10% AND ≥5 photos) - "Medium confidence (15%, 8 photos)"
- **Frequent but diverse** (≥5% AND ≥10 photos) - "Low % but frequent (12 photos)"

#### Automatic NO when:
- **Single occurrence** - "Single occurrence"
- **Category mismatch** - "Beverage brand with tobacco object"
- **Universal excludes** - "Object 'dogshit' rarely valid for brands"
- **Low confidence** - "Low confidence (2%, 2 photos)"

### Manual Review Workflow

#### Step 1: Open in Excel/Google Sheets
```
1. Import CSV
2. Enable filters on all columns
3. Freeze top row and first 3 columns
```

#### Step 2: Sort and Filter Strategy
```
Primary sort: Letter → Brand → Percentage (descending)
```

#### Step 3: Review Phases

**Phase 1: Verify High-Confidence YES**
- Filter: Include=YES, Confidence=HIGH
- Quick scan for obvious errors (coke→butts)
- These should mostly be correct

**Phase 2: Check Rejected High-Percentages**
- Filter: Include=NO, Percentage>10
- Look for valid relationships auto-rejected
- Example: McDonald's→cup might be 15% but still valid

**Phase 3: Review Medium Confidence**
- Filter: Confidence=MEDIUM
- These need most attention
- Use your domain knowledge

**Phase 4: Scan for Known Brands**
- Filter: Brand contains "coke" (or other known brands)
- Ensure all relationships make sense

#### Step 4: Edit YES/NO Column
- Change YES to NO for nonsense relationships
- Change NO to YES for valid relationships
- Add notes in empty cells if needed

## Time Estimates

- **Export ALL**: 5-10 minutes processing
- **Manual review**: 4-8 hours (at ~10 brands/minute)
- **Total time**: 1 day vs weeks of debugging bad relationships

## Summary

The `--all` option gives you complete control:
1. Export everything with smart YES/NO defaults
2. Review in spreadsheet with sorting/filtering
3. Manually adjust YES/NO as needed
4. Build perfect BrandsConfig from reviewed data
5. Never deal with "coke→cigarette_butts" again

## Process Workflow

### Step 1: Run Analysis for Each Letter

```bash
# Start with letter A
php artisan olm:log-brand-relationships --letter=A --export

# Then B
php artisan olm:log-brand-relationships --letter=B --export

# Continue through Z...
php artisan olm:log-brand-relationships --letter=Z --export
```

### Step 2: Review Console Output

The command displays each brand with its co-occurrences:

```
╔════════════════════════════════════════════════════════╗
║ BRAND: amstel                                          ║
║ Total occurrences: 709                                 ║
║ Status: ❌ NOT in BrandsConfig                         ║
╠════════════════════════════════════════════════════════╣
║ ⚠️ alcohol       beer_can              500 photos (70.5%) ★║
║ ⚠️ alcohol       beer_bottle           180 photos (25.4%) ★║
║ ⚠️ alcohol       bottletops             20 photos ( 2.8%)  ║
║ ⚠️ softdrinks    soda_can                2 photos ( 0.3%) •║
║ ⚠️ smoking       butts                   1 photos ( 0.1%) •║
╠════════════════════════════════════════════════════════╣
║ ⚠️ ACTION NEEDED: Add to BrandsConfig                  ║
╚════════════════════════════════════════════════════════╝
```

### Step 3: Interpret the Data

#### Percentage Guidelines:
- **≥20%** (★) = Strong relationship, definitely include
- **10-20%** = Likely valid relationship
- **5-10%** = Edge case, consider context
- **<5%** = Probably noise/error
- **Single occurrence (•)** = Almost certainly data entry error

#### Status Indicators:
- ✅ = Already configured correctly
- ❌ = Configured but relationship excluded (review if high %)
- ⚠️ = Not in BrandsConfig (needs to be added)

### Step 4: Update BrandsConfig.php

Based on the analysis, add the brand to `BrandsConfig.php`:

```php
// In BrandsConfig.php
'amstel' => [
    'alcohol' => ['beer_bottle', 'beer_can', 'bottletops'],
    // NOT including soda_can (0.3%) or butts (0.1%) - those are errors
],
```

### Step 5: Verify Changes

After updating BrandsConfig, re-run the letter to verify:

```bash
php artisan olm:log-brand-relationships --letter=A --export
```

You should now see ✅ indicators for correctly configured relationships.

## CSV Export Analysis

The `--export` flag creates a CSV with detailed analysis:

| Column | Description |
|--------|-------------|
| Brand | Brand key |
| Category | Litter category |
| Object | Object key |
| Photo Count | Number of photos with this co-occurrence |
| Total Occurrences | Total quantity count |
| Brand Total | Total occurrences of this brand |
| Percentage | % of brand occurrences with this object |
| In Config? | Is brand in BrandsConfig? |
| Config Allows? | Does config allow this relationship? |
| Example Photo IDs | Sample photos for verification |
| Suggested Action | Automated recommendation |

### Suggested Actions in CSV:
- `★ ADD to BrandsConfig (HIGH priority)` - ≥20% co-occurrence
- `ADD to BrandsConfig (medium priority)` - 10-20%
- `CONSIDER adding` - 5-10%
- `Single occurrence - likely noise` - Only 1 photo
- `Low usage - review` - <5% but multiple photos
- `✓ Correctly configured` - Already set up properly
- `✓ Correctly excluded` - Correctly identified as noise

## Example BrandsConfig Entries

### Beverage Brand (Multiple Valid Objects)
```php
'coke' => [
    'softdrinks' => ['soda_can', 'fizzy_bottle', 'cup', 'lid', 'label', 'straws'],
    // High percentages for all these objects
],
```

### Tobacco Brand (Limited Objects)
```php
'marlboro' => [
    'smoking' => ['cigarette_box', 'butts', 'packaging'],
    // Only smoking-related objects
],
```

### Fast Food Brand (Multiple Categories)
```php
'mcdonalds' => [
    'food' => ['wrapper', 'packaging', 'napkins'],
    'coffee' => ['cup', 'lid'],
    'softdrinks' => ['cup', 'lid', 'straws'],
    // Spans multiple categories legitimately
],
```

## Data Quality Rules

### Include Relationships When:
1. **High percentage** (≥20%) - Clear pattern
2. **Logical connection** - Makes semantic sense
3. **Multiple occurrences** - Not a one-off
4. **Category match** - Beer brands with alcohol objects

### Exclude Relationships When:
1. **Low percentage** (<5%) - Likely coincidental
2. **Illogical pairing** - Coke with cigarette butts
3. **Single occurrence** - Probable data entry error
4. **Wrong category** - Alcohol brand with sanitary objects

## Progress Tracking

Track your progress through the alphabet:

```
[x] A - aadrink, adidas, aldi, amazon, amstel, apple, applegreen, asahi, avoca
[x] B - bacardi, ballygowan, bewleys, budweiser, bullit, bulmers, burgerking
[ ] C - In progress
[ ] D-Z - Pending
```

## Benefits of This Approach

1. **Data Quality**: No false relationships from coincidental co-occurrences
2. **Systematic**: Every brand gets reviewed, nothing missed
3. **Evidence-Based**: Decisions based on actual usage patterns
4. **Traceable**: CSV exports provide audit trail
5. **Flexible**: Can adjust thresholds based on brand type
6. **Maintainable**: Clear documentation of why relationships exist

## Migration Timeline

1. **Phase 1**: Log all brands A-Z (1-2 days)
2. **Phase 2**: Build BrandsConfig.php (1 day)
3. **Phase 3**: Test migration with sample data (1 day)
4. **Phase 4**: Full migration with perfect relationships (1 day)

## Troubleshooting

### Q: A brand appears with many unrelated objects
**A**: This indicates the brand appears in complex/messy photos. Focus only on high-percentage relationships.

### Q: A brand has no high-percentage relationships
**A**: Check if it's a rare brand. Consider the highest percentages relative to its total usage.

### Q: Should I include medium-percentage relationships?
**A**: Yes, if they make logical sense. McDonald's cup might be 15% but still valid.

### Q: What about brands not starting with A-Z?
**A**: Use numbers/symbols as the letter parameter, e.g., `--letter=7` for "7up"

## Command Reference

```bash
# Analyze specific letter
php artisan olm:log-brand-relationships --letter=A --export

# Review output without export
php artisan olm:log-brand-relationships --letter=B

# Check which brands aren't configured (from old command)
php artisan olm:define-brand-relationships --analyze
```

## Success Metrics

After completing all letters, you should have:
- ✅ ~2,000+ brands configured in BrandsConfig
- ✅ Each brand with only logical object relationships
- ✅ Zero nonsensical associations (no coke→butts)
- ✅ CSV documentation for every decision
- ✅ 95%+ of photos will match correctly during migration

## Next Steps

After completing BrandsConfig:
1. Create pivot relationships from config
2. Run test migration on subset
3. Verify brand attachments are logical
4. Execute full migration
5. Validate results with spot checks

This systematic approach ensures perfect data quality for the 850,000+ tags across 500,000+ photos in OpenLitterMap v5.
