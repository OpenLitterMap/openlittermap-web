---
name: location-system
description: Countries, states, cities, ResolveLocationAction, Location base model, LocationType enum, geocoding, and location-level Redis data.
---

# Location System

Location tables store identity only (name, shortcode, FKs). All aggregates live in the `metrics` table and Redis. The `Location` base model computes all stats from Redis on access via `$appends`.

## Key Files

- `app/Models/Location/Location.php` — Abstract base model with Redis-backed computed attributes
- `app/Models/Location/Country.php` — Route key: `shortcode` (ISO 3166-1 alpha-2)
- `app/Models/Location/State.php` — Belongs to Country
- `app/Models/Location/City.php` — Belongs to Country + State
- `app/Actions/Locations/ResolveLocationAction.php` — Lat/lon -> Country/State/City via geocoding
- `app/Actions/Locations/ReverseGeocodeLocationAction.php` — LocationIQ API wrapper
- `app/Actions/Locations/LocationResult.php` — DTO returned by ResolveLocationAction
- `app/Enums/LocationType.php` — Global(0), Country(1), State(2), City(3)
- `app/Enums/Timescale.php` — AllTime(0), Daily(1), Weekly(2), Monthly(3), Yearly(4)

## Invariants

1. **Location tables store identity only.** No `total_*` counters, no `manual_verify`, no aggregates. All stats come from Redis or the `metrics` table.
2. **Photo table uses FK columns only:** `country_id`, `state_id`, `city_id`. Deprecated string columns (`country`, `county`, `city`, `display_name`, `location`, `road`) are dropped.
3. **Redis is a derived cache.** All Redis location data is rebuildable from the `metrics` table.
4. **HyperLogLog for contributor counts.** `PFCOUNT` gives ~0.81% error, O(1) space, append-only (cannot decrement).
5. **Country uses `shortcode` as route key**, not `id`. Routes: `/countries/{shortcode}`.

## Patterns

### ResolveLocationAction

```php
// app/Actions/Locations/ResolveLocationAction.php
public function run(float $lat, float $lon): LocationResult
{
    $address = $this->reverseGeocode->run($lat, $lon);

    $country = $this->resolveCountry($address);  // firstOrCreate by country_code
    $state   = $this->resolveState($country, $address);
    $city    = $this->resolveCity($country, $state, $address);

    return new LocationResult($country, $state, $city, $address, $displayName);
}
```

**Lookup strategy for city:** Searches keys in order: `city`, `town`, `city_district`, `village`, `hamlet`, `locality`, `county`.

### LocationResult DTO

```php
readonly class LocationResult
{
    public function __construct(
        public Country $country,
        public State   $state,
        public City    $city,
        public array   $addressArray,
        public string  $displayName,
    ) {}
}
```

### LocationType enum

```php
enum LocationType: int
{
    case Global  = 0;   // dbColumn: null,       scopePrefix: {g}
    case Country = 1;   // dbColumn: country_id, scopePrefix: {c:$id}
    case State   = 2;   // dbColumn: state_id,   scopePrefix: {s:$id}
    case City    = 3;   // dbColumn: city_id,     scopePrefix: {ci:$id}

    public function dbColumn(): ?string
    public function scopePrefix(int $id = 0): string
    public function modelClass(): ?string
    public function parentType(): ?self
}
```

### Location model computed attributes (from Redis)

```php
// All appended attributes on Country/State/City models:
$country->total_litter_redis      // HGET {c:$id}:stats litter
$country->total_photos_redis      // HGET {c:$id}:stats uploads
$country->total_contributors_redis // PFCOUNT {c:$id}:hll
$country->total_xp               // HGET {c:$id}:stats xp
$country->litter_data             // HGETALL {c:$id}:cat  (resolved to names)
$country->objects_data            // top 20 from {c:$id}:obj
$country->materials_data          // HGETALL {c:$id}:mat
$country->brands_data             // HGETALL {c:$id}:brands
$country->ppm                     // Cached time-series from metrics table (15min TTL)
$country->recent_activity         // Last 7 days daily counts (5min TTL)
```

### Location hierarchy rankings

```php
RedisKeys::globalCountryLitterRanking()           // {g}:rank:c:litter (ZSET)
RedisKeys::globalCountryPhotosRanking()           // {g}:rank:c:photos
RedisKeys::countryStateRanking($countryId, $metric)  // {c:$id}:rank:s:$metric
RedisKeys::stateCityRanking($stateId, $metric)       // {s:$id}:rank:ci:$metric
```

### Database schema (identity only)

```sql
countries (id, country, shortcode UNIQUE, created_by, timestamps)
states    (id, state, country_id, created_by, timestamps, UNIQUE(country_id, state))
cities    (id, city, country_id, state_id, created_by, timestamps, UNIQUE(country_id, state_id, city))
```

## LocationController API (v1)

`app/Http/Controllers/Location/LocationController.php` serves the locations browsing UI.

### Endpoints
- `GET /api/v1/locations` — Global view: list of countries with stats
- `GET /api/v1/locations/{type}/{id}` — Drill into country/state/city

### Response keys
```json
{
    "stats": { "countries": 120, "photos": 50000, "tags": 150000, ... },
    "locations": [
        {
            "id": 1, "name": "Ireland", "shortcode": "IE",
            "total_tags": 5000, "total_images": 1200, "total_members": 45,
            "xp": 15000, "created_at": "...", "updated_at": "...",
            "pct_tags": 3.3, "pct_photos": 2.4, "avg_tags_per_person": 111.1
        }
    ],
    "location_type": "country",
    "breadcrumbs": [ ... ],
    "activity": { "today": { ... }, "this_month": { ... } }
}
```

**Key naming:** Response uses `locations` (not `children`) and `location_type` (not `children_type`). Children use `total_tags`, `total_images`, `total_members` (not `tags`, `photos`, `contributors`). The Pinia store `useLocationsStore` reads these exact keys.

### Time filtering
Supports `?period=today|yesterday|this_month|last_month|this_year` and `?year=2024` query params. Mutually exclusive — year clears period and vice versa.

## Common Mistakes

- **Adding aggregate columns to location tables.** Aggregates live in `metrics` table and Redis. Location tables are identity only.
- **Using deprecated photo string columns.** `country`, `county`, `city`, `display_name`, `location`, `road` are dropped. Use `country_id`, `state_id`, `city_id` FKs.
- **Routing countries by ID instead of shortcode.** Country model has `getRouteKeyName(): 'shortcode'`.
- **Treating Redis location stats as authoritative.** They're derived caches. The `metrics` table is source of truth.
- **Decrementing HyperLogLog.** PFCOUNT is append-only. You cannot remove a contributor from HLL.
- **Forgetting `GeocodingException`.** `ResolveLocationAction::run()` throws `GeocodingException` when geocoding fails. Always handle this.
- **Using `children` or `children_type` in API responses.** The correct keys are `locations` and `location_type`.
- **Filtering locations by `manual_verify`.** This deprecated column is no longer used. Don't scope queries with it.
