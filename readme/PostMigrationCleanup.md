# OLM v5 — Post-Migration Cleanup

**Do NOT perform any of this until the v5 migration has been run in production and verified working.**

This documents everything that was intentionally left in place for the v5 release because it was needed by the tag migration scripts. Once the migration is complete and all photos have been re-processed through the new tagging system, these can be safely removed.

---

## 1. Photo model — remove deprecated category relationships

Remove everything below the `// DEPRECATED` comment in `App\Models\Photo`:

### Methods to delete

```
country()           → hasOne (broken anyway — should be belongsTo)
state()             → hasOne (broken anyway — should be belongsTo)
city()              → hasOne (broken anyway — should be belongsTo)
total()             → iterates old category relationships
translate()         → builds result_string from old categories
categories()        → static array of old category names
getBrands()         → delegates to Brand::types()
tags()              → iterates old category relationships
smoking()           → belongsTo Smoking model
food()              → belongsTo Food model
coffee()            → belongsTo Coffee model
softdrinks()        → belongsTo SoftDrinks model
alcohol()           → belongsTo Alcohol model
sanitary()          → belongsTo Sanitary model
dumping()           → belongsTo Dumping model
other()             → belongsTo Other model
industrial()        → belongsTo Industrial model
coastal()           → belongsTo Coastal model
art()               → belongsTo Art model
brands()            → belongsTo Brand model
trashdog()          → belongsTo TrashDog model
dogshit()           → belongsTo Dogshit model
material()          → belongsTo Material model
customTags()        → hasMany CustomTag (old system)
```

After removal, the active location relationships are:
- `countryRelation()` → belongsTo Country
- `stateRelation()` → belongsTo State
- `cityRelation()` → belongsTo City

### Migration to drop FK columns from photos

Once the category relationships are deleted and no code references them, drop the FK columns:

```php
Schema::table('photos', function (Blueprint $table) {
$table->dropColumn([
'smoking_id',
'food_id',
'coffee_id',
'softdrinks_id',
'alcohol_id',
'sanitary_id',
'dumping_id',
'other_id',
'industrial_id',
'coastal_id',
'art_id',
'brands_id',
'trashdog_id',
'dogshit_id',
'material_id',
'total_litter',    // replaced by summary JSON + metrics table
'result_string',   // replaced by summary JSON
'remaining',       // replaced by photo_tags.picked_up (per-tag granularity)
]);
});
```

**Check for FK constraints first** — if any of these columns have foreign keys, drop those before the column.

---

## 2. Drop old category tables

Once no code references the category models or relationships:

```php
Schema::dropIfExists('smoking');
Schema::dropIfExists('food');
Schema::dropIfExists('coffee');
Schema::dropIfExists('softdrinks');
Schema::dropIfExists('alcohol');
Schema::dropIfExists('sanitary');
Schema::dropIfExists('dumping');
Schema::dropIfExists('other');
Schema::dropIfExists('industrial');
Schema::dropIfExists('coastal');
Schema::dropIfExists('art');
Schema::dropIfExists('brands');
Schema::dropIfExists('trashdog');
Schema::dropIfExists('dogshit');
Schema::dropIfExists('material');
```

### Models to delete

```
App\Models\Litter\Categories\Smoking
App\Models\Litter\Categories\Food
App\Models\Litter\Categories\Coffee
App\Models\Litter\Categories\SoftDrinks
App\Models\Litter\Categories\Alcohol
App\Models\Litter\Categories\Sanitary
App\Models\Litter\Categories\Dumping
App\Models\Litter\Categories\Other
App\Models\Litter\Categories\Industrial
App\Models\Litter\Categories\Coastal
App\Models\Litter\Categories\Art
App\Models\Litter\Categories\Brand
App\Models\Litter\Categories\TrashDog
App\Models\Litter\Categories\Dogshit
App\Models\Litter\Categories\Material
App\Models\CustomTag (old system, replaced by photo_tags with custom_tags)
```

---

## 3. Delete dead listener & action files — DONE

All dead files deleted (Session 16, 2026-02-26). Empty directories removed. Unit tests for deleted listeners also deleted (`DecreaseTeamTotalPhotosTest`, `IncreaseTeamTotalPhotosTest`). Zero remaining references in codebase (verified via grep). 690 tests passing after deletion.

---

## 4. Flush old Redis keys

Run a one-off artisan command to delete all legacy key patterns:

```php
// Old location keys (from AddContributorForLocationAction / UpdateTotalPhotosForLocationAction)
Redis::del(Redis::keys('country:*:user_ids'));
Redis::del(Redis::keys('state:*:user_ids'));
Redis::del(Redis::keys('city:*:user_ids'));
Redis::del(Redis::keys('country:*'));  // old total_photos hashes
Redis::del(Redis::keys('state:*'));
Redis::del(Redis::keys('city:*'));

// Old leaderboard keys (from UpdateLeaderboardsForLocationAction — ALREADY DELETED)
// These are replaced by {scope}:lb:xp ZSETs populated by RedisMetricsCollector
Redis::del(Redis::keys('xp.country.*'));
Redis::del(Redis::keys('leaderboard:country:*'));
Redis::del(Redis::keys('leaderboard:state:*'));
Redis::del(Redis::keys('leaderboard:city:*'));
```

**Warning:** Use `SCAN` instead of `KEYS` in production to avoid blocking Redis. Wrap this in a batched artisan command.

---

## 5. Clean up RedisKeys.php

Remove dead methods that nothing writes to or reads from:

```
RedisKeys::contributorSet()      — old SET-based contributors, replaced by HLL
RedisKeys::monthlyAggregates()   — old monthly hash pattern, replaced by metrics table cache
RedisKeys::dailyPhotos()         — old daily hash pattern, replaced by metrics table cache
```

---

## 6. Team clustering cleanup (DONE)

The following have already been completed:

- [x] `team_clusters` table dropped (migration: `2026_02_25_drop_team_clusters_table`)
- [x] `TeamCluster` model deleted
- [x] `GenerateTeamClusters` command deleted
- [x] `TeamsClusterController` rewritten to read from unified `clusters` table with `team_id` column
- [x] `dirty_teams` table created for incremental team reclustering
- [x] `PhotoObserver` marks teams dirty when team photos change (verified >= VERIFIED)

---

## 7. Team metrics migration — DONE

The three team listeners (`IncreaseTeamTotalPhotos`, `IncreaseTeamTotalLitter`, `DecreaseTeamTotalPhotos`) were deleted in Session 16 (2026-02-26) as part of §3 cleanup. They were already de-registered from `EventServiceProvider` — the files were orphaned dead code, not active holdovers. Team metrics are now handled by `MetricsService` via the unified `metrics` table with location scopes.

---

## 8. Verification checklist

Before performing any cleanup, verify:

- [ ] v5 migration has run successfully in production
- [ ] All existing photos have been re-processed through the new tag system
- [ ] `MetricsService::processPhoto()` is being called on tag verification
- [ ] Redis keys populated by `RedisMetricsCollector` are serving correct data to the frontend
- [ ] `metrics` table has data for all timescales and locations
- [ ] No code outside this list references the deprecated Photo methods (grep for `$photo->smoking`, `$photo->food`, `Photo::categories()`, `$photo->translate()`, `$photo->total()`, `result_string`)
- [ ] No code references the old Redis key patterns (`country:*:user_ids`, `leaderboard:country:*`, `xp.country.*`)
- [ ] `CompileResultsString` output (`result_string`) is no longer read by any frontend code

---

## 9. Remove stale Composer dependencies

These packages have zero runtime usage in the codebase and can be safely removed:

```bash
composer remove laravel/ui doctrine/dbal laravel/helpers spatie/emoji youthage/laravel-geohash
```

| Package | Why stale |
|---|---|
| `laravel/ui` | `Auth::routes()` commented out, auth controllers are fully custom (no UI traits) |
| `doctrine/dbal` | Zero imports, no `->change()` migration calls. Laravel 11 handles column modifications natively |
| `laravel/helpers` | Restores deprecated global helpers (`str_slug`, `array_get`, etc.) — none are used. `str_contains` is native PHP 8 |
| `spatie/emoji` | Zero imports of `Spatie\Emoji` — emojis are hardcoded as UTF-8 strings |
| `youthage/laravel-geohash` | Zero usage in `app/`. Only referenced in old migrations (columns already exist) |

### DPG license note

`benjamincrozat/laravel-dropbox-driver` (WTFPL) is the only non-OSI-licensed dependency. It's used as the backup disk in `config/backup.php`. To achieve full OSI compliance, switch backups to S3 disk and remove it:

```bash
composer remove benjamincrozat/laravel-dropbox-driver
```

Then update `config/backup.php` to use `'disks' => ['s3']` instead of `'disks' => ['dropbox']`.
