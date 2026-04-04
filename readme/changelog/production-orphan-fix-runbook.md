# Production Runbook: Orphaned Tags Fix

**Date prepared:** 2026-04-04
**Local validation:** Complete (189,518 tags, 170,992 summaries, 1,041 XP corrections)

---

## Step 1: Dry-run CLO fix

```bash
php artisan olm:fix-orphaned-tags --log=storage/logs/orphan-fix-prod.log
```

**Expected output:**
- 74 mappings, all ✓
- Total expected: 189,518
- Total would update: 189,518
- 0 mismatches

**Gate:** If counts differ from local, STOP. Investigate before proceeding.

---

## Step 2: Apply CLO fix

```bash
php artisan olm:fix-orphaned-tags --apply --log=storage/logs/orphan-fix-prod.log
```

- UPDATE-only, batched at 5,000, transacted per orphan key
- Low risk — no events, no metrics, no Redis

**Verify:**
```bash
php artisan olm:fix-orphaned-tags --verify-only --log=storage/logs/orphan-fix-prod.log
```

Expected:
- Orphaned photo_tags (NULL CLO + non-NULL LO): 0 ✓
- Extra-tag-only (NULL CLO + NULL LO): ~24,628
- Spot check: 0 remaining ✓

---

## Step 3: Summary regeneration (heavy — schedule during low traffic)

```bash
php artisan olm:regenerate-summaries --orphan-fix --log=storage/logs/regen-summaries-prod.log
```

- Scans 518k migrated photos, regenerates ~171k stale summaries
- Chunked at 500 (DB-level chunkById, no memory issue)
- Resumable: skips already-regenerated photos (checks clo_id in summary JSON)
- Pure write: `Photo::withoutEvents()` — no observers, no MetricsService, no Redis
- Kill and restart safely if needed

**Expected:**
- ~170,992 changed, ~347,052 skipped, 0 errors
- ~1,041 photos will log XP changes (special object bonus corrections)

---

## Step 4: XP metrics reprocess

After step 3, extract the XP-changed photo IDs from the log:

```bash
grep "xp:" storage/logs/regen-summaries-prod.log | sed 's/.*Photo \([0-9]*\):.*/\1/' > storage/logs/xp-changed-photo-ids.txt
wc -l storage/logs/xp-changed-photo-ids.txt
```

Expected: ~1,041 IDs.

Dry-run:
```bash
php artisan olm:reprocess-metrics --from-file=storage/logs/xp-changed-photo-ids.txt --dry-run --log=storage/logs/reprocess-metrics-prod.log
```

Apply:
```bash
php artisan olm:reprocess-metrics --from-file=storage/logs/xp-changed-photo-ids.txt --log=storage/logs/reprocess-metrics-prod.log
```

- Trivial load (~1k photos)
- Updates: metrics table, Redis leaderboard ZSETs, users.xp, processed_xp/processed_fp

---

## Post-run verification

```sql
-- Should be 0
SELECT COUNT(*) FROM photo_tags
WHERE category_litter_object_id IS NULL AND litter_object_id IS NOT NULL;

-- Should be ~24,628
SELECT COUNT(*) FROM photo_tags
WHERE category_litter_object_id IS NULL AND litter_object_id IS NULL;

-- Spot check: Red Bull energy cans queryable
SELECT COUNT(*) as photos, SUM(pt.quantity) as total_cans
FROM photo_tags pt
JOIN litter_object_types lot ON pt.litter_object_type_id = lot.id
JOIN photo_tag_extra_tags pete ON pete.photo_tag_id = pt.id AND pete.tag_type = 'brand'
JOIN brandslist b ON pete.tag_type_id = b.id
WHERE lot.key = 'energy' AND b.key = 'redbull';
```

## Rollback

No rollback script needed — the fix is idempotent (re-running on already-fixed data is a no-op). If something goes wrong mid-step, fix the issue and re-run. The summary regen is resumable. The metrics reprocess is delta-based.

For catastrophic rollback: restore from DB backup taken before step 2.
