<?php

namespace Tests\Feature\Api\Photos;

use App\Actions\Locations\ReverseGeocodeLocationAction;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Illuminate\Http\UploadedFile;
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
        $response->assertJsonStructure(['success', 'photo_id']);

        $photoId = $response->json('photo_id');
        $this->assertDatabaseHas('photos', [
            'id' => $photoId,
            'user_id' => $user->id,
            'platform' => 'web',
        ]);
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
}
