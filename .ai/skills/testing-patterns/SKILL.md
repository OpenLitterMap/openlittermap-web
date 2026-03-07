---
name: testing-patterns
description: Writing and fixing tests, test factories, Event::fake patterns, auth guard testing, PHPUnit configuration, deprecated test groups, and common test pitfalls.
---

# Testing Patterns

972+ tests passing (1 skipped), 0 failures, 0 flaky. PHPUnit 10 with `RefreshDatabase`. Base `TestCase` flushes Redis + array cache in `setUp()` — prevents rate limiter state leaking between tests. 0 deprecated tests remaining (all 40 previously-deprecated files resolved: 18 dead removed, 22 fixed and undeprecated). Dead tests deleted: `DecreaseTeamTotalPhotosTest`, `IncreaseTeamTotalPhotosTest` (listeners removed), `CalculateTagsDifferenceActionTest` (action removed). 32 dead files deleted across v5 audit sessions.

## Key Files

- `phpunit.xml` — Config: excludes `deprecated` group, uses `olm_test` DB, Redis DB 2
- `tests/TestCase.php` — Base class: `RefreshDatabase` + Redis flush + `TagKeyCache::forgetAll()`
- `tests/Feature/Admin/AdminQueueTest.php` — 11 tests (queue endpoint: filters, pagination, exclusions, auth)
- `tests/Feature/UploadValidationTest.php` — 11 tests (EXIF datetime, GPS DMS conversion, zero denominator, 0,0 coords)
- `tests/Feature/HasPhotoUploads.php` — Trait for old upload-based tests (deprecated)
- `database/factories/PhotoFactory.php` — Photo with user, country, state, geom
- `database/factories/Location/CountryFactory.php` — Country with shortcode
- `database/factories/Location/StateFactory.php` — State with country FK
- `database/factories/Location/CityFactory.php` — City with country + state FKs
- `tests/Feature/Bbox/BoundingBoxRetiredTest.php` — 5 tests (all bbox endpoints return 410 Gone)
- `database/factories/Litter/Tags/CategoryFactory.php` — Category with unique key
- `database/factories/Litter/Tags/LitterObjectFactory.php` — LitterObject with unique key
- `database/factories/Litter/Tags/LitterObjectTypeFactory.php` — LitterObjectType with unique key + name
- `tests/Feature/Tags/TaggingArchitecturePhase1Test.php` — 20 tests (seeding, relationships, API, idempotency)
- `tests/Feature/Tags/ReplacePhotoTagsTest.php` — 5 tests (replace tags, ownership, auth, extra tags cleanup)
- `tests/Feature/Teams/TeamPhotosTest.php` — 35 tests (new_tags format, CLO tag edits, member stats, safeguarding, delete, revoke, approval, map)
- `tests/Feature/User/PublicProfileTest.php` — 4 tests (public profile data, private returns, privacy settings, 404)
- `tests/Feature/Leaderboard/LeaderboardTest.php` — 18 tests (all paths: global, country, state, city scopes)
- `tests/Feature/Auth/SanctumTokenAuthTest.php` — Mobile token auth tests
- `tests/Feature/Signup/CreateNewUserTest.php` — Registration flow tests
- `tests/Feature/Tags/ClassifyTagsServiceTest.php` — 12 tests (category aliases, deprecated tag mapping, unknown tags, getCategory)
- `tests/Feature/User/UsersUploadsControllerTest.php` — 9 tests (picked_up filter, pagination, tagged/untagged filters)

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
        Cache::flush(); // Reset rate limiters (array cache persists between tests)
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
// auth:api routes (Passport — mobile legacy) → use 'api' guard
$this->actingAs($user, 'api')->postJson('/api/photos/submit', [...]);

// auth:sanctum routes (SPA + Sanctum tokens) → use NO guard argument
$this->actingAs($user)->postJson('/api/settings/update', [...]);

// auth:api,web routes (both guards — teams, v3) → either guard works
$this->actingAs($user, 'api')->postJson('/api/v3/tags', [...]);
$this->actingAs($user)->postJson('/api/v3/tags', [...]); // also works

// CRITICAL: actingAs($user) (no guard) does NOT work for auth:api routes.
// actingAs($user, 'api') does NOT work for auth:sanctum routes.
// Mismatching guard ↔ middleware = silent 401 with no helpful error message.
```

### Using factories instead of uploading in tests

```php
// BAD: Upload via API requires auth:api, but test uses web guard
$this->actingAs($user); // web guard
$this->post('/api/photos/submit', [...]); // auth:api → 401, photo not created
$photo = $user->photos->last(); // null!

// GOOD: Use factories when testing non-upload behavior
$photo = Photo::factory()->create(['user_id' => $user->id]);
$this->actingAs($user)->post('/api/profile/photos/delete', ['photoid' => $photo->id]);
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

### Seeding tags + types for v5.1 architecture tests

```php
protected function setUp(): void
{
    parent::setUp();
    $this->seed([
        GenerateTagsSeeder::class,
        SeedLitterObjectTypesSeeder::class,
    ]);
}
// SeedLitterObjectTypesSeeder depends on GenerateTagsSeeder (needs categories/objects to exist)
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
- **Mismatching `actingAs()` guard with route middleware.** `actingAs($user)` (web guard) fails on `auth:api` routes. `actingAs($user, 'api')` fails on `auth:sanctum` routes. The mismatch causes silent 401s — no error message, just empty responses.
- **Uploading photos via `/api/photos/submit` in web-guard tests.** That route uses `auth:api`. If your test uses `actingAs($user)`, the upload returns 401 and no photo is created. Use `Photo::factory()` for tests that aren't testing upload behavior.
- **Expecting `geom` in JSON responses.** `Photo::$hidden = ['geom']` — binary spatial data is excluded from serialization.
- **Using `assertNull` for `Redis::zScore()` on missing members.** PHP Redis returns `false` (not `null`) when a ZSET member doesn't exist. Use `assertFalse(Redis::zScore($key, $member))`.
- **`HasPhotoUploads` trait double-encoding `address_array`.** The trait was `json_encode()`-ing `address_array` before insert, but the Photo model has an `'array'` cast on that column — causing double-encoding. Fixed to pass the raw array directly and let the model cast handle serialization.
- **Flaky 429s from rate limiter state.** `CACHE_DRIVER=array` in phpunit.xml means rate limiter entries persist between tests in the same PHPUnit process. The base `TestCase::setUp()` calls `Cache::flush()` to prevent this. If you add a new test file that hits throttled routes and see intermittent 429s, verify it extends the base `TestCase`.
