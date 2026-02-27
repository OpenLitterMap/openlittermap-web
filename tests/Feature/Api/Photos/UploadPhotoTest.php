<?php

namespace Tests\Feature\Api\Photos;

use App\Actions\Photos\DeletePhotoAction;
use App\Events\ImageUploaded;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class UploadPhotoTest extends TestCase
{
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPhotoUploads();

        $country = Country::create(['country' => 'error_country', 'shortcode' => 'error']);
        $state = State::create(['state' => 'error_state', 'country_id' => $country->id]);
        City::create(['city' => 'error_city', 'country_id' => $country->id, 'state_id' => $state->id]);
    }

    public function test_an_api_user_can_upload_a_photo()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        Event::fake([ImageUploaded::class]);

        $user = User::factory()->create([
            'verified' => true,
            'active_team' => Team::factory(),
            'items_remaining' => 0
        ]);

        $this->actingAs($user, 'api');

        $imageAttributes = $this->getImageAndAttributes();
        $location = $this->locationService->createOrGetLocationFromAddress($imageAttributes['address']);

        Carbon::setTestNow(now());

        $response = $this->post('/api/photos/submit',
            $this->getApiImageAttributes($imageAttributes)
        );

        $response->assertOk()->assertJson(['success' => true]);

        // Response includes the photo ID
        $response->assertJsonStructure(['success', 'photo_id']);
        $this->assertIsInt($response->json('photo_id'));

        // Image is uploaded
        Storage::disk('s3')->assertExists($imageAttributes['filepath']);
        Storage::disk('bbox')->assertExists($imageAttributes['filepath']);

        // Bounding Box image has the right dimensions
        $image = Image::make(Storage::disk('bbox')->get($imageAttributes['filepath']));
        $this->assertEquals(500, $image->width());
        $this->assertEquals(500, $image->height());

        $user->refresh();

        // The Photo is persisted correctly
        $this->assertCount(1, $user->photos);
        $photo = $user->photos->last();

        $this->assertEquals($imageAttributes['fullFilePath'], $photo->filename);
        $this->assertEquals(
            $imageAttributes['dateTime']->format('Y-m-d H:i:s'),
            $photo->datetime->format('Y-m-d H:i:s')
        );
        $this->assertEquals($imageAttributes['latitude'], $photo->lat);
        $this->assertEquals($imageAttributes['longitude'], $photo->lon);
        $this->assertEquals($imageAttributes['displayName'], $photo->display_name);
        $this->assertEquals($imageAttributes['address'], $photo->address_array);
        $this->assertEquals('test model', $photo->model);
        $this->assertEquals(0, $photo->remaining);
        $this->assertEquals($location['country_id'], $photo->country_id);
        $this->assertEquals($location['state_id'], $photo->state_id);
        $this->assertEquals($location['city_id'], $photo->city_id);
        $this->assertEquals('mobile', $photo->platform);
        $this->assertEquals('dr15u73vccgyzbs9w4um', $photo->geohash);
        $this->assertEquals($user->active_team, $photo->team_id);
        $this->assertEquals($imageAttributes['fullBBoxFilePath'], $photo->five_hundred_square_filepath);

        Event::assertDispatched(
            ImageUploaded::class,
            function (ImageUploaded $e) use ($user, $imageAttributes, $location) {
                return $e->city === $imageAttributes['address']['city'] &&
                    $e->state === $imageAttributes['address']['state'] &&
                    $e->country === $imageAttributes['address']['country'] &&
                    $e->countryCode === $imageAttributes['address']['country_code'] &&
                    $e->userId === $user->id &&
                    $e->countryId === $location['country_id'] &&
                    $e->stateId === $location['state_id'] &&
                    $e->cityId === $location['city_id'];
            }
        );

    }

    public function test_an_api_user_can_upload_a_photo_on_a_real_storage()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $imageAttributes = $this->getImageAndAttributes();

        $response = $this->post('/api/photos/submit',
            $this->getApiImageAttributes($imageAttributes)
        );

        $response->assertOk()->assertJson(['success' => true]);

        // Image is uploaded
        Storage::disk('s3')->assertExists($imageAttributes['filepath']);
        $this->assertTrue(
            Storage::disk('s3')->exists($imageAttributes['filepath']),
            "File does not exist on the s3 disk: {$imageAttributes['filepath']}"
        );

        $content = Storage::disk('s3')->get($imageAttributes['filepath']);
        $this->assertNotEmpty($content, 'Uploaded file content is empty');

        Storage::disk('bbox')->assertExists($imageAttributes['filepath']);

        // Bounding Box image has the right dimensions
        $image = Image::make(Storage::disk('bbox')->get($imageAttributes['filepath']));
        $this->assertEquals(500, $image->width());
        $this->assertEquals(500, $image->height());

        $user->refresh();

        // The Photo is persisted correctly
        $this->assertCount(1, $user->photos);
        $photo = $user->photos->last();

        $this->assertEquals($imageAttributes['fullFilePath'], $photo->filename);
        $this->assertEquals($imageAttributes['fullBBoxFilePath'], $photo->five_hundred_square_filepath);

        // Cleanup
        $deletePhotoAction = app(DeletePhotoAction::class);
        $deletePhotoAction->run($photo);
    }

    public function test_photo_is_associated_with_user_after_upload(): void
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create();
        $this->assertCount(0, $user->photos);

        $this->actingAs($user, 'api')->post('/api/photos/submit',
            $this->getApiImageAttributes($this->getImageAndAttributes())
        );

        // v5: Upload creates the photo association; XP/metrics update on tag verification
        $user->refresh();

        $this->assertCount(1, $user->photos);
    }

    public function test_upload_does_not_set_xp_until_tags_are_verified()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create();
        $imageAttributes = $this->getImageAndAttributes();
        $countryId = Country::factory()->create([
            'shortcode' => $imageAttributes['address']['country_code'],
            'country' => $imageAttributes['address']['country'],
        ])->id;
        $stateId = State::factory()->create(['state' => $imageAttributes['address']['state'], 'country_id' => $countryId])->id;
        $cityId = City::factory()->create(['city' => $imageAttributes['address']['city'], 'country_id' => $countryId, 'state_id' => $stateId])->id;

        Redis::del("xp.users");
        Redis::del("xp.country.$countryId");

        $this->actingAs($user, 'api')->post('/api/photos/submit',
            $this->getApiImageAttributes($imageAttributes)
        );

        // v5: XP is not set on upload — it's set when tags are verified
        $this->assertFalse(Redis::zscore("xp.users", $user->id));
        $this->assertFalse(Redis::zscore("xp.country.$countryId", $user->id));
    }

    public function test_unauthenticated_users_cannot_upload_photos()
    {
        $imageAttributes = $this->getImageAndAttributes();

        $response = $this->postJson('/api/photos/submit',
            $this->getApiImageAttributes($imageAttributes)
        );

        $response->assertUnauthorized();
    }


    public static function validationDataProvider(): array
    {
        return [
            [
                'fields' => [],
                'errors' => ['photo', 'lat', 'lon', 'date'],
            ],
            [
                'fields' => ['photo' => UploadedFile::fake()->image('some.pdf'), 'lat' => 5, 'lon' => 5, 'date' => now()->toDateTimeString()],
                'errors' => ['photo']
            ],
            [
                'fields' => ['photo' => 'validImage', 'lat' => 'asdf', 'lon' => 'asdf', 'date' => now()->toDateTimeString()],
                'errors' => ['lat', 'lon']
            ],
        ];
    }

    /**
     * @dataProvider validationDataProvider
     */
    public function test_the_uploaded_photo_is_validated($fields, $errors)
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        if (($fields['photo'] ?? null) == 'validImage') {
            $fields['photo'] = $this->getApiImageAttributes($this->getImageAndAttributes());
        }

        $this->postJson('/api/photos/submit', $fields)
            ->assertStatus(422)
            ->assertJsonValidationErrors($errors);
    }

    public function test_uploaded_photo_can_have_different_mime_types()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        Carbon::setTestNow(now());

        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        // PNG
        $imageAttributes = $this->getImageAndAttributes('png');
        $this->post('/api/photos/submit', $this->getApiImageAttributes($imageAttributes))->assertOk();

        // JPEG
        $imageAttributes = $this->getImageAndAttributes('jpeg');
        $this->post('/api/photos/submit', $this->getApiImageAttributes($imageAttributes))->assertOk();
    }

    public function test_web_upload_returns_photo_id()
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create(['items_remaining' => 0]);

        // Web route uses auth:api,web — web guard works
        $response = $this->actingAs($user)->postJson('/api/upload', [
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
            'items_remaining' => 0,
        ]);
        $team->users()->attach($user->id);

        $response = $this->actingAs($user)->postJson('/api/upload', [
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

        $user = User::factory()->create(['active_team' => null, 'items_remaining' => 0]);

        $response = $this->actingAs($user)->postJson('/api/upload', [
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

        $user = User::factory()->create(['items_remaining' => 0]);
        $file = new UploadedFile(
            storage_path('framework/testing/img_with_exif.JPG'),
            'photo.jpg',
            'image/jpeg',
            null,
            true
        );

        // First upload succeeds
        $response = $this->actingAs($user)->postJson('/api/upload', ['photo' => $file]);
        $response->assertOk();

        // Second upload of same photo (same EXIF datetime) is rejected
        $response = $this->actingAs($user)->postJson('/api/upload', ['photo' => $file]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('photo');
    }
}
