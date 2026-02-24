---
name: testing-patterns
description: Writing and fixing tests, test factories, Event::fake patterns, auth guard testing, PHPUnit configuration, deprecated test groups, and common test pitfalls.
---

# Testing Patterns

605 tests passing, 0 failures. PHPUnit 10 with `RefreshDatabase`. 0 deprecated tests remaining (all 40 previously-deprecated files resolved: 18 dead removed, 22 fixed and undeprecated).

## Key Files

- `phpunit.xml` — Config: excludes `deprecated` group, uses `olm_test` DB, Redis DB 2
- `tests/TestCase.php` — Base class: `RefreshDatabase` + Redis flush + `TagKeyCache::forgetAll()`
- `tests/Feature/HasPhotoUploads.php` — Trait for old upload-based tests (deprecated)
- `database/factories/PhotoFactory.php` — Photo with user, country, state, geom
- `database/factories/Location/CountryFactory.php` — Country with shortcode
- `database/factories/Location/StateFactory.php` — State with country FK
- `database/factories/Location/CityFactory.php` — City with country + state FKs
- `database/factories/Litter/Tags/CategoryFactory.php` — Category with unique key
- `database/factories/Litter/Tags/LitterObjectFactory.php` — LitterObject with unique key

## Invariants

1. **RefreshDatabase on every test.** The base `TestCase` uses `RefreshDatabase` and flushes Redis in `setUp()` and `tearDown()`.
2. **`photo_tags` uses FK columns.** Tests must create Category/LitterObject records and use their IDs — not strings.
3. **Deprecated tests are excluded by default.** Run with `--group=deprecated` to include them. They use old routes (`/submit`, `/add-tags`) that no longer work with v5.
4. **`Event::fake()` prevents listeners.** If testing event dispatch AND listener side effects (metrics), split into two tests or don't fake.
5. **Notifications table may not exist.** Fake events if testing notification-dispatching code, or create the `notifications` table.

## Patterns

### Base TestCase setup

```php
// tests/TestCase.php
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        TagKeyCache::forgetAll();
    }

    protected function tearDown(): void
    {
        Redis::connection()->flushdb();
        parent::tearDown();
    }
}
```

### Auth guard patterns

```php
// API guard (Passport) — for /api/* routes
$this->actingAs($user, 'api')->postJson('/api/v3/tags', [...]);

// Web guard (default) — for web routes
$this->actingAs($user)->postJson('/add-tags', [...]);

// IMPORTANT: actingAs() bypasses auth middleware entirely.
// It does NOT test real auth guards (Passport vs Sanctum).
// 'auth:api' and 'auth:web' will both pass with actingAs().
```

### Event::fake patterns

```php
// Pattern 1: Fake specific events (others still fire)
Event::fake([TagsVerifiedByAdmin::class]);
// ... do stuff ...
Event::assertDispatched(TagsVerifiedByAdmin::class, 1);
Event::assertNotDispatched(SchoolDataApproved::class);

// Pattern 2: Assert with callback
Event::assertDispatched(
    TagsVerifiedByAdmin::class,
    fn (TagsVerifiedByAdmin $e) => $e->photo_id === $photo->id
);

// Pattern 3: Test both event AND side effects (no fake)
// Don't fake — let ProcessPhotoMetrics listener run
$this->postJson('/api/v3/tags', $payload);
$photo->refresh();
$this->assertNotNull($photo->processed_at);  // MetricsService ran
```

### Spatie Permissions setup (required for team tests)

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

protected function setUp(): void
{
    parent::setUp();

    // CRITICAL: Reset cached permissions between tests
    app()[PermissionRegistrar::class]->forgetCachedPermissions();

    $permissions = collect([
        'create school team', 'manage school team',
        'toggle safeguarding', 'view student identities',
    ])->map(fn ($name) => Permission::firstOrCreate([
        'name' => $name, 'guard_name' => 'web'
    ]));

    $role = Role::firstOrCreate(['name' => 'school_manager', 'guard_name' => 'web']);
    $role->syncPermissions($permissions);
}
```

### Factory usage — let factories create related models

```php
// GOOD: Let factory handle related models
$photo = Photo::factory()->create([
    'is_public' => true,
    'verified' => VerificationStatus::ADMIN_APPROVED->value,
    'city_id' => City::factory(),  // PhotoFactory doesn't include city_id by default
]);

// BAD: Hardcoding IDs
$photo = Photo::factory()->create(['country_id' => 1]);  // May not exist
```

### Team type setup (required for team tests)

```php
// team_types.price has no default — always provide it
$communityType = TeamType::create(['team' => 'community', 'price' => 0]);
$schoolType = TeamType::create(['team' => 'school', 'price' => 0]);
```

### Seeding tags for tagging tests

```php
protected function setUp(): void
{
    parent::setUp();
    $this->seed([
        GenerateTagsSeeder::class,
        GenerateBrandsSeeder::class,
    ]);
}
```

### VerificationStatus in assertions

```php
// GOOD: Compare enum values
$photo->refresh();
$this->assertEquals(VerificationStatus::ADMIN_APPROVED, $photo->verified);
// or for ordering:
$this->assertTrue($photo->verified->value >= VerificationStatus::ADMIN_APPROVED->value);

// BAD: Compare to raw int (fails after enum cast)
$this->assertEquals(2, $photo->verified);  // Comparing enum to int
```

### Soft-delete assertions

```php
// After SoftDeletes trait added to Photo model:
$this->assertSoftDeleted('photos', ['id' => $photo->id]);

// NOT:
$this->assertDatabaseMissing('photos', ['id' => $photo->id]);
// (row still exists, just has deleted_at set)
```

### Running tests

```bash
php artisan test --compact                                    # All (excludes deprecated)
php artisan test --compact tests/Feature/Teams/               # Directory
php artisan test --compact tests/Feature/Photos/AddTagsToPhotoTest.php  # Single file
php artisan test --compact --filter=test_method_name          # Single test
php artisan test --compact --group=deprecated                 # Deprecated only
```

### Leaderboard test patterns

```php
// Leaderboard route uses auth:sanctum — use actingAs() with NO guard argument
$this->actingAs($user)->getJson('/api/leaderboard?locationType=global');

// Seed Redis ZSETs for leaderboard tests
Redis::zadd(RedisKeys::xpRanking(RedisKeys::global()), $xp, (string)$user->id);

// Seed per-user metrics rows for time-filtered tests
DB::table('metrics')->insert([
    'timescale' => 3, // monthly
    'location_type' => 0, // global
    'location_id' => 0,
    'user_id' => $user->id, // > 0 for per-user
    'year' => now()->year,
    'month' => now()->month,
    'xp' => 100,
    // ... other counters
]);
```

### Test DB restoration (if all tests fail with "table doesn't exist")

```bash
# PointsTest's internal migrate:fresh can corrupt olm_test
DB_DATABASE=olm_test DB_USERNAME=root DB_PASSWORD=secret php artisan migrate:fresh --no-interaction
DB_DATABASE=olm_test DB_USERNAME=root DB_PASSWORD=secret php artisan db:seed --class=GenerateTagsSeeder --no-interaction
DB_DATABASE=olm_test DB_USERNAME=root DB_PASSWORD=secret php artisan db:seed --class=GenerateBrandsSeeder --no-interaction
```

## Common Mistakes

- **Forgetting `PermissionRegistrar::forgetCachedPermissions()` in setUp.** Spatie caches permissions across tests. Reset explicitly.
- **Not providing `'price' => 0` for TeamType.** Column has no default — insert fails.
- **Faking events when you need side effects.** `Event::fake()` prevents all listeners. If you need MetricsService to run, don't fake `TagsVerifiedByAdmin`.
- **Using `assertDatabaseMissing` for soft-deleted records.** Use `assertSoftDeleted` instead.
- **Creating PhotoTags with string keys.** `photo_tags.category_id` and `litter_object_id` are integer FKs. Create Category/LitterObject records first.
- **Missing `city_id` in photo factory.** The default PhotoFactory doesn't include `city_id`. Add `'city_id' => City::factory()` when testing location-dependent features.
- **Testing auth guards with `actingAs()`.** This bypasses middleware. It doesn't verify that `auth:api` vs `auth:web` actually works.
- **Expecting `geom` in JSON responses.** `Photo::$hidden = ['geom']` — binary spatial data is excluded from serialization.
- **Using `assertNull` for `Redis::zScore()` on missing members.** PHP Redis returns `false` (not `null`) when a ZSET member doesn't exist. Use `assertFalse(Redis::zScore($key, $member))`.
