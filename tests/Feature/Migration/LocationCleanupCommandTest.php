<?php

namespace Tests\Feature\Migration;

use App\Models\Users\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests for LocationCleanupCommand.
 *
 * Does NOT use DatabaseTransactions because the command runs DDL
 * (CREATE TABLE, ALTER TABLE ADD INDEX) which causes MySQL implicit
 * commits that destroy savepoints. Instead, we track max IDs before
 * each test and clean up only records we created.
 */
class LocationCleanupCommandTest extends TestCase
{
    private int $testUserId;

    // Snapshot of max IDs before each test — only clean up records above these
    private int $maxCountryId;
    private int $maxStateId;
    private int $maxCityId;
    private int $maxPhotoId;

    protected function setUp(): void
    {
        parent::setUp();

        // Safety: fail fast if not running on the test database
        $dbName = DB::getDatabaseName();
        $this->assertStringContainsString('test', strtolower($dbName),
            "Refusing to run destructive tests on database '{$dbName}'. Expected a test database.");

        // Snapshot current max IDs
        $this->maxCountryId = (int) (DB::table('countries')->max('id') ?? 0);
        $this->maxStateId   = (int) (DB::table('states')->max('id') ?? 0);
        $this->maxCityId    = (int) (DB::table('cities')->max('id') ?? 0);
        $this->maxPhotoId   = (int) (DB::table('photos')->max('id') ?? 0);

        $this->testUserId = User::factory()->create()->id;

        if (Schema::hasTable('location_merges')) {
            DB::table('location_merges')->truncate();
        }

        // Drop unique constraints that may exist from production schema or prior test runs.
        // Required so we can insert duplicate states/cities for testing.
        $this->dropIndexIfExists('countries', 'uq_country_shortcode');
        $this->dropIndexIfExists('states', 'uq_state_country');
        $this->dropIndexIfExists('cities', 'uq_city_state');
    }

    protected function tearDown(): void
    {
        // Delete in FK-safe order: photos → cities → states → countries
        DB::table('photos')->where('id', '>', $this->maxPhotoId)->delete();
        DB::table('cities')->where('id', '>', $this->maxCityId)->delete();
        DB::table('states')->where('id', '>', $this->maxStateId)->delete();
        DB::table('countries')->where('id', '>', $this->maxCountryId)->delete();

        if (Schema::hasTable('location_merges')) {
            DB::table('location_merges')->truncate();
        }

        $this->dropIndexIfExists('countries', 'uq_country_shortcode');
        $this->dropIndexIfExists('states', 'uq_state_country');
        $this->dropIndexIfExists('cities', 'uq_city_state');

        parent::tearDown();
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $existing = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        if (!empty($existing)) {
            DB::statement("DROP INDEX {$indexName} ON {$table}");
        }
    }

    private static int $shortcodeCounter = 0;

    private function uniqueShortcode(): string
    {
        return 'z' . str_pad((string) ++self::$shortcodeCounter, 2, '0', STR_PAD_LEFT);
    }

    private function createCountry(array $attrs = []): int
    {
        return DB::table('countries')->insertGetId(array_merge([
            'country' => 'TestCountry',
            'shortcode' => $this->uniqueShortcode(),
            'manual_verify' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    private function createState(int $countryId, array $attrs = []): int
    {
        return DB::table('states')->insertGetId(array_merge([
            'state' => 'TestState',
            'country_id' => $countryId,
            'manual_verify' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    private function createCity(int $stateId, array $attrs = []): int
    {
        $countryId = $attrs['country_id']
            ?? (int) DB::table('states')->where('id', $stateId)->value('country_id');

        return DB::table('cities')->insertGetId(array_merge([
            'city' => 'TestCity',
            'state_id' => $stateId,
            'country_id' => $countryId,
            'manual_verify' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    /**
     * Raw SQL insert because photos.geom is POINT NOT NULL SRID 4326
     * and cell_x/cell_y are generated columns from lat/lon.
     */
    private function createPhoto(int $countryId, int $stateId, int $cityId): int
    {
        $lat = 51.8985 + (mt_rand(-1000, 1000) / 10000);
        $lon = -8.4756 + (mt_rand(-1000, 1000) / 10000);

        DB::insert("
            INSERT INTO photos
                (user_id, country_id, state_id, city_id, lat, lon, geom, filename, model, datetime, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ST_SRID(POINT(?, ?), 4326), ?, 'test', NOW(), NOW(), NOW())
        ", [
            $this->testUserId,
            $countryId,
            $stateId,
            $cityId,
            $lat,
            $lon,
            $lon,  // POINT(X=longitude, Y=latitude)
            $lat,
            'test_' . uniqid() . '.jpg',
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    /**
     * Get a photo's current location columns.
     */
    private function getPhotoLocation(int $photoId): object
    {
        return DB::table('photos')
            ->select('country_id', 'state_id', 'city_id')
            ->where('id', $photoId)
            ->first();
    }

    private function photoCount(): int
    {
        return DB::table('photos')->count();
    }

    // ─────────────────────────────────────────────
    // Country Merge Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function it_merges_duplicate_countries_keeping_one_with_manual_verify()
    {
        $keeperId = $this->createCountry(['country' => 'TestNorway', 'manual_verify' => 1]);
        $loserId  = $this->createCountry(['country' => 'TestNorway', 'manual_verify' => 0]);

        $stateId = $this->createState($keeperId, ['state' => 'TestOslo']);
        $cityId  = $this->createCity($stateId, ['city' => 'TestOsloCity']);
        $this->createPhoto($keeperId, $stateId, $cityId);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('countries', ['id' => $loserId]);
        $this->assertDatabaseHas('countries', ['id' => $keeperId]);
    }

    /** @test */
    public function it_reassigns_photos_from_loser_country_to_keeper()
    {
        $keeperId = $this->createCountry(['country' => 'TestSwiss', 'manual_verify' => 1]);
        $loserId  = $this->createCountry(['country' => 'TestSwiss', 'manual_verify' => 0]);

        // Photo under keeper — should stay
        $keeperState = $this->createState($keeperId, ['state' => 'TestZurich']);
        $keeperCity  = $this->createCity($keeperState, ['city' => 'TestZurichCity']);
        $keeperPhotoId = $this->createPhoto($keeperId, $keeperState, $keeperCity);

        // Photo under loser — must be reassigned
        $loserState = $this->createState($loserId, ['state' => 'TestBern']);
        $loserCity  = $this->createCity($loserState, ['country_id' => $loserId, 'city' => 'TestBernCity']);
        $loserPhotoId = $this->createPhoto($loserId, $loserState, $loserCity);

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        // Loser country is gone
        $this->assertDatabaseMissing('countries', ['id' => $loserId]);

        // Keeper photo unchanged
        $keeperPhoto = $this->getPhotoLocation($keeperPhotoId);
        $this->assertEquals($keeperId, $keeperPhoto->country_id);
        $this->assertEquals($keeperState, $keeperPhoto->state_id);
        $this->assertEquals($keeperCity, $keeperPhoto->city_id);

        // Loser photo's country_id reassigned to keeper
        $loserPhoto = $this->getPhotoLocation($loserPhotoId);
        $this->assertEquals($keeperId, $loserPhoto->country_id);

        // Children moved to keeper
        $this->assertEquals($keeperId, DB::table('states')->where('id', $loserState)->value('country_id'));
        $this->assertEquals($keeperId, DB::table('cities')->where('id', $loserCity)->value('country_id'));

        $this->assertEquals($photoBefore, $this->photoCount());
    }

    /** @test */
    public function it_keeps_country_with_most_photos_when_both_have_same_manual_verify()
    {
        $fewerPhotos = $this->createCountry(['country' => 'TestDupCountry', 'manual_verify' => 1]);
        $morePhotos  = $this->createCountry(['country' => 'TestDupCountry', 'manual_verify' => 1]);

        $s1 = $this->createState($fewerPhotos, ['state' => 'S1']);
        $c1 = $this->createCity($s1, ['city' => 'C1']);
        $lonePhotoId = $this->createPhoto($fewerPhotos, $s1, $c1);

        $s2 = $this->createState($morePhotos, ['state' => 'S2']);
        $c2 = $this->createCity($s2, ['city' => 'C2']);
        $this->createPhoto($morePhotos, $s2, $c2);
        $this->createPhoto($morePhotos, $s2, $c2);
        $this->createPhoto($morePhotos, $s2, $c2);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('countries', ['id' => $morePhotos]);
        $this->assertDatabaseMissing('countries', ['id' => $fewerPhotos]);

        // The lone photo from fewerPhotos should now point to morePhotos
        $this->assertEquals($morePhotos, $this->getPhotoLocation($lonePhotoId)->country_id);
        $this->assertEquals(4, DB::table('photos')->where('country_id', $morePhotos)->count());
    }

    /** @test */
    public function it_logs_country_merges()
    {
        $keeperId = $this->createCountry(['country' => 'TestChina', 'manual_verify' => 1]);
        $loserId  = $this->createCountry(['country' => 'TestChina', 'manual_verify' => 0]);

        $s = $this->createState($keeperId, ['state' => 'TestBeijing']);
        $c = $this->createCity($s, ['city' => 'TestBeijingCity']);
        $this->createPhoto($keeperId, $s, $c);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('location_merges', [
            'entity_type' => 'country',
            'loser_id' => $loserId,
            'keeper_id' => $keeperId,
        ]);
    }

    /** @test */
    public function it_skips_countries_with_no_duplicates()
    {
        $id1 = $this->createCountry(['country' => 'TestUniqueA']);
        $id2 = $this->createCountry(['country' => 'TestUniqueB']);

        $s1 = $this->createState($id1, ['state' => 'S1']);
        $c1 = $this->createCity($s1, ['city' => 'C1']);
        $this->createPhoto($id1, $s1, $c1);

        $s2 = $this->createState($id2, ['state' => 'S2']);
        $c2 = $this->createCity($s2, ['city' => 'C2']);
        $this->createPhoto($id2, $s2, $c2);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('countries', ['id' => $id1]);
        $this->assertDatabaseHas('countries', ['id' => $id2]);
    }

    // ─────────────────────────────────────────────
    // State Merge Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function it_reassigns_photos_from_loser_state_to_keeper()
    {
        $countryId = $this->createCountry(['country' => 'TestSpain']);

        $keeperState = $this->createState($countryId, ['state' => 'TestMalaga', 'manual_verify' => 1]);
        $loserState  = $this->createState($countryId, ['state' => 'TestMalaga', 'manual_verify' => 0]);

        $keeperCity = $this->createCity($keeperState, ['city' => 'CityA']);
        $loserCity  = $this->createCity($loserState, ['city' => 'CityB']);

        $keeperPhotoId = $this->createPhoto($countryId, $keeperState, $keeperCity);
        $loserPhotoId  = $this->createPhoto($countryId, $loserState, $loserCity);

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('states', ['id' => $loserState]);
        $this->assertDatabaseHas('states', ['id' => $keeperState]);

        // Keeper photo unchanged
        $keeperPhoto = $this->getPhotoLocation($keeperPhotoId);
        $this->assertEquals($keeperState, $keeperPhoto->state_id);
        $this->assertEquals($keeperCity, $keeperPhoto->city_id);

        // Loser photo's state_id reassigned to keeper
        $loserPhoto = $this->getPhotoLocation($loserPhotoId);
        $this->assertEquals($keeperState, $loserPhoto->state_id);

        // Loser's city moved to keeper state
        $this->assertEquals($keeperState, DB::table('cities')->where('id', $loserCity)->value('state_id'));

        $this->assertEquals($photoBefore, $this->photoCount());
    }

    /** @test */
    public function it_does_not_merge_states_from_different_countries()
    {
        $country1 = $this->createCountry(['country' => 'TestCountryA']);
        $country2 = $this->createCountry(['country' => 'TestCountryB']);

        $state1 = $this->createState($country1, ['state' => 'SameName']);
        $state2 = $this->createState($country2, ['state' => 'SameName']);

        $city1 = $this->createCity($state1, ['city' => 'CityA']);
        $city2 = $this->createCity($state2, ['city' => 'CityB']);

        $photo1 = $this->createPhoto($country1, $state1, $city1);
        $photo2 = $this->createPhoto($country2, $state2, $city2);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('states', ['id' => $state1]);
        $this->assertDatabaseHas('states', ['id' => $state2]);

        // Photos untouched
        $this->assertEquals($state1, $this->getPhotoLocation($photo1)->state_id);
        $this->assertEquals($state2, $this->getPhotoLocation($photo2)->state_id);
    }

    // ─────────────────────────────────────────────
    // City Merge Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function it_reassigns_photos_from_loser_city_to_keeper()
    {
        $countryId = $this->createCountry(['country' => 'TestNL']);
        $stateId   = $this->createState($countryId, ['state' => 'TestZuidHolland']);

        $keeperCity = $this->createCity($stateId, ['city' => 'TestRotterdam', 'manual_verify' => 1]);
        $loserCity  = $this->createCity($stateId, ['city' => 'TestRotterdam', 'manual_verify' => 0]);

        $keeperPhoto1 = $this->createPhoto($countryId, $stateId, $keeperCity);
        $keeperPhoto2 = $this->createPhoto($countryId, $stateId, $keeperCity);
        $loserPhotoId = $this->createPhoto($countryId, $stateId, $loserCity);

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('cities', ['id' => $loserCity]);
        $this->assertDatabaseHas('cities', ['id' => $keeperCity]);

        // Keeper photos unchanged
        $this->assertEquals($keeperCity, $this->getPhotoLocation($keeperPhoto1)->city_id);
        $this->assertEquals($keeperCity, $this->getPhotoLocation($keeperPhoto2)->city_id);

        // Loser photo's city_id reassigned to keeper
        $this->assertEquals($keeperCity, $this->getPhotoLocation($loserPhotoId)->city_id);

        $this->assertEquals(3, DB::table('photos')->where('city_id', $keeperCity)->count());
        $this->assertEquals($photoBefore, $this->photoCount());
    }

    /** @test */
    public function it_merges_mass_duplicate_cities()
    {
        $countryId = $this->createCountry(['country' => 'TestAustralia']);
        $stateId   = $this->createState($countryId, ['state' => 'TestVictoria']);

        $cityIds  = [];
        $photoIds = [];
        for ($i = 0; $i < 10; $i++) {
            $cityIds[] = $this->createCity($stateId, ['city' => 'TestFairfield']);
        }

        foreach ($cityIds as $cityId) {
            $photoIds[] = $this->createPhoto($countryId, $stateId, $cityId);
        }

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $remaining = DB::table('cities')
            ->where('city', 'TestFairfield')
            ->where('state_id', $stateId)
            ->get();

        $this->assertCount(1, $remaining);
        $keeperId = $remaining->first()->id;

        // Every photo now points to the single surviving city
        foreach ($photoIds as $photoId) {
            $this->assertEquals($keeperId, $this->getPhotoLocation($photoId)->city_id,
                "Photo #{$photoId} should point to keeper city #{$keeperId}");
        }

        $this->assertEquals(10, DB::table('photos')->where('city_id', $keeperId)->count());
        $this->assertEquals($photoBefore, $this->photoCount());
    }

    /** @test */
    public function it_keeps_city_with_most_photos_as_keeper()
    {
        $countryId = $this->createCountry(['country' => 'TestLand']);
        $stateId   = $this->createState($countryId, ['state' => 'TestRegion']);

        $fewPhotosCity  = $this->createCity($stateId, ['city' => 'TestDupCity']);
        $manyPhotosCity = $this->createCity($stateId, ['city' => 'TestDupCity']);

        $lonePhotoId = $this->createPhoto($countryId, $stateId, $fewPhotosCity);
        $this->createPhoto($countryId, $stateId, $manyPhotosCity);
        $this->createPhoto($countryId, $stateId, $manyPhotosCity);
        $this->createPhoto($countryId, $stateId, $manyPhotosCity);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('cities', ['id' => $manyPhotosCity]);
        $this->assertDatabaseMissing('cities', ['id' => $fewPhotosCity]);

        // The lone photo was reassigned from fewPhotosCity to manyPhotosCity
        $this->assertEquals($manyPhotosCity, $this->getPhotoLocation($lonePhotoId)->city_id);
        $this->assertEquals(4, DB::table('photos')->where('city_id', $manyPhotosCity)->count());
    }

    /** @test */
    public function it_does_not_merge_cities_from_different_states()
    {
        $countryId = $this->createCountry(['country' => 'TestUSA']);
        $state1 = $this->createState($countryId, ['state' => 'TestCalifornia']);
        $state2 = $this->createState($countryId, ['state' => 'TestOregon']);

        $city1 = $this->createCity($state1, ['city' => 'TestPortland']);
        $city2 = $this->createCity($state2, ['city' => 'TestPortland']);

        $photo1 = $this->createPhoto($countryId, $state1, $city1);
        $photo2 = $this->createPhoto($countryId, $state2, $city2);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('cities', ['id' => $city1]);
        $this->assertDatabaseHas('cities', ['id' => $city2]);

        // Photos untouched
        $this->assertEquals($city1, $this->getPhotoLocation($photo1)->city_id);
        $this->assertEquals($city2, $this->getPhotoLocation($photo2)->city_id);
    }

    // ─────────────────────────────────────────────
    // Not Found → Unknown Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function it_renames_single_not_found_city_to_unknown()
    {
        $countryId    = $this->createCountry(['country' => 'TestNotFoundCountry']);
        $stateId      = $this->createState($countryId, ['state' => 'TestNotFoundState']);
        $notFoundCity = $this->createCity($stateId, ['city' => 'not found']);

        $photoId = $this->createPhoto($countryId, $stateId, $notFoundCity);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertEquals('Unknown', DB::table('cities')->where('id', $notFoundCity)->value('city'));
        // Photo still points to the same city row (just renamed)
        $this->assertEquals($notFoundCity, $this->getPhotoLocation($photoId)->city_id);
    }

    /** @test */
    public function it_merges_multiple_not_found_cities_into_single_unknown()
    {
        $countryId = $this->createCountry(['country' => 'TestIreland']);
        $stateId   = $this->createState($countryId, ['state' => 'TestCork']);

        $notFoundIds = [];
        for ($i = 0; $i < 5; $i++) {
            $notFoundIds[] = $this->createCity($stateId, ['city' => 'not found']);
        }

        $photo0 = $this->createPhoto($countryId, $stateId, $notFoundIds[0]);
        $photo2 = $this->createPhoto($countryId, $stateId, $notFoundIds[2]);
        $photo4 = $this->createPhoto($countryId, $stateId, $notFoundIds[4]);

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $unknowns = DB::table('cities')
            ->where('state_id', $stateId)
            ->whereIn('city', ['not found', 'Unknown'])
            ->get();

        $this->assertCount(1, $unknowns);
        $keeperId = $unknowns->first()->id;
        $this->assertEquals('Unknown', $unknowns->first()->city);

        // All 3 photos point to the single surviving Unknown city
        $this->assertEquals($keeperId, $this->getPhotoLocation($photo0)->city_id);
        $this->assertEquals($keeperId, $this->getPhotoLocation($photo2)->city_id);
        $this->assertEquals($keeperId, $this->getPhotoLocation($photo4)->city_id);

        $this->assertEquals(3, DB::table('photos')->where('city_id', $keeperId)->count());
        $this->assertEquals($photoBefore, $this->photoCount());
    }

    /** @test */
    public function it_merges_not_found_into_existing_unknown_city()
    {
        $countryId = $this->createCountry(['country' => 'TestUnknownMerge']);
        $stateId   = $this->createState($countryId, ['state' => 'TestUnknownState']);

        $unknownCity  = $this->createCity($stateId, ['city' => 'Unknown']);
        $notFoundCity = $this->createCity($stateId, ['city' => 'not found']);

        $unknownPhoto  = $this->createPhoto($countryId, $stateId, $unknownCity);
        $notFoundPhoto = $this->createPhoto($countryId, $stateId, $notFoundCity);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseMissing('cities', ['id' => $notFoundCity]);

        // Both photos now point to the existing Unknown city
        $this->assertEquals($unknownCity, $this->getPhotoLocation($unknownPhoto)->city_id);
        $this->assertEquals($unknownCity, $this->getPhotoLocation($notFoundPhoto)->city_id);
        $this->assertEquals(2, DB::table('photos')->where('city_id', $unknownCity)->count());
    }

    // ─────────────────────────────────────────────
    // Orphan Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function it_deletes_orphaned_cities_with_no_photos()
    {
        $countryId = $this->createCountry(['country' => 'TestOrphanCountry']);
        $stateId   = $this->createState($countryId, ['state' => 'TestOrphanState']);

        $usedCity   = $this->createCity($stateId, ['city' => 'TestUsedCity']);
        $orphanCity = $this->createCity($stateId, ['city' => 'TestOrphanCity']);

        $this->createPhoto($countryId, $stateId, $usedCity);

        $this->artisan('olm:locations:cleanup', ['--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('cities', ['id' => $usedCity]);
        $this->assertDatabaseMissing('cities', ['id' => $orphanCity]);
    }

    /** @test */
    public function it_deletes_orphaned_states_with_no_photos_and_no_cities()
    {
        $countryId = $this->createCountry(['country' => 'TestOrphanStateCountry']);

        $usedState   = $this->createState($countryId, ['state' => 'TestUsedState']);
        $orphanState = $this->createState($countryId, ['state' => 'TestOrphanState']);

        $usedCity = $this->createCity($usedState, ['city' => 'TestUsedCity']);
        $this->createPhoto($countryId, $usedState, $usedCity);

        $this->artisan('olm:locations:cleanup', ['--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('states', ['id' => $usedState]);
        $this->assertDatabaseMissing('states', ['id' => $orphanState]);
    }

    /** @test */
    public function it_keeps_states_that_have_child_cities()
    {
        $countryId     = $this->createCountry(['country' => 'TestStateCityKeep']);

        // This state has a city with a photo — kept because it has a child city
        $stateWithCity = $this->createState($countryId, ['state' => 'TestHasCitiesState']);
        $city          = $this->createCity($stateWithCity, ['city' => 'TestSomeCity']);
        $this->createPhoto($countryId, $stateWithCity, $city);

        // This state has no cities and no photos — should be deleted
        $emptyState = $this->createState($countryId, ['state' => 'TestEmptyState']);

        $this->artisan('olm:locations:cleanup', ['--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('states', ['id' => $stateWithCity]);
        $this->assertDatabaseMissing('states', ['id' => $emptyState]);
    }

    /** @test */
    public function it_skips_orphan_deletion_with_flag()
    {
        $countryId = $this->createCountry(['country' => 'TestSkipOrphan']);
        $stateId   = $this->createState($countryId, ['state' => 'TestSkipOrphanState']);

        $usedCity   = $this->createCity($stateId, ['city' => 'TestUsedCity2']);
        $orphanCity = $this->createCity($stateId, ['city' => 'TestOrphanCity2']);

        $this->createPhoto($countryId, $stateId, $usedCity);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('cities', ['id' => $orphanCity]);
    }

    // ─────────────────────────────────────────────
    // Unique Constraint Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function it_adds_unique_constraints_after_cleanup()
    {
        $countryId = $this->createCountry(['country' => 'TestConstraintCountry']);
        $stateId   = $this->createState($countryId, ['state' => 'TestConstraintState']);
        $cityId    = $this->createCity($stateId, ['city' => 'TestConstraintCity']);
        $this->createPhoto($countryId, $stateId, $cityId);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true])
            ->assertSuccessful();

        $this->assertNotEmpty(DB::select("SHOW INDEX FROM states WHERE Key_name = 'uq_state_country'"));
        $this->assertNotEmpty(DB::select("SHOW INDEX FROM cities WHERE Key_name = 'uq_city_state'"));
    }

    /** @test */
    public function it_is_idempotent_with_constraints()
    {
        $countryId = $this->createCountry(['country' => 'TestIdempotent']);
        $stateId   = $this->createState($countryId, ['state' => 'TestIdempotentState']);
        $cityId    = $this->createCity($stateId, ['city' => 'TestIdempotentCity']);
        $this->createPhoto($countryId, $stateId, $cityId);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true])->assertSuccessful();
        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true])->assertSuccessful();
    }

    // ─────────────────────────────────────────────
    // Dry Run Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function dry_run_makes_no_changes()
    {
        $keeperId = $this->createCountry(['country' => 'TestDryRun', 'manual_verify' => 1]);
        $loserId  = $this->createCountry(['country' => 'TestDryRun', 'manual_verify' => 0]);

        $stateId = $this->createState($keeperId, ['state' => 'TestDryRunState']);
        $cityId  = $this->createCity($stateId, ['city' => 'TestDryRunCity']);
        $photoId = $this->createPhoto($keeperId, $stateId, $cityId);

        $countriesBefore = DB::table('countries')->count();

        $this->artisan('olm:locations:cleanup', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('countries', ['id' => $loserId]);
        $this->assertEquals($countriesBefore, DB::table('countries')->count());
        $this->assertEquals($keeperId, $this->getPhotoLocation($photoId)->country_id);

        if (Schema::hasTable('location_merges')) {
            $this->assertEquals(0, DB::table('location_merges')->count());
        }
    }

    // ─────────────────────────────────────────────
    // Idempotency Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function running_twice_is_idempotent()
    {
        $keeperId = $this->createCountry(['country' => 'TestIdempotent2', 'manual_verify' => 1]);
        $loserId  = $this->createCountry(['country' => 'TestIdempotent2', 'manual_verify' => 0]);

        $stateId = $this->createState($keeperId, ['state' => 'TestIdem2State']);
        $cityId  = $this->createCity($stateId, ['city' => 'TestIdem2City']);
        $this->createPhoto($keeperId, $stateId, $cityId);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $countriesAfterFirst = DB::table('countries')->count();
        DB::table('location_merges')->truncate();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertEquals($countriesAfterFirst, DB::table('countries')->count());
        $this->assertEquals(0, DB::table('location_merges')->count());
    }

    // ─────────────────────────────────────────────
    // Integrity / Safety Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function photo_count_never_changes()
    {
        $country1 = $this->createCountry(['country' => 'TestIntegrity', 'manual_verify' => 1]);
        $country2 = $this->createCountry(['country' => 'TestIntegrity', 'manual_verify' => 0]);

        $state1 = $this->createState($country1, ['state' => 'TestIntState1']);
        $state2 = $this->createState($country1, ['state' => 'TestIntState1']);

        $city1 = $this->createCity($state1, ['city' => 'TestIntCity1']);
        $city2 = $this->createCity($state1, ['city' => 'TestIntCity1']);

        $this->createPhoto($country1, $state1, $city1);
        $this->createPhoto($country1, $state1, $city2);
        $this->createPhoto($country2, $state2, $city1);
        $this->createPhoto($country1, $state2, $city2);

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertEquals($photoBefore, $this->photoCount());
    }

    /** @test */
    public function no_broken_foreign_keys_after_cleanup()
    {
        $country1 = $this->createCountry(['country' => 'TestFKCheck', 'manual_verify' => 1]);
        $country2 = $this->createCountry(['country' => 'TestFKCheck', 'manual_verify' => 0]);

        $state = $this->createState($country1, ['state' => 'TestFKState']);
        $city  = $this->createCity($state, ['city' => 'TestFKCity']);

        $this->createPhoto($country1, $state, $city);
        $this->createPhoto($country2, $state, $city);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $broken = DB::select("
            SELECT
                (SELECT COUNT(*) FROM photos p WHERE NOT EXISTS (SELECT 1 FROM countries c WHERE c.id = p.country_id)) as broken_countries,
                (SELECT COUNT(*) FROM photos p WHERE NOT EXISTS (SELECT 1 FROM states s WHERE s.id = p.state_id)) as broken_states,
                (SELECT COUNT(*) FROM photos p WHERE NOT EXISTS (SELECT 1 FROM cities c WHERE c.id = p.city_id)) as broken_cities
        ");

        $this->assertEquals(0, $broken[0]->broken_countries);
        $this->assertEquals(0, $broken[0]->broken_states);
        $this->assertEquals(0, $broken[0]->broken_cities);
    }

    /** @test */
    public function every_photo_points_to_surviving_locations_after_full_cleanup()
    {
        // Create duplicates at every level
        $ireland    = $this->createCountry(['country' => 'TestSurvival', 'manual_verify' => 1]);
        $irelandDup = $this->createCountry(['country' => 'TestSurvival', 'manual_verify' => 0]);

        $cork    = $this->createState($ireland, ['state' => 'TestSurvivalCork', 'manual_verify' => 1]);
        $corkDup = $this->createState($ireland, ['state' => 'TestSurvivalCork', 'manual_verify' => 0]);

        $bandon    = $this->createCity($cork, ['city' => 'TestSurvivalBandon']);
        $bandonDup = $this->createCity($cork, ['city' => 'TestSurvivalBandon']);

        // Photos across every combination of duplicates
        $p1 = $this->createPhoto($ireland, $cork, $bandon);        // all keepers
        $p2 = $this->createPhoto($irelandDup, $cork, $bandon);     // loser country
        $p3 = $this->createPhoto($ireland, $corkDup, $bandon);     // loser state
        $p4 = $this->createPhoto($ireland, $cork, $bandonDup);     // loser city
        $p5 = $this->createPhoto($irelandDup, $corkDup, $bandonDup); // all losers

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertEquals($photoBefore, $this->photoCount());

        // Determine surviving IDs (keepers)
        $survivingCountry = $ireland;
        $survivingState   = $cork;
        // Exactly one city should survive from the duplicate pair
        $survivingCities = DB::table('cities')->whereIn('id', [$bandon, $bandonDup])->pluck('id');
        $this->assertCount(1, $survivingCities, 'Exactly one city should survive from duplicate pair');
        $survivingCity = $survivingCities->first();

        // Every photo must point to surviving locations
        foreach ([$p1, $p2, $p3, $p4, $p5] as $photoId) {
            $loc = $this->getPhotoLocation($photoId);
            $this->assertEquals($survivingCountry, $loc->country_id,
                "Photo #{$photoId} country_id should be {$survivingCountry}");
            $this->assertEquals($survivingState, $loc->state_id,
                "Photo #{$photoId} state_id should be {$survivingState}");
            $this->assertEquals($survivingCity, $loc->city_id,
                "Photo #{$photoId} city_id should be {$survivingCity}");
        }
    }

    // ─────────────────────────────────────────────
    // Merge Log Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function merge_log_records_photos_moved_count()
    {
        $countryId = $this->createCountry(['country' => 'TestMergeLog']);
        $stateId   = $this->createState($countryId, ['state' => 'TestMergeLogState']);

        $keeperCity = $this->createCity($stateId, ['city' => 'TestMergeMe', 'manual_verify' => 1]);
        $loserCity  = $this->createCity($stateId, ['city' => 'TestMergeMe', 'manual_verify' => 0]);

        $this->createPhoto($countryId, $stateId, $keeperCity);
        $this->createPhoto($countryId, $stateId, $loserCity);
        $this->createPhoto($countryId, $stateId, $loserCity);
        $this->createPhoto($countryId, $stateId, $loserCity);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $log = DB::table('location_merges')
            ->where('entity_type', 'city')
            ->where('loser_id', $loserCity)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($keeperCity, $log->keeper_id);
        $this->assertEquals(3, $log->photos_moved);
    }

    /** @test */
    public function merge_log_records_children_moved_for_countries()
    {
        $keeperId = $this->createCountry(['country' => 'TestChildMove', 'manual_verify' => 1]);
        $loserId  = $this->createCountry(['country' => 'TestChildMove', 'manual_verify' => 0]);

        $keeperState = $this->createState($keeperId, ['state' => 'TestKeeperState']);
        $keeperCity  = $this->createCity($keeperState, ['city' => 'TestKeeperCity']);
        $this->createPhoto($keeperId, $keeperState, $keeperCity);

        $loserState1 = $this->createState($loserId, ['state' => 'TestLoserState1']);
        $loserState2 = $this->createState($loserId, ['state' => 'TestLoserState2']);
        $loserCity   = $this->createCity($loserState1, ['country_id' => $loserId, 'city' => 'TestLoserCity']);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $log = DB::table('location_merges')
            ->where('entity_type', 'country')
            ->where('loser_id', $loserId)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(3, $log->children_moved); // 2 states + 1 city
    }

    // ─────────────────────────────────────────────
    // Keeper Selection Policy Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function keeper_policy_manual_verify_wins_over_photo_count()
    {
        $countryId = $this->createCountry(['country' => 'TestPolicyCountry']);
        $stateId   = $this->createState($countryId, ['state' => 'TestPolicyState']);

        $verifiedCity   = $this->createCity($stateId, ['city' => 'TestPolicyCity', 'manual_verify' => 1]);
        $morePhotosCity = $this->createCity($stateId, ['city' => 'TestPolicyCity', 'manual_verify' => 0]);

        $verifiedPhoto = $this->createPhoto($countryId, $stateId, $verifiedCity);
        $loserPhoto1   = $this->createPhoto($countryId, $stateId, $morePhotosCity);
        $loserPhoto2   = $this->createPhoto($countryId, $stateId, $morePhotosCity);
        $loserPhoto3   = $this->createPhoto($countryId, $stateId, $morePhotosCity);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('cities', ['id' => $verifiedCity]);
        $this->assertDatabaseMissing('cities', ['id' => $morePhotosCity]);

        // All photos reassigned to the verified city
        $this->assertEquals($verifiedCity, $this->getPhotoLocation($verifiedPhoto)->city_id);
        $this->assertEquals($verifiedCity, $this->getPhotoLocation($loserPhoto1)->city_id);
        $this->assertEquals($verifiedCity, $this->getPhotoLocation($loserPhoto2)->city_id);
        $this->assertEquals($verifiedCity, $this->getPhotoLocation($loserPhoto3)->city_id);
    }

    /** @test */
    public function keeper_policy_lowest_id_breaks_ties()
    {
        $countryId = $this->createCountry(['country' => 'TestTieBreak']);
        $stateId   = $this->createState($countryId, ['state' => 'TestTieBreakState']);

        $lowId  = $this->createCity($stateId, ['city' => 'TestTieBreak', 'manual_verify' => 1]);
        $highId = $this->createCity($stateId, ['city' => 'TestTieBreak', 'manual_verify' => 1]);

        $lowPhoto  = $this->createPhoto($countryId, $stateId, $lowId);
        $highPhoto = $this->createPhoto($countryId, $stateId, $highId);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('cities', ['id' => $lowId]);
        $this->assertDatabaseMissing('cities', ['id' => $highId]);

        // Both photos now point to the lower ID
        $this->assertEquals($lowId, $this->getPhotoLocation($lowPhoto)->city_id);
        $this->assertEquals($lowId, $this->getPhotoLocation($highPhoto)->city_id);
    }

    // ─────────────────────────────────────────────
    // Diacritic / Collation Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function it_handles_diacritics_without_data_loss()
    {
        // Whether Málaga and Malaga are treated as duplicates depends on the
        // column's collation (utf8mb4_unicode_ci merges them, utf8mb4_bin does not).
        // The command groups by raw column value, so collation does the work.
        // We test the invariants: no data loss, no broken references, and if
        // collation treats them as equal, the merge is clean.
        $countryId = $this->createCountry(['country' => 'TestDiacriticCountry']);

        $accentState = $this->createState($countryId, ['state' => 'Málaga', 'manual_verify' => 1]);
        $plainState  = $this->createState($countryId, ['state' => 'Malaga', 'manual_verify' => 0]);

        $city1 = $this->createCity($accentState, ['city' => 'CityAccent']);
        $city2 = $this->createCity($plainState, ['city' => 'CityPlain']);

        $photo1 = $this->createPhoto($countryId, $accentState, $city1);
        $photo2 = $this->createPhoto($countryId, $plainState, $city2);

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        // Invariant 1: No data loss
        $this->assertEquals($photoBefore, $this->photoCount());

        // Invariant 2: Both photos point to existing states
        $loc1 = $this->getPhotoLocation($photo1);
        $loc2 = $this->getPhotoLocation($photo2);
        $this->assertNotNull(DB::table('states')->where('id', $loc1->state_id)->first(),
            'Photo 1 must point to an existing state');
        $this->assertNotNull(DB::table('states')->where('id', $loc2->state_id)->first(),
            'Photo 2 must point to an existing state');

        // Check what actually happened — if collation merged them, both point to same state
        $surviving = DB::table('states')
            ->where('country_id', $countryId)
            ->whereIn('state', ['Málaga', 'Malaga'])
            ->get();

        if ($surviving->count() === 1) {
            // Collation treated them as equal — verify clean merge
            $survivingId = $surviving->first()->id;
            $this->assertEquals($survivingId, $loc1->state_id);
            $this->assertEquals($survivingId, $loc2->state_id);
        } else {
            // Collation kept them separate — both should still be intact
            $this->assertCount(2, $surviving);
        }
    }

    /** @test */
    public function it_handles_apostrophe_and_special_character_duplicates()
    {
        // Forli'-Cesena type names — depends on how command groups duplicates.
        // If collation treats these as equal, they should merge.
        // If not, they should remain separate. Either way, no data loss.
        $countryId = $this->createCountry(['country' => 'TestApostropheCountry']);

        $state1 = $this->createState($countryId, ['state' => "Forlì-Cesena"]);
        $state2 = $this->createState($countryId, ['state' => "Forli'-Cesena"]);

        $city1 = $this->createCity($state1, ['city' => 'CityA']);
        $city2 = $this->createCity($state2, ['city' => 'CityB']);

        $photo1 = $this->createPhoto($countryId, $state1, $city1);
        $photo2 = $this->createPhoto($countryId, $state2, $city2);

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        // Regardless of whether they merged or stayed separate: no data loss
        $this->assertEquals($photoBefore, $this->photoCount());

        // Both photos should point to existing states
        $loc1 = $this->getPhotoLocation($photo1);
        $loc2 = $this->getPhotoLocation($photo2);
        $this->assertNotNull(DB::table('states')->where('id', $loc1->state_id)->first());
        $this->assertNotNull(DB::table('states')->where('id', $loc2->state_id)->first());
    }

    // ─────────────────────────────────────────────
    // Edge Cases
    // ─────────────────────────────────────────────

    /** @test */
    public function it_handles_three_way_country_duplicate()
    {
        $id1 = $this->createCountry(['country' => 'TestTriple', 'manual_verify' => 1]);
        $id2 = $this->createCountry(['country' => 'TestTriple', 'manual_verify' => 0]);
        $id3 = $this->createCountry(['country' => 'TestTriple', 'manual_verify' => 0]);

        $s1 = $this->createState($id1, ['state' => 'TestTripleState1']);
        $c1 = $this->createCity($s1, ['city' => 'TestTripleCity1']);
        $p1 = $this->createPhoto($id1, $s1, $c1);

        $s2 = $this->createState($id2, ['state' => 'TestTripleState2']);
        $c2 = $this->createCity($s2, ['city' => 'TestTripleCity2']);
        $p2 = $this->createPhoto($id2, $s2, $c2);

        $s3 = $this->createState($id3, ['state' => 'TestTripleState3']);
        $c3 = $this->createCity($s3, ['city' => 'TestTripleCity3']);
        $p3 = $this->createPhoto($id3, $s3, $c3);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('countries', ['id' => $id1]);
        $this->assertDatabaseMissing('countries', ['id' => $id2]);
        $this->assertDatabaseMissing('countries', ['id' => $id3]);

        // All 3 photos point to the keeper country
        $this->assertEquals($id1, $this->getPhotoLocation($p1)->country_id);
        $this->assertEquals($id1, $this->getPhotoLocation($p2)->country_id);
        $this->assertEquals($id1, $this->getPhotoLocation($p3)->country_id);

        $merges = DB::table('location_merges')
            ->where('entity_type', 'country')
            ->where('keeper_id', $id1)
            ->count();
        $this->assertEquals(2, $merges);
    }

    /** @test */
    public function it_handles_no_duplicates_gracefully()
    {
        $countryId = $this->createCountry(['country' => 'TestSolo']);
        $stateId   = $this->createState($countryId, ['state' => 'TestSoloState']);
        $cityId    = $this->createCity($stateId, ['city' => 'TestSoloCity']);
        $photoId   = $this->createPhoto($countryId, $stateId, $cityId);

        $this->artisan('olm:locations:cleanup', ['--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertEquals(0, DB::table('location_merges')->count());

        // Photo completely untouched
        $loc = $this->getPhotoLocation($photoId);
        $this->assertEquals($countryId, $loc->country_id);
        $this->assertEquals($stateId, $loc->state_id);
        $this->assertEquals($cityId, $loc->city_id);
    }

    /** @test */
    public function it_handles_full_scenario_with_all_steps()
    {
        // Country duplicate
        $ireland    = $this->createCountry(['country' => 'TestFullIreland', 'manual_verify' => 1]);
        $irelandDup = $this->createCountry(['country' => 'TestFullIreland', 'manual_verify' => 0]);

        // State duplicate
        $cork    = $this->createState($ireland, ['state' => 'TestFullCork', 'manual_verify' => 1]);
        $corkDup = $this->createState($ireland, ['state' => 'TestFullCork', 'manual_verify' => 0]);

        // City duplicate
        $bandon    = $this->createCity($cork, ['city' => 'TestFullBandon']);
        $bandonDup = $this->createCity($cork, ['city' => 'TestFullBandon']);

        // Not found + orphan
        $notFound   = $this->createCity($cork, ['city' => 'not found']);
        $orphanCity = $this->createCity($cork, ['city' => 'TestFullGhostTown']);

        // Photos across duplicates — track each
        $pBandon    = $this->createPhoto($ireland, $cork, $bandon);
        $pBandonDup = $this->createPhoto($ireland, $cork, $bandonDup);
        $pCorkDup   = $this->createPhoto($ireland, $corkDup, $bandon);
        $pNotFound  = $this->createPhoto($ireland, $cork, $notFound);

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-constraints' => true])
            ->assertSuccessful();

        // No photos lost
        $this->assertEquals($photoBefore, $this->photoCount());

        // Losers gone
        $this->assertDatabaseMissing('countries', ['id' => $irelandDup]);
        $this->assertDatabaseMissing('states', ['id' => $corkDup]);
        $this->assertDatabaseMissing('cities', ['id' => $bandonDup]);
        $this->assertDatabaseMissing('cities', ['id' => $orphanCity]);

        // Not found renamed
        $this->assertEquals('Unknown', DB::table('cities')->where('id', $notFound)->value('city'));

        // All photos point to surviving locations
        $survivingBandons = DB::table('cities')->whereIn('id', [$bandon, $bandonDup])->pluck('id');
        $this->assertCount(1, $survivingBandons, 'Exactly one bandon should survive');
        $survivingBandon = $survivingBandons->first();

        foreach ([$pBandon, $pBandonDup] as $photoId) {
            $this->assertEquals($survivingBandon, $this->getPhotoLocation($photoId)->city_id,
                "Photo #{$photoId} should point to surviving bandon city");
        }

        // corkDup photo moved to keeper state
        $this->assertEquals($cork, $this->getPhotoLocation($pCorkDup)->state_id);

        // not found photo still points to its city (now renamed Unknown)
        $this->assertEquals($notFound, $this->getPhotoLocation($pNotFound)->city_id);
    }

    // ─────────────────────────────────────────────
    // Scale / Skew Test
    // ─────────────────────────────────────────────

    /** @test */
    public function it_handles_scale_with_many_duplicates_across_levels()
    {
        // 3 duplicate countries, each with 5 duplicate states, each with 10 duplicate cities
        // = 3 countries, 15 states, 150 cities, 450 photos
        // Tests that merge logic works across volume and that interactions between
        // country/state/city merge passes don't corrupt references.

        $countryIds = [];
        $allPhotoIds = [];

        for ($c = 0; $c < 3; $c++) {
            $countryIds[] = $this->createCountry([
                'country' => 'TestScaleCountry',
                'manual_verify' => $c === 0 ? 1 : 0,
            ]);
        }

        foreach ($countryIds as $countryId) {
            for ($s = 0; $s < 5; $s++) {
                $stateId = $this->createState($countryId, [
                    'state' => "TestScaleState{$s}",
                    'manual_verify' => ($countryId === $countryIds[0]) ? 1 : 0,
                ]);

                for ($ci = 0; $ci < 10; $ci++) {
                    $cityId = $this->createCity($stateId, [
                        'city' => "TestScaleCity{$ci}",
                    ]);

                    // 3 photos per city
                    for ($p = 0; $p < 3; $p++) {
                        $allPhotoIds[] = $this->createPhoto($countryId, $stateId, $cityId);
                    }
                }
            }
        }

        $photoBefore = $this->photoCount();
        $totalCreated = count($allPhotoIds);
        $this->assertGreaterThanOrEqual(450, $totalCreated, 'Should have created at least 450 photos');

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        // Invariant 1: No photos lost
        $this->assertEquals($photoBefore, $this->photoCount());

        // Invariant 2: No broken foreign keys
        $broken = DB::select("
            SELECT
                (SELECT COUNT(*) FROM photos p WHERE p.id > ? AND NOT EXISTS (SELECT 1 FROM countries c WHERE c.id = p.country_id)) as broken_countries,
                (SELECT COUNT(*) FROM photos p WHERE p.id > ? AND NOT EXISTS (SELECT 1 FROM states s WHERE s.id = p.state_id)) as broken_states,
                (SELECT COUNT(*) FROM photos p WHERE p.id > ? AND NOT EXISTS (SELECT 1 FROM cities c WHERE c.id = p.city_id)) as broken_cities
        ", [$this->maxPhotoId, $this->maxPhotoId, $this->maxPhotoId]);

        $this->assertEquals(0, $broken[0]->broken_countries, 'No broken country references');
        $this->assertEquals(0, $broken[0]->broken_states, 'No broken state references');
        $this->assertEquals(0, $broken[0]->broken_cities, 'No broken city references');

        // Invariant 3: Countries merged to exactly 1
        $survivingCountries = DB::table('countries')
            ->where('country', 'TestScaleCountry')
            ->count();
        $this->assertEquals(1, $survivingCountries, 'Duplicate countries should merge to one');

        // Invariant 4: Each state name exists once per surviving country
        $survivingCountryId = DB::table('countries')
            ->where('country', 'TestScaleCountry')
            ->value('id');

        for ($s = 0; $s < 5; $s++) {
            $stateCount = DB::table('states')
                ->where('country_id', $survivingCountryId)
                ->where('state', "TestScaleState{$s}")
                ->count();
            $this->assertEquals(1, $stateCount, "TestScaleState{$s} should exist exactly once");
        }

        // Invariant 5: Every photo we created still exists and points to valid locations
        foreach ($allPhotoIds as $photoId) {
            $loc = $this->getPhotoLocation($photoId);
            $this->assertNotNull($loc, "Photo #{$photoId} should still exist");
            $this->assertEquals($survivingCountryId, $loc->country_id,
                "Photo #{$photoId} should point to the surviving country");
        }
    }

    // ─────────────────────────────────────────────
    // Tier Consistency Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function it_repairs_photo_country_id_mismatched_with_state()
    {
        // Create a valid location hierarchy
        $countryA = $this->createCountry(['country' => 'TestTierCountryA']);
        $countryB = $this->createCountry(['country' => 'TestTierCountryB']);

        $stateA = $this->createState($countryA, ['state' => 'TestTierStateA']);
        $cityA  = $this->createCity($stateA, ['city' => 'TestTierCityA']);

        // Create a photo with a mismatched country_id (photo says B, but state belongs to A)
        $photoId = $this->createPhoto($countryB, $stateA, $cityA);

        // Verify the mismatch exists before cleanup
        $loc = $this->getPhotoLocation($photoId);
        $this->assertEquals($countryB, $loc->country_id);
        $this->assertEquals($stateA, $loc->state_id);

        $stateCountry = DB::table('states')->where('id', $stateA)->value('country_id');
        $this->assertEquals($countryA, $stateCountry);
        $this->assertNotEquals($loc->country_id, $stateCountry, 'Pre-condition: mismatch should exist');

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        // After cleanup, photo.country_id should match state's country_id
        $loc = $this->getPhotoLocation($photoId);
        $this->assertEquals($countryA, $loc->country_id,
            'Photo country_id should be repaired to match state\'s country_id');
        $this->assertEquals($stateA, $loc->state_id);
    }

    /** @test */
    public function it_repairs_photo_state_id_mismatched_with_city()
    {
        $countryId = $this->createCountry(['country' => 'TestTierMismatchCountry']);

        $stateA = $this->createState($countryId, ['state' => 'TestTierStateA']);
        $stateB = $this->createState($countryId, ['state' => 'TestTierStateB']);

        // City belongs to stateA
        $city = $this->createCity($stateA, ['city' => 'TestTierCity']);

        // Photo says stateB, but city belongs to stateA
        $photoId = $this->createPhoto($countryId, $stateB, $city);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        // After cleanup, photo.state_id should match city's state_id
        $loc = $this->getPhotoLocation($photoId);
        $this->assertEquals($stateA, $loc->state_id,
            'Photo state_id should be repaired to match city\'s state_id');
    }

    // ─────────────────────────────────────────────
    // Whitespace Normalization Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function it_normalizes_whitespace_and_merges_resulting_duplicates()
    {
        $countryId = $this->createCountry(['country' => 'TestWhitespaceCountry']);

        // "TestCork" vs "TestCork " (trailing space) — after TRIM, these are duplicates
        $clean  = $this->createState($countryId, ['state' => 'TestCork', 'manual_verify' => 1]);
        $padded = $this->createState($countryId, ['state' => 'TestCork ']);

        $city1 = $this->createCity($clean, ['city' => 'CityClean']);
        $city2 = $this->createCity($padded, ['city' => 'CityPadded']);

        $photo1 = $this->createPhoto($countryId, $clean, $city1);
        $photo2 = $this->createPhoto($countryId, $padded, $city2);

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertEquals($photoBefore, $this->photoCount());

        // After normalization + merge, both should point to the same state
        $loc1 = $this->getPhotoLocation($photo1);
        $loc2 = $this->getPhotoLocation($photo2);
        $this->assertEquals($loc1->state_id, $loc2->state_id,
            'Both photos should point to the same state after whitespace normalization');

        // The surviving state's name should be trimmed
        $stateName = DB::table('states')->where('id', $loc1->state_id)->value('state');
        $this->assertEquals('TestCork', $stateName, 'Surviving state name should be trimmed');
    }

    // ─────────────────────────────────────────────
    // Shortcode Merge Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function it_merges_countries_with_different_names_but_same_shortcode()
    {
        $id1 = $this->createCountry([
            'country' => 'TestUnitedStates',
            'shortcode' => 'zus',
            'manual_verify' => 1,
        ]);
        $id2 = $this->createCountry([
            'country' => 'TestUSA',
            'shortcode' => 'zus',
            'manual_verify' => 0,
        ]);

        $s1 = $this->createState($id1, ['state' => 'TestCalifornia']);
        $c1 = $this->createCity($s1, ['city' => 'TestLA']);
        $p1 = $this->createPhoto($id1, $s1, $c1);

        $s2 = $this->createState($id2, ['state' => 'TestTexas']);
        $c2 = $this->createCity($s2, ['city' => 'TestDallas']);
        $p2 = $this->createPhoto($id2, $s2, $c2);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        // Keeper survives, loser gone
        $this->assertDatabaseHas('countries', ['id' => $id1]);
        $this->assertDatabaseMissing('countries', ['id' => $id2]);

        // Both photos point to the keeper
        $this->assertEquals($id1, $this->getPhotoLocation($p1)->country_id);
        $this->assertEquals($id1, $this->getPhotoLocation($p2)->country_id);

        // Merge log records it with shortcode reason
        $log = DB::table('location_merges')
            ->where('entity_type', 'country')
            ->where('loser_id', $id2)
            ->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('shortcode', $log->reason);
    }

    // ─────────────────────────────────────────────
    // Not-Found Merge Log Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function not_found_merge_log_records_real_loser_ids()
    {
        $countryId = $this->createCountry(['country' => 'TestNotFoundLog']);
        $stateId   = $this->createState($countryId, ['state' => 'TestNotFoundLogState']);

        $nf1 = $this->createCity($stateId, ['city' => 'not found']);
        $nf2 = $this->createCity($stateId, ['city' => 'not found']);
        $nf3 = $this->createCity($stateId, ['city' => 'not found']);

        $this->createPhoto($countryId, $stateId, $nf1);
        $this->createPhoto($countryId, $stateId, $nf2);
        $this->createPhoto($countryId, $stateId, $nf3);

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        // Every merge log entry should have a real loser_id (never 0)
        $logs = DB::table('location_merges')
            ->where('reason', 'LIKE', '%not found%')
            ->get();

        foreach ($logs as $log) {
            $this->assertGreaterThan(0, $log->loser_id,
                "Merge log should never have loser_id=0, got: " . json_encode($log));
        }
    }

    // ─────────────────────────────────────────────
    // Child Merge Tests
    // ─────────────────────────────────────────────

    /** @test */
    public function it_merges_colliding_child_states_during_country_merge()
    {
        // Two countries with same shortcode but different names
        // Both have a state called "District of Columbia"
        $keeper = $this->createCountry(['country' => 'TestUSFull', 'shortcode' => 'zz', 'manual_verify' => 1]);
        $loser  = $this->createCountry(['country' => 'TestUSShort', 'shortcode' => 'zz', 'manual_verify' => 0]);

        $keeperDC = $this->createState($keeper, ['state' => 'TestDC']);
        $loserDC  = $this->createState($loser, ['state' => 'TestDC']);

        // Non-colliding state in loser
        $loserTexas = $this->createState($loser, ['state' => 'TestTexas']);

        $keeperCity = $this->createCity($keeperDC, ['city' => 'TestWashington']);
        $loserCity  = $this->createCity($loserDC, ['city' => 'TestWashington2']);
        $texasCity  = $this->createCity($loserTexas, ['city' => 'TestDallas']);

        $pKeeper = $this->createPhoto($keeper, $keeperDC, $keeperCity);
        $pLoserDC = $this->createPhoto($loser, $loserDC, $loserCity);
        $pTexas   = $this->createPhoto($loser, $loserTexas, $texasCity);

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        // No photos lost
        $this->assertEquals($photoBefore, $this->photoCount());

        // Loser country gone
        $this->assertDatabaseMissing('countries', ['id' => $loser]);

        // Loser DC state merged into keeper DC state
        $this->assertDatabaseMissing('states', ['id' => $loserDC]);
        $this->assertDatabaseHas('states', ['id' => $keeperDC]);

        // Non-colliding state moved to keeper country
        $this->assertEquals($keeper, DB::table('states')->where('id', $loserTexas)->value('country_id'));

        // All photos point to keeper country
        $this->assertEquals($keeper, $this->getPhotoLocation($pKeeper)->country_id);
        $this->assertEquals($keeper, $this->getPhotoLocation($pLoserDC)->country_id);
        $this->assertEquals($keeper, $this->getPhotoLocation($pTexas)->country_id);

        // DC photos all point to keeper state
        $this->assertEquals($keeperDC, $this->getPhotoLocation($pKeeper)->state_id);
        $this->assertEquals($keeperDC, $this->getPhotoLocation($pLoserDC)->state_id);
    }

    /** @test */
    public function it_merges_colliding_child_cities_during_state_merge()
    {
        $countryId = $this->createCountry(['country' => 'TestCityCollisionCountry']);

        $keeperState = $this->createState($countryId, ['state' => 'TestCityCollisionState', 'manual_verify' => 1]);
        $loserState  = $this->createState($countryId, ['state' => 'TestCityCollisionState', 'manual_verify' => 0]);

        // Both states have "TestBandon"
        $keeperBandon = $this->createCity($keeperState, ['city' => 'TestBandon']);
        $loserBandon  = $this->createCity($loserState, ['city' => 'TestBandon']);

        // Non-colliding city in loser
        $loserKinsale = $this->createCity($loserState, ['city' => 'TestKinsale']);

        $p1 = $this->createPhoto($countryId, $keeperState, $keeperBandon);
        $p2 = $this->createPhoto($countryId, $loserState, $loserBandon);
        $p3 = $this->createPhoto($countryId, $loserState, $loserKinsale);

        $photoBefore = $this->photoCount();

        $this->artisan('olm:locations:cleanup', ['--skip-orphans' => true, '--skip-constraints' => true])
            ->assertSuccessful();

        $this->assertEquals($photoBefore, $this->photoCount());

        // Loser state gone, loser bandon gone
        $this->assertDatabaseMissing('states', ['id' => $loserState]);
        $this->assertDatabaseMissing('cities', ['id' => $loserBandon]);

        // Both bandon photos point to keeper city
        $this->assertEquals($keeperBandon, $this->getPhotoLocation($p1)->city_id);
        $this->assertEquals($keeperBandon, $this->getPhotoLocation($p2)->city_id);

        // Kinsale moved to keeper state
        $this->assertEquals($keeperState, DB::table('cities')->where('id', $loserKinsale)->value('state_id'));
        $this->assertEquals($keeperState, $this->getPhotoLocation($p3)->state_id);
    }
}
