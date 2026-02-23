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

## Common Mistakes

- **Adding aggregate columns to location tables.** Aggregates live in `metrics` table and Redis. Location tables are identity only.
- **Using deprecated photo string columns.** `country`, `county`, `city`, `display_name`, `location`, `road` are dropped. Use `country_id`, `state_id`, `city_id` FKs.
- **Routing countries by ID instead of shortcode.** Country model has `getRouteKeyName(): 'shortcode'`.
- **Treating Redis location stats as authoritative.** They're derived caches. The `metrics` table is source of truth.
- **Decrementing HyperLogLog.** PFCOUNT is append-only. You cannot remove a contributor from HLL.
- **Forgetting `GeocodingException`.** `ResolveLocationAction::run()` throws `GeocodingException` when geocoding fails. Always handle this.
