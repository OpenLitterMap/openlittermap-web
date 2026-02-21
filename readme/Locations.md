# OpenLitterMap v5 — Locations System

## Overview

This document covers the v5 locations architecture: what's changing, what's being deprecated, the new database schema, Redis design, and the re-engineered upload flow.

The core principle: **locations tables store identity only; all aggregates live in the `metrics` table and Redis.**

---

## Current State (v4) — Problems

### 1. Redundant data on `photos` table

The photo record stores location data in two ways simultaneously:

| Foreign Keys (keep) | String Columns (deprecate) |
|---|---|
| `country_id` | `country`, `country_code` |
| `state_id` | `county` (actually state name) |
| `city_id` | `city`, `display_name`, `location`, `road` |

The string columns are denormalized copies of data that already exists on the location tables. They made sense before we had proper foreign keys but now they just drift and waste space.

### 2. Legacy category counters on `cities` table

The `cities` table has 16+ `total_*` columns:

```
total_smoking, total_cigaretteButts, total_food, total_softdrinks,
total_plasticBottles, total_alcohol, total_coffee, total_drugs,
total_dumping, total_industrial, total_needles, total_sanitary,
total_other, total_coastal, total_pathways, total_art, total_dogshit
```

These are completely replaced by the `metrics` table time-series which tracks tags by category, object, material, and brand at all location levels with full time-series granularity.

### 3. Legacy columns on all location tables

| Column | Tables | Status |
|---|---|---|
| `manual_verify` | countries, states, cities | Deprecated — no longer used |
| `littercoin_paid` | countries, states, cities | Deprecated — Littercoin tracked elsewhere |
| `countrynameb` | countries | Deprecated — unused alternate name |
| `statenameb` | states | Deprecated — unused alternate name |
| `user_id_last_uploaded` | countries, states, cities | Deprecated — derivable from photos table |

### 4. `UpdateLeaderboardsForLocationAction` is deprecated

Already marked `@deprecated` but still called from the upload controller. It writes to old Redis key patterns:

```
xp.country.{id}                                    # old format
leaderboard:country:{id}:total                      # old format  
leaderboard:country:{id}:{year}:{month}:{day}       # old format
```

The `MetricsService` + `RedisMetricsCollector` now handles all of this via the unified metrics pipeline.

### 5. Events overlap with MetricsService

| Event | What it does | v5 status |
|---|---|---|
| `ImageUploaded` | Updates total_contributors_redis, broadcasts to map | **Keep** — real-time broadcast still needed |
| `IncrementPhotoMonth` | Increments month counters per location | **Remove** — metrics table handles time-series |
| `NewCountryAdded` | Notifies Twitter/Slack | **Keep** — notification, not metrics |
| `NewStateAdded` | Notifies Twitter/Slack | **Keep** — notification, not metrics |
| `NewCityAdded` | Notifies Twitter/Slack | **Keep** — notification, not metrics |

### 6. UploadHelper error handling

Falls back to sentinel records (`error_country`, `error_state`, `error_city`). This means:

- Bad geocode results silently create photos attached to error locations
- These pollute metrics and leaderboards
- No way to distinguish "geocode failed" from "geocode returned unexpected format"

### 7. Upload controller does too much

The `__invoke` method handles: image processing → S3 upload → bbox upload → GPS extraction → reverse geocoding → location resolution → photo creation → XP/leaderboards → 5 different events. This needs to be broken into focused steps.

---

## v5 Target Schema

### `countries` table

```sql
-- Keep
id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
country         VARCHAR(255) NOT NULL        -- Display name
shortcode       VARCHAR(2) NOT NULL UNIQUE   -- ISO 3166-1 alpha-2
created_by      BIGINT UNSIGNED NULLABLE     -- User who first triggered creation
created_at      TIMESTAMP
updated_at      TIMESTAMP

-- Deprecate (migration to drop)
manual_verify           -- unused
littercoin_paid         -- tracked elsewhere  
countrynameb            -- unused alternate name
user_id_last_uploaded   -- derivable from photos
```

### `states` table

```sql
-- Keep  
id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
state           VARCHAR(255) NOT NULL
country_id      BIGINT UNSIGNED NOT NULL     -- FK → countries
created_by      BIGINT UNSIGNED NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP

UNIQUE KEY (country_id, state)

-- Deprecate (migration to drop)
statenameb              -- unused alternate name
manual_verify           -- unused
littercoin_paid         -- tracked elsewhere
user_id_last_uploaded   -- derivable from photos
```

### `cities` table

```sql
-- Keep
id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY  
city            VARCHAR(255) NOT NULL
country_id      BIGINT UNSIGNED NOT NULL     -- FK → countries
state_id        BIGINT UNSIGNED NOT NULL     -- FK → states
created_by      BIGINT UNSIGNED NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP

UNIQUE KEY (country_id, state_id, city)

-- Deprecate (migration to drop) — ALL total_* columns
total_smoking, total_cigaretteButts, total_food, total_softdrinks,
total_plasticBottles, total_alcohol, total_coffee, total_drugs,
total_dumping, total_industrial, total_needles, total_sanitary,
total_other, total_coastal, total_pathways, total_art, total_dogshit,
manual_verify, littercoin_paid, user_id_last_uploaded
```

### `photos` table — columns to deprecate

```sql
-- Deprecate (migration to drop)
country         -- redundant, use country_id → countries.country
country_code    -- redundant, use country_id → countries.shortcode
county          -- confusingly named (it's state), use state_id → states.state
city            -- redundant, use city_id → cities.city
display_name    -- full OSM address string, move to address_array JSON
location        -- first element of address array, derivable
road            -- second element of address array, derivable

-- Keep
country_id, state_id, city_id  -- foreign keys
address_array                   -- raw OSM response (JSON), source of truth for display_name/location/road
lat, lon, geohash              -- coordinates
```

### `metrics` table (already exists in v5)

This is the single source of truth for all aggregates. See `MetricsService` for full schema.

```sql
-- Composite unique key
(timescale, location_type, location_id, user_id, year, month, week, bucket_date)

-- Additive counters
uploads, tags, brands, materials, custom_tags, litter, xp
```

**LocationType enum:**
- `0` = Global
- `1` = Country
- `2` = State
- `3` = City

**Timescales:**
- `0` = All-time
- `1` = Daily
- `2` = Weekly (ISO)
- `3` = Monthly
- `4` = Yearly

---

## v5 Location Models

### Country model (cleaned)

```php
class Country extends Location
{
    protected $fillable = [
        'country',
        'shortcode', 
        'created_by',
    ];

    protected $appends = [
        'total_litter_redis',
        'total_photos_redis', 
        'total_contributors_redis',
        'litter_data',
        'brands_data',
        'objects_data',
        'materials_data',
        'recent_activity',
        'total_xp',
        'ppm',
        'updatedAtDiffForHumans',
        'total_ppm',
    ];

    public function getRouteKeyName(): string
    {
        return 'country';
    }

    public function states()
    {
        return $this->hasMany(State::class);
    }

    public function cities()
    {
        return $this->hasMany(City::class);
    }
}
```

### State model (cleaned)

```php
class State extends Location
{
    protected $fillable = [
        'state',
        'country_id',
        'created_by',
    ];

    // Same $appends as Country

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function cities()
    {
        return $this->hasMany(City::class);
    }
}
```

### City model (cleaned)

```php
class City extends Location
{
    protected $fillable = [
        'city',
        'country_id',
        'state_id',
        'created_by',
    ];

    // Same $appends as Country

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
```

### Location base model `$appends`

All aggregate data (`total_litter_redis`, `total_photos_redis`, etc.) should be computed from Redis, which is populated by `RedisMetricsCollector` and rebuildable from the `metrics` table. The base `Location` model provides these accessors.

---

## Redis Key Design (v5)

Redis is a **derived cache** — rebuildable from the `metrics` table at any time.

### Scope prefixes (from `LocationType` enum)

```
global              → LocationType::Global
country:{id}        → LocationType::Country  
state:{id}          → LocationType::State
city:{id}           → LocationType::City
```

### Key patterns managed by `RedisMetricsCollector`

```
# Aggregate counters (HINCRBY on hash keys)
metrics:{scope}:totals          → { uploads, tags, litter, xp, brands, materials, custom_tags }

# Contributors (SADD on set keys)  
metrics:{scope}:contributors    → SET of user IDs

# Per-tag counters
metrics:{scope}:objects         → HASH { object_id: count }
metrics:{scope}:materials       → HASH { material_id: count }
metrics:{scope}:brands          → HASH { brand_id: count }
metrics:{scope}:categories      → HASH { category_id: count }

# Leaderboards (ZINCRBY on sorted sets)
metrics:{scope}:leaderboard     → ZSET { user_id: xp }
```

### Deprecated Redis keys (to remove)

```
# Old leaderboard format — replaced by metrics:{scope}:leaderboard
xp.country.{id}
xp.country.{id}.state.{id}  
xp.country.{id}.state.{id}.city.{id}
leaderboard:country:{id}:total
leaderboard:state:{id}:total
leaderboard:city:{id}:total
leaderboard:country:{id}:{year}:{month}:{day}
leaderboard:state:{id}:{year}:{month}:{day}
leaderboard:city:{id}:{year}:{month}:{day}
leaderboard:country:{id}:{year}:{month}
leaderboard:state:{id}:{year}:{month}
leaderboard:city:{id}:{year}:{month}
leaderboard:country:{id}:{year}
leaderboard:state:{id}:{year}
leaderboard:city:{id}:{year}
```

---

## Migrations

### Migration 1: Drop deprecated columns from locations

```php
Schema::table('countries', function (Blueprint $table) {
    $table->dropColumn([
        'manual_verify',
        'littercoin_paid', 
        'countrynameb',
        'user_id_last_uploaded',
    ]);
});

Schema::table('states', function (Blueprint $table) {
    $table->dropColumn([
        'statenameb',
        'manual_verify',
        'littercoin_paid',
        'user_id_last_uploaded',
    ]);
});

Schema::table('cities', function (Blueprint $table) {
    $table->dropColumn([
        'total_smoking',
        'total_cigaretteButts', 
        'total_food',
        'total_softdrinks',
        'total_plasticBottles',
        'total_alcohol',
        'total_coffee',
        'total_drugs',
        'total_dumping',
        'total_industrial',
        'total_needles',
        'total_sanitary',
        'total_other',
        'total_coastal',
        'total_pathways',
        'total_art',
        'total_dogshit',
        'manual_verify',
        'littercoin_paid',
        'user_id_last_uploaded',
    ]);
});
```

### Migration 2: Drop deprecated columns from photos

```php
Schema::table('photos', function (Blueprint $table) {
    $table->dropColumn([
        'country',
        'country_code',
        'county',        // actually state name
        'city',          // string duplicate of city_id
        'display_name',  // derivable from address_array
        'location',      // derivable from address_array
        'road',          // derivable from address_array
    ]);
});
```

**Important:** Run Migration 2 only after confirming no code reads these columns. During transition, you can mark them as nullable/deprecated first, then drop in a follow-up migration.

---

## Re-engineered Upload Flow

### Current flow (v4)

```
UploadPhotoController::__invoke()
├── MakeImageAction::run()              → image + EXIF
├── UploadPhotoAction::run() × 2        → S3 + bbox
├── getCoordinatesFromPhoto()           → lat/lon
├── ReverseGeocodeLocationAction::run() → OSM address
├── UploadHelper::getCountry/State/City → firstOrCreate locations
├── Photo::create()                     → 20+ columns including string locations
├── event(ImageUploaded)                → broadcast + contributor counts
├── UpdateLeaderboardsForLocationAction → deprecated Redis writes
├── event(NewCountryAdded)              → notification
├── event(NewStateAdded)                → notification  
├── event(NewCityAdded)                 → notification
└── event(IncrementPhotoMonth)          → deprecated month counters
```

### New flow (v5)

```
UploadPhotoController::__invoke()
├── MakeImageAction::run()              → image + EXIF
├── UploadPhotoAction::run() × 2        → S3 + bbox  
├── getCoordinatesFromPhoto()           → lat/lon
├── ResolveLocationAction::run()        → country, state, city (replaces UploadHelper)
├── Photo::create()                     → slim columns (FKs only, no string duplication)
├── MetricsService::processPhoto()      → MySQL metrics + Redis (replaces leaderboards action)
├── event(ImageUploaded)                → broadcast to real-time map
├── event(NewCountryAdded)              → notification (if wasRecentlyCreated)
├── event(NewStateAdded)                → notification (if wasRecentlyCreated)
└── event(NewCityAdded)                 → notification (if wasRecentlyCreated)
```

**Removed:**
- `UpdateLeaderboardsForLocationAction` — replaced by `MetricsService`
- `IncrementPhotoMonth` event — replaced by `metrics` table time-series
- String location columns from `Photo::create()`
- `UploadHelper` class — replaced by `ResolveLocationAction`

### New `ResolveLocationAction`

Replaces `UploadHelper` with cleaner error handling:

```php
namespace App\Actions\Locations;

use App\Models\Location\{Country, State, City};

class ResolveLocationAction
{
    /**
     * Resolve lat/lon to Country, State, City.
     * 
     * @throws \App\Exceptions\GeocodingException
     */
    public function run(float $lat, float $lon): LocationResult
    {
        $revGeoCode = app(ReverseGeocodeLocationAction::class)->run($lat, $lon);
        $address = $revGeoCode['address'];

        $country = $this->resolveCountry($address);
        $state = $this->resolveState($country, $address);
        $city = $this->resolveCity($country, $state, $address);

        return new LocationResult(
            country: $country,
            state: $state,
            city: $city,
            addressArray: $address,
            displayName: $revGeoCode['display_name'],
        );
    }

    private function resolveCountry(array $address): Country
    {
        $code = $address['country_code'] ?? null;

        if (!$code) {
            throw new \App\Exceptions\GeocodingException('No country_code in geocode response');
        }

        return Country::firstOrCreate(
            ['shortcode' => strtoupper($code)],
            ['country' => $address['country'] ?? '', 'created_by' => auth()->id()]
        );
    }

    private function resolveState(Country $country, array $address): State
    {
        $name = $this->lookup($address, ['state', 'county', 'region', 'state_district']);

        if (!$name) {
            throw new \App\Exceptions\GeocodingException('No state found in geocode response');
        }

        return State::firstOrCreate(
            ['state' => $name, 'country_id' => $country->id],
            ['created_by' => auth()->id()]
        );
    }

    private function resolveCity(Country $country, State $state, array $address): City
    {
        $name = $this->lookup($address, ['city', 'town', 'city_district', 'village', 'hamlet', 'locality', 'county']);

        if (!$name) {
            throw new \App\Exceptions\GeocodingException('No city found in geocode response');
        }

        return City::firstOrCreate(
            ['country_id' => $country->id, 'state_id' => $state->id, 'city' => $name],
            ['created_by' => auth()->id()]
        );
    }

    private function lookup(array $address, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!empty($address[$key])) {
                return $address[$key];
            }
        }
        return null;
    }
}
```

### New `LocationResult` DTO

```php
namespace App\Actions\Locations;

use App\Models\Location\{Country, State, City};

class LocationResult
{
    public function __construct(
        public readonly Country $country,
        public readonly State $state,
        public readonly City $city,
        public readonly array $addressArray,
        public readonly string $displayName,
    ) {}
}
```

### New `UploadPhotoController` (v5)

```php
namespace App\Http\Controllers\Uploads;

use Geohash\GeoHash;
use App\Models\Photo;
use App\Events\{ImageUploaded, NewCityAdded, NewCountryAdded, NewStateAdded};
use App\Actions\Photos\{MakeImageAction, UploadPhotoAction};
use App\Actions\Locations\ResolveLocationAction;
use App\Services\Metrics\MetricsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadPhotoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UploadPhotoController extends Controller
{
    public function __construct(
        private MakeImageAction $makeImageAction,
        private UploadPhotoAction $uploadPhotoAction,
        private ResolveLocationAction $resolveLocationAction,
        private MetricsService $metricsService,
    ) {}

    public function __invoke(UploadPhotoRequest $request): JsonResponse
    {
        $user = Auth::user();
        $file = $request->file('photo');

        // 1. Process image & extract EXIF
        $imageAndExif = $this->makeImageAction->run($file);
        $image = $imageAndExif['image'];
        $exif = $imageAndExif['exif'];
        $dateTime = getDateTimeForPhoto($exif);

        // 2. Upload full image + bbox thumbnail
        $imageName = $this->uploadPhotoAction->run($image, $dateTime, $file->hashName());
        $bboxImageName = $this->uploadPhotoAction->run(
            $this->makeImageAction->run($file, true)['image'],
            $dateTime,
            $file->hashName(),
            'bbox'
        );

        // 3. Resolve location from GPS coordinates
        $coordinates = getCoordinatesFromPhoto($exif);
        $lat = $coordinates[0];
        $lon = $coordinates[1];

        $location = $this->resolveLocationAction->run($lat, $lon);

        // 4. Create photo (slim — no string location duplication)
        $photo = Photo::create([
            'user_id' => $user->id,
            'filename' => $imageName,
            'datetime' => $dateTime,
            'remaining' => !$user->picked_up,
            'lat' => $lat,
            'lon' => $lon,
            'model' => $exif['Model'] ?? 'Unknown',
            'country_id' => $location->country->id,
            'state_id' => $location->state->id,
            'city_id' => $location->city->id,
            'platform' => 'web',
            'geohash' => (new GeoHash())->encode($lat, $lon),
            'team_id' => $user->active_team,
            'five_hundred_square_filepath' => $bboxImageName,
            'address_array' => json_encode($location->addressArray),
        ]);

        // 5. Broadcast to real-time map
        event(new ImageUploaded($user, $photo, $location->country, $location->state, $location->city));

        // 6. Notify on new locations
        if ($location->country->wasRecentlyCreated) {
            event(new NewCountryAdded($location->country->country, $location->country->shortcode, now()));
        }
        if ($location->state->wasRecentlyCreated) {
            event(new NewStateAdded($location->state->state, $location->country->country, now()));
        }
        if ($location->city->wasRecentlyCreated) {
            event(new NewCityAdded(
                $location->city->city, $location->state->state, $location->country->country,
                now(), $location->city->id, $lat, $lon, $photo->id
            ));
        }

        // 7. MetricsService processes after tags are added (not here)
        // Tags are added in a separate step. MetricsService::processPhoto()
        // is called when the user submits tags, not at upload time.
        // At upload time, the photo has 0 tags and 0 XP.

        return response()->json(['success' => true]);
    }
}
```

---

## When MetricsService Runs

Important distinction: **photo upload ≠ photo tagging**.

1. **Upload** — the controller above creates the photo with coordinates, image, and location FKs. No tags yet.
2. **Tagging** — the user adds tags (litter categories, materials, brands) in a separate request. This is when `MetricsService::processPhoto()` should run, because that's when tags, XP, and litter counts exist.

If tags are submitted at upload time (e.g. pre-tagged uploads), then `MetricsService::processPhoto()` can be called at the end of the upload controller. But for the typical web flow where tagging is separate, the metrics call belongs in the tagging controller.

---

## Files to Delete / Deprecate

| File | Action | Reason |
|---|---|---|
| `App\Helpers\Post\UploadHelper` | **Delete** | Replaced by `ResolveLocationAction` |
| `App\Actions\Locations\UpdateLeaderboardsForLocationAction` | **Delete** | Already `@deprecated`, replaced by `MetricsService` |
| `App\Actions\Locations\UpdateLeaderboardsXpAction` | **Delete** | Called only by the above |
| `App\Events\Photo\IncrementPhotoMonth` | **Delete** | Replaced by `metrics` table time-series |

---

## Migration Checklist

1. **Create** `ResolveLocationAction` + `LocationResult` DTO
2. **Create** `GeocodingException` for proper error handling
3. **Update** `UploadPhotoController` to v5 flow
4. **Update** `ImageUploaded` event if it references deprecated photo columns
5. **Verify** no code reads the deprecated photo string columns (`country`, `county`, `city`, etc.)
6. **Run** Migration 1: drop deprecated location columns
7. **Run** Migration 2: drop deprecated photo columns
8. **Delete** `UploadHelper`, `UpdateLeaderboardsForLocationAction`, `IncrementPhotoMonth`
9. **Clean up** old Redis keys (run a one-off script to delete the deprecated key patterns)
10. **Update** `$fillable` on Country, State, City models
11. **Remove** `$appends` entries that reference deleted columns (if any)

---

## API Endpoints (Location Data)

All location aggregate data is served from Redis (fast) with MySQL metrics table as the source of truth (rebuildable).

| Endpoint | Source | Notes |
|---|---|---|
| `GET /api/countries` | DB + Redis appends | List with aggregates from Redis |
| `GET /api/countries/{country}` | DB + Redis appends | Single country with full data |
| `GET /api/countries/{country}/states` | DB + Redis appends | States within country |
| `GET /api/states/{state}/cities` | DB + Redis appends | Cities within state |
| `GET /api/leaderboard/{scope}` | Redis sorted sets | `metrics:{scope}:leaderboard` |

The `$appends` on location models (`total_litter_redis`, `total_photos_redis`, etc.) read directly from Redis hashes, making these endpoints fast without any MySQL aggregate queries.
