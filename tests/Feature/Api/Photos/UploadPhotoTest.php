<?php

namespace Tests\Feature\Api\Photos;

use App\Actions\Photos\DeletePhotoAction;
use App\Events\ImageUploaded;
use App\Events\Photo\IncrementPhotoMonth;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Teams\Team;
use App\Models\User\User;
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

        Event::fake([ImageUploaded::class, IncrementPhotoMonth::class]);

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
        $this->assertEquals($imageAttributes['address']['house_number'], $photo->location);
        $this->assertEquals($imageAttributes['address']['road'], $photo->road);
        $this->assertEquals($imageAttributes['address']['city'], $photo->city);
        $this->assertEquals($imageAttributes['address']['state'], $photo->county);
        $this->assertEquals($imageAttributes['address']['country'], $photo->country);
        $this->assertEquals($imageAttributes['address']['country_code'], $photo->country_code);
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
                    $e->teamName === $user->team->name &&
                    $e->userId === $user->id &&
                    $e->countryId === $location['country_id'] &&
                    $e->stateId === $location['state_id'] &&
                    $e->cityId === $location['city_id'] &&
                    $e->isUserVerified === !$user->verification_required;
            }
        );

        Event::assertDispatched(
            IncrementPhotoMonth::class,
            function (IncrementPhotoMonth $e) use ($imageAttributes, $location) {
                return $e->country_id === $location['country_id'] &&
                    $e->state_id === $location['state_id'] &&
                    $e->city_id === $location['city_id'] &&
                    $imageAttributes['dateTime']->is($e->created_at);
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

    public function test_a_users_info_is_updated_when_they_upload_a_photo(): void
    {
        Storage::fake('s3');
        Storage::fake('bbox');

        $user = User::factory()->create();
        $this->assertEquals(0, $user->has_uploaded);
        $this->assertEquals(0, $user->xp_redis);
        $this->assertEquals(0, $user->total_images);

        $this->actingAs($user, 'api')->post('/api/photos/submit',
            $this->getApiImageAttributes($this->getImageAndAttributes())
        );

        // User info gets updated
        $user->refresh();

        $this->assertEquals(1, $user->has_uploaded);
        $this->assertEquals(1, $user->xp_redis);
        $this->assertEquals(1, $user->total_images);
    }

    public function test_a_users_xp_by_location_is_updated_when_they_upload_a_photo()
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
        Redis::del("xp.country.$countryId.state.$stateId");
        Redis::del("xp.country.$countryId.state.$stateId.city.$cityId");
        $this->assertEquals(0, Redis::zscore("xp.users", $user->id));
        $this->assertEquals(0, Redis::zscore("xp.country.$countryId", $user->id));
        $this->assertEquals(0, Redis::zscore("xp.country.$countryId.state.$stateId", $user->id));
        $this->assertEquals(0, Redis::zscore("xp.country.$countryId.state.$stateId.city.$cityId", $user->id));

        $this->actingAs($user, 'api')->post('/api/photos/submit',
            $this->getApiImageAttributes($imageAttributes)
        );

        $this->assertEquals(1, Redis::zscore("xp.users", $user->id));
        $this->assertEquals(1, Redis::zscore("xp.country.$countryId", $user->id));
        $this->assertEquals(1, Redis::zscore("xp.country.$countryId.state.$stateId", $user->id));
        $this->assertEquals(1, Redis::zscore("xp.country.$countryId.state.$stateId.city.$cityId", $user->id));
    }

    public function test_unauthenticated_users_cannot_upload_photos()
    {
        $imageAttributes = $this->getImageAndAttributes();

        $response = $this->post('/api/photos/submit',
            $this->getApiImageAttributes($imageAttributes)
        );

        $response->assertRedirect('login');
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

}
