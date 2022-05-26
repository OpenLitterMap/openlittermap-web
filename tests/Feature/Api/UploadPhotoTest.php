<?php

namespace Tests\Feature\Api;

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

use App\Models\Photo;

class UploadPhotoTest extends TestCase
{
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setImagePath();

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
            'active_team' => Team::factory(),
            'items_remaining' => 0
        ]);

        $this->actingAs($user, 'api');

        $imageAttributes = $this->getImageAndAttributes();

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

        // Original image has the right dimensions
        $image = Image::make(Storage::disk('s3')->get($imageAttributes['filepath']));
        $this->assertEquals(1, $image->width());
        $this->assertEquals(1, $image->height());

        $user->refresh();

        // The Photo is persisted correctly
        $this->assertCount(1, $user->photos);
        /** @var Photo $photo */
        $photo = $user->photos->last();

        $this->assertEquals($imageAttributes['imageName'], $photo->filename);
        $this->assertEquals($imageAttributes['dateTime'], $photo->datetime);
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
        $this->assertEquals($this->getCountryId(), $photo->country_id);
        $this->assertEquals($this->getStateId(), $photo->state_id);
        $this->assertEquals($this->getCityId(), $photo->city_id);
        $this->assertEquals('mobile', $photo->platform);
        $this->assertEquals('dr15u73vccgyzbs9w4um', $photo->geohash);
        $this->assertEquals($user->active_team, $photo->team_id);
        $this->assertEquals($imageAttributes['bboxImageName'], $photo->five_hundred_square_filepath);

        Event::assertDispatched(
            ImageUploaded::class,
            function (ImageUploaded $e) use ($user, $imageAttributes) {
                return $e->city === $imageAttributes['address']['city'] &&
                    $e->state === $imageAttributes['address']['state'] &&
                    $e->country === $imageAttributes['address']['country'] &&
                    $e->countryCode === $imageAttributes['address']['country_code'] &&
                    $e->teamName === $user->team->name &&
                    $e->userId === $user->id &&
                    $e->countryId === $this->getCountryId() &&
                    $e->stateId === $this->getStateId() &&
                    $e->cityId === $this->getCityId() &&
                    $e->isUserVerified === !$user->verification_required;
            }
        );

        Event::assertDispatched(
            IncrementPhotoMonth::class,
            function (IncrementPhotoMonth $e) use ($imageAttributes) {
                return $e->country_id === $this->getCountryId() &&
                    $e->state_id === $this->getStateId() &&
                    $e->city_id === $this->getCityId() &&
                    $imageAttributes['dateTime']->is($e->created_at);
            }
        );
    }

    public function test_an_api_user_can_upload_a_photo_on_a_real_storage()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $imageAttributes = $this->getImageAndAttributes();

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

        // Original image has the right dimensions
        $image = Image::make(Storage::disk('s3')->get($imageAttributes['filepath']));
        $this->assertEquals(1, $image->width());
        $this->assertEquals(1, $image->height());

        $user->refresh();

        // The Photo is persisted correctly
        $this->assertCount(1, $user->photos);
        /** @var Photo $photo */
        $photo = $user->photos->last();

        $this->assertEquals($imageAttributes['imageName'], $photo->filename);
        $this->assertEquals($imageAttributes['bboxImageName'], $photo->five_hundred_square_filepath);

        // Cleanup
        /** @var DeletePhotoAction $deletePhotoAction */
        $deletePhotoAction = app(DeletePhotoAction::class);
        $deletePhotoAction->run($photo);
    }

    public function test_a_users_info_is_updated_when_they_upload_a_photo()
    {
        Storage::fake('s3');
        Storage::fake('bbox');
        /** @var User $user */
        $user = User::factory()->create();
        $this->assertEquals(0, $user->has_uploaded);
        $this->assertEquals(0, $user->xp);
        $this->assertEquals(0, $user->total_images);

        $this->actingAs($user, 'api')->post('/api/photos/submit',
            $this->getApiImageAttributes($this->getImageAndAttributes())
        );

        // User info gets updated
        $user->refresh();
        $this->assertEquals(1, $user->has_uploaded);
        $this->assertEquals(1, $user->xp);
        $this->assertEquals(1, $user->total_images);
    }

    public function test_a_users_xp_by_location_is_updated_when_they_upload_a_photo()
    {
        Storage::fake('s3');
        Storage::fake('bbox');
        /** @var User $user */
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


    public function validationDataProvider(): array
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
