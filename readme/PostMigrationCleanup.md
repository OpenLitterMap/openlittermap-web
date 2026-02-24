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

## 3. Delete dead listener & action files

These were removed from `EventServiceProvider` during the v5 release but the files may still exist on disk:

### Listeners

```
App\Listeners\AddTags\IncrementLocation
App\Listeners\AddTags\CompileResultsString
App\Listeners\Locations\AddLocationContributor
App\Listeners\Locations\IncreaseLocationTotalPhotos
App\Listeners\Locations\DecreaseLocationTotalPhotos
App\Listeners\Locations\RemoveLocationContributor
App\Listeners\Locations\User\UpdateUserIdLastUpdatedLocation
App\Listeners\User\UpdateUserCategories
App\Listeners\User\UpdateUserTimeSeries
App\Listeners\UpdateTimes\IncrementCountryMonth
App\Listeners\UpdateTimes\IncrementStateMonth
App\Listeners\UpdateTimes\IncrementCityMonth
```

### Actions

```
App\Actions\Locations\UpdateLeaderboardsForLocationAction  — ALREADY DELETED (Session 5)
App\Actions\Locations\UpdateLeaderboardsXpAction           — ALREADY DELETED (Session 5)
App\Actions\Locations\AddContributorForLocationAction
App\Actions\Locations\UpdateTotalPhotosForLocationAction
App\Actions\Locations\RemoveContributorForLocationAction   — ALREADY DELETED (Session 3)
```

### Events

```
App\Events\Photo\IncrementPhotoMonth
```

### Helpers

```
App\Helpers\Post\UploadHelper
```

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

## 6. Team metrics migration (future)

Three team listeners survive the v5 release as temporary holdovers:

```
IncreaseTeamTotalPhotos    → ImageUploaded
IncreaseTeamTotalLitter    → TagsVerifiedByAdmin
DecreaseTeamTotalPhotos    → ImageDeleted
```

These write directly to SQL (`teams` and `team_user` pivot tables). The plan is to migrate team metrics into `MetricsService` by adding a `LocationType::Team` scope. Once that's done, these three listeners can be deleted.

---

## 7. Verification checklist

Before performing any cleanup, verify:

- [ ] v5 migration has run successfully in production
- [ ] All existing photos have been re-processed through the new tag system
- [ ] `MetricsService::processPhoto()` is being called on tag verification
- [ ] Redis keys populated by `RedisMetricsCollector` are serving correct data to the frontend
- [ ] `metrics` table has data for all timescales and locations
- [ ] No code outside this list references the deprecated Photo methods (grep for `$photo->smoking`, `$photo->food`, `Photo::categories()`, `$photo->translate()`, `$photo->total()`, `result_string`)
- [ ] No code references the old Redis key patterns (`country:*:user_ids`, `leaderboard:country:*`, `xp.country.*`)
- [ ] `CompileResultsString` output (`result_string`) is no longer read by any frontend code
