# Production Runbook: Orphaned Tags Fix

**Date prepared:** 2026-04-04
**Last updated:** 2026-04-05
**Local validation:** Complete (189,518 tags, 170,992 summaries, 1,041 XP corrections)

**Local timing (reference):**
- Step 2 (pointer fix): ~9 seconds
- Step 4 (summary regen): ~5.5 minutes (518k scanned, 171k regenerated)
- Step 6 (metrics reprocess): ~9 seconds for 1,041 photos; ~15 minutes estimated for 171k

---

## Pre-flight: Database Backup

```bash
# Backup photo_tags before any changes (cheap insurance on 189k rows)
mysqldump -u root -p olm photo_tags > /tmp/photo_tags_backup_$(date +%Y%m%d_%H%M%S).sql

# Optional: backup photos table (summaries will change in step 4)
mysqldump -u root -p olm photos --where="migrated_at IS NOT NULL" > /tmp/photos_migrated_backup_$(date +%Y%m%d_%H%M%S).sql
```

---

## Step 1: Dry-run CLO pointer fix

```bash
php artisan olm:fix-orphaned-tags --log=storage/logs/orphan-fix-prod.log
```

**Expected output:**
- 74 mappings, all match
- Total expected: 189,518
- Total would update: 189,518
- 0 mismatches

**Gate:** If counts differ from local, STOP. Investigate before proceeding.

---

## Step 2: Apply CLO pointer fix

```bash
php artisan olm:fix-orphaned-tags --apply --log=storage/logs/orphan-fix-prod.log
```

- UPDATE-only, batched at 5,000, transacted per orphan key
- Low risk: no events, no metrics, no Redis, no summaries

**Verify immediately:**
```bash
php artisan olm:fix-orphaned-tags --verify-only --log=storage/logs/orphan-fix-prod.log
```

Expected:
- Orphaned photo_tags (NULL CLO + non-NULL LO): 0
- Extra-tag-only (NULL CLO + NULL LO): ~24,628
- Spot check: 0 remaining

**Steps 1-2 are safe to run during normal traffic.**

---

## Step 3: Dry-run summary regeneration

Schedule steps 3-6 during low traffic. The summary regen is the heaviest operation.

```bash
php artisan olm:regenerate-summaries --orphan-fix --dry-run --log=storage/logs/regen-summaries-prod.log
```

**Expected:** ~170,992 would change, ~347,052 skipped, 0 errors.

---

## Step 4: Apply summary regeneration

```bash
php artisan olm:regenerate-summaries --orphan-fix \
  --changed-ids=storage/logs/summary-changed-photo-ids.txt \
  --log=storage/logs/regen-summaries-prod.log
```

- Scans ~518k migrated photos, regenerates ~171k stale summaries
- Chunked at 500 (DB-level `chunkById`, no memory issue)
- **Resumable:** skips already-regenerated photos (checks `clo_id` in summary JSON)
- Pure write: `Photo::withoutEvents()` — no observers, no MetricsService, no Redis
- Kill and restart safely if needed (already-regenerated photos are detected and skipped)
- Writes ALL changed photo IDs to `--changed-ids` file for step 6

**Why this is needed:** The pointer fix (step 2) changed `litter_object_id` and `category_id`
on `photo_tags` rows, but `photo.summary` JSON still contains the old orphan IDs. Without
regeneration, the summary is structurally inconsistent with the underlying `photo_tags` data.
`MetricsService::extractMetricsFromPhoto()` reads from summary JSON, so stale summaries mean
stale fingerprints — any future edit to an affected photo would trigger an unexpected
metrics delta.

**Expected:**
- ~170,992 changed, ~347,052 skipped, 0 errors
- ~1,041 photos will log XP changes (special object bonus corrections)
- `summary-changed-photo-ids.txt` will contain ~170,992 photo IDs

---

## Step 5: Dry-run metrics reprocess (all summary-changed photos)

```bash
wc -l storage/logs/summary-changed-photo-ids.txt
# Expected: ~170,992

php artisan olm:reprocess-metrics \
  --from-file=storage/logs/summary-changed-photo-ids.txt \
  --dry-run --log=storage/logs/reprocess-metrics-prod.log
```

Review the dry-run output. Most photos will show zero XP delta (summary changed but total
XP stayed the same). ~1,041 will show non-zero XP deltas from special object bonus corrections.

**Why all ~171k and not just the ~1,041 with XP changes:** Every summary-changed photo has a
stale `processed_fp` (fingerprint) and `processed_tags` on the photo record. Without updating
these, the next time `MetricsService::processPhoto()` runs on any of those photos (tag edit,
admin action, re-verification), it would detect a fingerprint mismatch and attempt to
delta-correct — producing an unexpected and potentially incorrect metrics delta. Reprocessing
all summary-changed photos updates `processed_fp` and `processed_tags` atomically, eliminating
this "ticking time bomb" scenario.

---

## Step 6: Apply metrics reprocess

```bash
php artisan olm:reprocess-metrics \
  --from-file=storage/logs/summary-changed-photo-ids.txt \
  --log=storage/logs/reprocess-metrics-prod.log
```

- ~171k photos at batch size 100, each within a DB transaction
- Updates: metrics table, Redis leaderboard ZSETs, `users.xp`, `processed_xp`/`processed_fp`/`processed_tags`
- Estimated runtime: ~15 minutes (extrapolated from 1,041 in 9 seconds locally)

---

## Post-run verification

```sql
-- Should be 0: no orphaned photo_tags remain
SELECT COUNT(*) FROM photo_tags
WHERE category_litter_object_id IS NULL AND litter_object_id IS NOT NULL;

-- Should be ~24,628: extra-tag-only (brands/materials/custom with no CLO)
SELECT COUNT(*) FROM photo_tags
WHERE category_litter_object_id IS NULL AND litter_object_id IS NULL;

-- Spot check: orphan keys should have 0 remaining
SELECT lo.`key`, COUNT(*) AS remaining
FROM photo_tags pt
JOIN litter_objects lo ON pt.litter_object_id = lo.id
WHERE pt.category_litter_object_id IS NULL
AND lo.`key` IN ('energy_can', 'beer_can', 'water_bottle', 'soda_can')
GROUP BY lo.`key`;

-- Spot check: energy cans now queryable by type
SELECT COUNT(*) as photos, SUM(pt.quantity) as total_cans
FROM photo_tags pt
JOIN litter_object_types lot ON pt.litter_object_type_id = lot.id
WHERE lot.key = 'energy'
AND pt.category_litter_object_id = 152;

-- Fingerprint consistency: should be 0 (no stale processed_fp)
SELECT COUNT(*) FROM photos
WHERE migrated_at IS NOT NULL
AND processed_fp IS NOT NULL
AND summary IS NOT NULL
AND processed_at IS NOT NULL
AND id IN (SELECT DISTINCT photo_id FROM photo_tags WHERE category_litter_object_id IS NOT NULL);
```

---

## Rollback

The fix is idempotent at every step:
- **Pointer fix (step 2):** Already-fixed rows have non-NULL CLO and won't match the orphan query again. Re-running is a no-op.
- **Summary regen (step 4):** `hasStaleSummary()` detects already-regenerated photos and skips them. Resumable after interruption.
- **Metrics reprocess (step 6):** `processPhoto()` compares fingerprint and XP. If already reprocessed, fingerprint matches and it returns immediately.

For catastrophic rollback: restore from DB backup taken in the pre-flight step.
