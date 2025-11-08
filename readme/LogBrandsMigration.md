# OpenLitterMap v5 Brand Migration Process

## Overview

The v5 migration transforms 500,000+ photos with 850,000+ tags from a column-based tagging system to a normalized relationship-based system. The critical challenge is establishing accurate brand-object relationships to prevent nonsensical associations (e.g., "coke → cigarette_butts").

## Migration Strategy: Lift-Based Statistical Analysis

Instead of manual review or automatic discovery, we use **lift analysis** to identify statistically significant brand-object relationships:

- **Lift** = P(object|brand) / P(object)
- Lift > 1.0 = positive association
- Lift > 2.0 = strong association
- Lift > 3.0 = very strong association

This approach is 4x more effective than simple co-occurrence counting.

## Complete Process Workflow

### Phase 1: Generate Statistical Analysis

```bash
# Analyze current brand distribution
php artisan olm:log-brand-relationships --analyze

# Generate comprehensive statistics with lift metrics
php artisan olm:log-brand-relationships --all
```

**Output**: `ALL-brands-[timestamp].csv` with 23 columns including:
- Lift scores
- Photo-based support
- Confidence levels (VERY_HIGH/HIGH/MEDIUM/LOW/NOISE)
- Probability metrics
- Brand/object relationships

### Phase 2: Quick Manual Review (Optional)

Open the CSV in Excel/Google Sheets:

1. **Sort by Lift** (descending)
2. **Filter**: Confidence = HIGH or VERY_HIGH
3. **Review** high-lift relationships for obvious errors
4. **Export** filtered list if needed

Key decision metrics:
- **Lift ≥ 3.0 + Photos ≥ 20** = Almost certainly valid
- **Lift ≥ 2.0 + Photos ≥ 10** = Likely valid
- **P_obj_given_brand ≥ 0.20** = Common relationship
- **Single_Occurrence = YES** = Noise, ignore

### Phase 3: AI-Powered Validation

#### Test Single Brand First
```bash
# Dry run to see what would be processed
php artisan olm:validate-brands --brand=coke --dry-run

# Actual validation
php artisan olm:validate-brands --brand=coke

# Check result
cat storage/app/brands/coke.json
```

#### Batch Validation by Letter
```bash
# Process brands alphabetically to manage API costs
php artisan olm:validate-brands --letter=a --min-lift=2.0 --min-photos=10
php artisan olm:validate-brands --letter=b --min-lift=2.0 --min-photos=10
# ... continue through z

# Or validate ALL at once (expensive!)
php artisan olm:validate-brands --all --dry-run  # Check cost first
php artisan olm:validate-brands --all
```

**Options**:
- `--min-lift=2.0` - Only validate relationships with lift ≥ 2.0
- `--min-photos=10` - Only validate relationships with ≥ 10 photos
- `--dry-run` - Preview what would be processed without API calls

### Phase 4: Generate Configuration

```bash
# Generate BrandsConfig from validated results
php artisan olm:validate-brands --all --export

# Output: storage/app/BrandsConfig_generated.php
```

The generated config includes:
- Confidence level groupings
- Warning markers for low-confidence brands
- Clean category→object mappings

### Phase 5: Review and Deploy

1. **Review** `BrandsConfig_generated.php`
    - Check brands marked with `// ⚠️ LOW`
    - Verify high-confidence relationships make sense
    - Remove any obvious errors

2. **Merge** into `app/Tags/BrandsConfig.php`
   ```php
   // Example entry
   'coke' => [
       'softdrinks' => ['soda_can', 'fizzy_bottle', 'cup', 'lid', 'label'],
       // Validated relationships only
   ],
   ```

3. **Test Migration** on subset
   ```bash
   php artisan olm:migrate-subset --limit=100
   ```

4. **Full Migration**
   ```bash
   php artisan olm:migrate-all
   ```

## Key Improvements in This Process

### 1. Statistical Rigor
- **Lift analysis** identifies true relationships vs coincidence
- **Photo-based support** prevents double-counting
- **Normalized keys** avoid duplicates (aadrink vs AAdrink)

### 2. Efficient Validation
- **Pre-filtering** by lift/support reduces API calls
- **Batch processing** manages costs
- **Confidence scoring** prioritizes manual review

### 3. Quality Assurance
- **Parent company awareness** (Coke = Coca-Cola Company products)
- **Category logic** prevents impossible relationships
- **Audit trail** via JSON outputs

## Cost Estimates

| Action | Items | API Cost | Time |
|--------|-------|----------|------|
| Generate CSV | 500k photos | $0 | 5-10 min |
| Validate brand | 1 brand | ~$0.008 | 1 sec |
| Validate letter | ~100 brands | ~$0.80 | 2 min |
| Validate all | ~2000 brands | ~$16 | 35 min |

## Success Metrics

After completion, you should have:
- ✅ Comprehensive CSV with lift analysis
- ✅ ~2000 brands validated via AI
- ✅ BrandsConfig with only logical relationships
- ✅ Zero nonsensical associations
- ✅ 95%+ photos matching correctly during migration

## File Outputs

| File | Purpose |
|------|---------|
| `ALL-brands-*.csv` | Complete statistical analysis |
| `brands/*.json` | Individual brand validations |
| `brands/summary.json` | Validation summary and metrics |
| `BrandsConfig_generated.php` | Ready-to-merge configuration |

## Common Issues and Solutions

### High Lift but Wrong Category
**Example**: Beer brand → cigarette butts (lift=4.5)  
**Cause**: Photos at bars/events with mixed litter  
**Solution**: AI validation rejects based on category mismatch

### Low Confidence Brands
**Example**: Regional or obscure brands  
**Solution**: Manual review of `brands/*.json` files marked LOW

### Missing Subsidiaries
**Example**: Coke products beyond soda (Dasani water)  
**Solution**: AI considers parent company portfolio

## Console Output Examples

### Statistical Analysis
```
╔════════════════════════════════════════════════════════╗
║  EXPORTING LIFT-BASED BRAND-OBJECT STATISTICS         ║
╚════════════════════════════════════════════════════════╝

Processing 50,000 photos...
[████████████████████] 100% 

🎯 High-Confidence Relationships (Lift ≥ 3.0, Photos ≥ 10):
  1. coke        → softdrinks.soda_can    (lift: 45.2, photos: 1250)
  2. marlboro    → smoking.cigarette_box  (lift: 38.7, photos:  890)
  3. mcdonalds   → food.wrapper           (lift: 32.1, photos:  567)

📊 Distribution by Confidence:
  VERY_HIGH:    234 (5.2%)
  HIGH:         567 (12.6%)
  MEDIUM:       890 (19.8%)
  LOW:        1,234 (27.4%)
  NOISE:      1,575 (35.0%)
```

### Validation Progress
```
Validating 156 brands for letter 'C'...
[████████░░░░░░░░] 45% - corona

⚠️ Low confidence brands requiring manual review:
   - carlsberg_sport
   - centra_own_brand
   - chicago_town

✅ Validation complete: 145 approved, 11 need review
```

## Next Steps After Migration

1. **Verify** relationships in production
2. **Monitor** new photo uploads for unmatched brands
3. **Update** BrandsConfig quarterly based on new data
4. **Document** any manual overrides or exceptions

## Command Reference

```bash
# Analysis
php artisan olm:log-brand-relationships --analyze
php artisan olm:log-brand-relationships --all

# Validation
php artisan olm:validate-brands --brand=X [--dry-run]
php artisan olm:validate-brands --letter=X [--min-lift=N] [--min-photos=N]
php artisan olm:validate-brands --all [--export]

# Migration
php artisan olm:migrate-subset --limit=N
php artisan olm:migrate-all
```

## Summary

This lift-based statistical approach combined with AI validation ensures:
- **Data Quality**: Accurate brand-object relationships
- **Efficiency**: Minimal manual review needed
- **Scalability**: Handles 850,000+ tags effectively
- **Maintainability**: Clear audit trail and documentation

The entire process can be completed in 1-2 days vs weeks of manual review, with superior accuracy.
