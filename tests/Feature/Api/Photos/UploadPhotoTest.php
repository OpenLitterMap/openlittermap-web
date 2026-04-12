<?php

namespace Tests\Feature\Api\Photos;

use App\Actions\Locations\ReverseGeocodeLocationAction;
use App\Enums\XpScore;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use App\Services\Redis\RedisKeys;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\Doubles\Actions\Locations\FakeReverseGeocodingAction;
use Tests\TestCase;

class UploadPhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->swap(
            ReverseGeocodeLocationAction::class,
            (new FakeReverseGeocodingAction())->withAddress([
                'house_number' => '10735',
                'road' => 'Carlisle Pike',
                'city' => 'Latimore Township',
                'county' => 'Adams County',
                'state' => 'Pennsylvania',
                'postcode' => '17324',
                'country' => 'United States of America',
                'country_code' => 'us',
                'suburb' => 'unknown',
            ])
        );
    }

    public function test_web_upload_returns_photo_id()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create(['picked_up' => true]);

        // Web route uses auth:api,web — web guard works
        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success', 'photo_id', 'lat', 'lon',
            'city', 'state', 'country', 'display_name',
            'xp_awarded', 'user_xp_total',
        ]);

        $photoId = $response->json('photo_id');
        $this->assertDatabaseHas('photos', [
            'id' => $photoId,
            'user_id' => $user->id,
            'platform' => 'web',
        ]);

        $this->assertEquals(XpScore::Upload->xp(), $response->json('xp_awarded'));
        $this->assertEquals(XpScore::Upload->xp(), $user->fresh()->xp);
        $this->assertNotNull($response->json('city'));
        $this->assertNotNull($response->json('state'));
        $this->assertNotNull($response->json('country'));
    }

    public function test_upload_attaches_photo_to_active_team()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $teamType = TeamType::create(['team' => 'community', 'price' => 0]);
        $team = Team::factory()->create(['type_id' => $teamType->id]);

        $user = User::factory()->create([
            'active_team' => $team->id,
            'picked_up' => true,
        ]);
        $team->users()->attach($user->id);

        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
        ]);

        $response->assertOk();

        $photo = Photo::find($response->json('photo_id'));
        $this->assertEquals($team->id, $photo->team_id);
    }

    public function test_upload_without_active_team_has_null_team_id()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create(['active_team' => null, 'picked_up' => true]);

        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
        ]);

        $response->assertOk();

        $photo = Photo::find($response->json('photo_id'));
        $this->assertNull($photo->team_id);
    }

    public function test_web_upload_rejects_duplicate_photo()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create(['picked_up' => true]);
        $file = new UploadedFile(
            storage_path('framework/testing/img_with_exif.JPG'),
            'photo.jpg',
            'image/jpeg',
            null,
            true
        );

        // First upload succeeds
        $response = $this->actingAs($user)->postJson('/api/v3/upload', ['photo' => $file]);
        $response->assertOk();

        // Second upload of same photo (same EXIF datetime) is rejected
        $response = $this->actingAs($user)->postJson('/api/v3/upload', ['photo' => $file]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('photo');
    }

    // ---------------------------------------------------------------
    // Mobile upload (explicit coordinates)
    // ---------------------------------------------------------------

    public function test_mobile_upload_with_explicit_coordinates()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create(['picked_up' => true]);

        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
            'lat' => 40.053,
            'lon' => -77.154,
            'date' => '2026-03-01 12:00:00',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'photo_id']);

        $photo = Photo::find($response->json('photo_id'));
        $this->assertEquals(40.053, $photo->lat);
        $this->assertEquals(-77.154, $photo->lon);
        $this->assertEquals('mobile', $photo->platform);
    }

    public function test_mobile_upload_with_unix_timestamp()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create(['picked_up' => true]);
        $timestamp = now()->timestamp;

        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
            'lat' => 51.8754,
            'lon' => -8.5138,
            'date' => $timestamp,
        ]);

        $response->assertOk();

        $photo = Photo::find($response->json('photo_id'));
        $this->assertEquals($timestamp, $photo->datetime->timestamp);
    }

    public function test_mobile_upload_with_picked_up()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create(['picked_up' => false]);

        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
            'lat' => 40.053,
            'lon' => -77.154,
            'date' => '2026-03-01 14:00:00',
            'picked_up' => true,
        ]);

        $response->assertOk();

        $photo = Photo::find($response->json('photo_id'));
        // picked_up=true → remaining=false
        $this->assertFalse((bool) $photo->remaining);
    }

    public function test_mobile_upload_rejects_zero_coordinates()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
            'lat' => 0,
            'lon' => 0,
            'date' => '2026-03-01 12:00:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('lat');
    }

    public function test_mobile_upload_rejects_duplicate_by_explicit_date()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create();
        $date = '2026-03-01 15:30:00';

        // First upload
        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
            'lat' => 40.053,
            'lon' => -77.154,
            'date' => $date,
        ]);
        $response->assertOk();

        // Second upload with same date
        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
            'lat' => 40.053,
            'lon' => -77.154,
            'date' => $date,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('photo');
    }

    // ---------------------------------------------------------------
    // User photos per_page parameter
    // ---------------------------------------------------------------

    public function test_user_photos_per_page_parameter()
    {
        $user = User::factory()->create();

        // Create 15 photos
        Photo::factory()->count(15)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/v3/user/photos?per_page=50');

        $response->assertOk();
        $this->assertEquals(50, $response->json('pagination.per_page'));
        $this->assertCount(15, $response->json('photos')); // All 15 fit in one page
    }

    public function test_user_photos_per_page_capped_at_100()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v3/user/photos?per_page=500');

        $response->assertOk();
        $this->assertEquals(100, $response->json('pagination.per_page'));
    }

    public function test_user_photos_default_per_page_is_8()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v3/user/photos');

        $response->assertOk();
        $this->assertEquals(8, $response->json('pagination.per_page'));
    }

    // ---------------------------------------------------------------
    // Upload XP and enriched response
    // ---------------------------------------------------------------

    public function test_upload_awards_correct_xp()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create(['xp' => 100, 'picked_up' => true]);

        $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
        ])->assertOk();

        $this->assertEquals(100 + XpScore::Upload->xp(), $user->fresh()->xp);
    }

    public function test_upload_writes_metrics_for_leaderboard()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create(['xp' => 50, 'picked_up' => true]);

        $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
        ])->assertOk();

        // Upload writes to metrics table so user appears on time-filtered leaderboards
        $this->assertDatabaseHas('metrics', [
            'timescale' => 0, // all-time
            'location_type' => 0,
            'location_id' => 0,
            'user_id' => $user->id,
            'xp' => XpScore::Upload->xp(),
            'uploads' => 1,
        ]);
    }

    public function test_upload_returns_location_data()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create(['picked_up' => true]);

        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
        ]);

        $response->assertOk();
        $this->assertIsFloat($response->json('lat'));
        $this->assertIsFloat($response->json('lon'));
        $this->assertIsString($response->json('city'));
        $this->assertIsString($response->json('state'));
        $this->assertIsString($response->json('country'));
    }

    public function test_duplicate_upload_returns_typed_error()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create(['picked_up' => true]);
        $file = new UploadedFile(
            storage_path('framework/testing/img_with_exif.JPG'),
            'photo.jpg',
            'image/jpeg',
            null,
            true
        );

        // First upload succeeds
        $this->actingAs($user)->postJson('/api/v3/upload', ['photo' => $file])->assertOk();

        // Second upload returns typed error
        $response = $this->actingAs($user)->postJson('/api/v3/upload', ['photo' => $file]);
        $response->assertStatus(422);
        $response->assertJson(['error' => 'duplicate']);
    }

    // ---------------------------------------------------------------
    // Upload metrics gate: school vs private-by-choice
    // ---------------------------------------------------------------

    public function test_school_team_photo_defers_upload_xp()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $schoolType = TeamType::firstOrCreate(
            ['team' => 'school'],
            ['team' => 'school']
        );

        $team = Team::factory()->create([
            'type_id' => $schoolType->id,
            'type_name' => 'school',
        ]);

        $user = User::factory()->create([
            'active_team' => $team->id,
            'picked_up' => true,
        ]);
        $team->users()->attach($user->id);

        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
        ]);

        $response->assertOk();

        // School photos defer XP — no immediate award
        $this->assertEquals(0, $response->json('xp_awarded'));
        $this->assertEquals(0, $user->fresh()->xp);

        // Photo should be private
        $photo = Photo::find($response->json('photo_id'));
        $this->assertFalse((bool) $photo->is_public);
    }

    // ---------------------------------------------------------------
    // public_photos user default
    // ---------------------------------------------------------------

    public function test_upload_uses_user_public_photos_default()
    {
        Storage::fake('s3');
        Storage::fake('bbox');
        $user = User::factory()->create(['picked_up' => true, 'public_photos' => false]);
        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(storage_path('framework/testing/img_with_exif.JPG'), 'photo.jpg', 'image/jpeg', null, true),
        ]);
        $response->assertOk();
        $photo = Photo::find($response->json('photo_id'));
        $this->assertFalse((bool) $photo->is_public);
    }

    public function test_upload_request_is_public_overrides_user_default()
    {
        Storage::fake('s3');
        Storage::fake('bbox');
        $user = User::factory()->create(['picked_up' => true, 'public_photos' => false]);
        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(storage_path('framework/testing/img_with_exif.JPG'), 'photo.jpg', 'image/jpeg', null, true),
            'is_public' => true,
        ]);
        $response->assertOk();
        $photo = Photo::find($response->json('photo_id'));
        $this->assertTrue((bool) $photo->is_public);
    }

    public function test_school_team_overrides_user_public_photos_true()
    {
        Storage::fake('s3');
        Storage::fake('bbox');
        $schoolType = TeamType::firstOrCreate(['team' => 'school'], ['team' => 'school']);
        $team = Team::factory()->create(['type_id' => $schoolType->id, 'type_name' => 'school']);
        $user = User::factory()->create(['active_team' => $team->id, 'picked_up' => true, 'public_photos' => true]);
        $team->users()->attach($user->id);
        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(storage_path('framework/testing/img_with_exif.JPG'), 'photo.jpg', 'image/jpeg', null, true),
        ]);
        $response->assertOk();
        $photo = Photo::find($response->json('photo_id'));
        $this->assertFalse((bool) $photo->is_public);
        $this->assertEquals(0, $response->json('xp_awarded'));
    }

    public function test_user_leaving_school_team_uses_own_default()
    {
        Storage::fake('s3');
        Storage::fake('bbox');
        $user = User::factory()->create(['active_team' => null, 'picked_up' => true, 'public_photos' => false]);
        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(storage_path('framework/testing/img_with_exif.JPG'), 'photo.jpg', 'image/jpeg', null, true),
        ]);
        $response->assertOk();
        $photo = Photo::find($response->json('photo_id'));
        $this->assertFalse((bool) $photo->is_public);
        $this->assertEquals(\App\Enums\XpScore::Upload->xp(), $response->json('xp_awarded'));
    }

    public function test_upload_succeeds_when_geocoding_returns_no_state_or_city()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        // Override with an address that has no state or city keys
        $this->swap(
            ReverseGeocodeLocationAction::class,
            (new FakeReverseGeocodingAction())->withAddress([
                'country' => 'Maldives',
                'country_code' => 'mv',
            ])
        );

        $user = User::factory()->create(['picked_up' => true]);

        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
        ]);

        $response->assertOk();

        $photo = Photo::find($response->json('photo_id'));
        $this->assertNotNull($photo);
        $this->assertNotNull($photo->country_id);
        $this->assertNull($photo->state_id);
        $this->assertNull($photo->city_id);
    }

    public function test_non_school_team_photo_gets_immediate_upload_xp()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $communityType = TeamType::firstOrCreate(
            ['team' => 'community'],
            ['team' => 'community', 'price' => 0]
        );

        $team = Team::factory()->create([
            'type_id' => $communityType->id,
            'type_name' => 'community',
        ]);

        $user = User::factory()->create([
            'active_team' => $team->id,
            'picked_up' => true,
        ]);
        $team->users()->attach($user->id);

        $response = $this->actingAs($user)->postJson('/api/v3/upload', [
            'photo' => new UploadedFile(
                storage_path('framework/testing/img_with_exif.JPG'),
                'photo.jpg',
                'image/jpeg',
                null,
                true
            ),
        ]);

        $response->assertOk();

        // Community team photos get immediate XP
        $this->assertEquals(XpScore::Upload->xp(), $response->json('xp_awarded'));
        $this->assertEquals(XpScore::Upload->xp(), $user->fresh()->xp);

        // Photo should be public
        $photo = Photo::find($response->json('photo_id'));
        $this->assertTrue((bool) $photo->is_public);
    }
}
