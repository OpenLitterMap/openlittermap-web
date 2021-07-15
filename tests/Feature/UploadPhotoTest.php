<?php

namespace Tests\Feature;

use App\Events\ImageUploaded;
use App\Events\Photo\IncrementPhotoMonth;
use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Tests\TestCase;

use App\Models\Photo;

class UploadPhotoTest extends TestCase
{
    private $imagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imagePath = storage_path('framework/testing/1x1.jpg');
    }

    protected function getImageAndAttributes(): array
    {
        $exifImage = file_get_contents($this->imagePath);
        $file = UploadedFile::fake()->createWithContent(
            'image.jpg',
            $exifImage
        );
        $latitude = 40.053030045789;
        $longitude = -77.15449870066;
        $geoHash = 'dr15u73vccgyzbs9w4uj';
        $displayName = '10735, Carlisle Pike, Latimore Township,' .
            ' Adams County, Pennsylvania, 17324, USA';
        $address = [
            "house_number" => "10735",
            "road" => "Carlisle Pike",
            "city" => "Latimore Township",
            "county" => "Adams County",
            "state" => "Pennsylvania",
            "postcode" => "17324",
            "country" => "United States of America",
            "country_code" => "us",
            "suburb" => "unknown"
        ];

        // Since these models are created on runtime
        // and we haven't uploaded any images before
        // their ids should be 1
        $countryId = 1;
        $stateId = 1;
        $cityId = 1;

        $dateTime = now();
        $year = $dateTime->year;
        $month = $dateTime->month < 10 ? "0$dateTime->month" : $dateTime->month;
        $day = $dateTime->day < 10 ? "0$dateTime->day" : $dateTime->day;

        $localUploadsPath = "/local-uploads/$year/$month/$day/{$file->hashName()}";
        $filepath = public_path($localUploadsPath);
        $imageName = config('app.url') . $localUploadsPath;

        return compact(
            'latitude', 'longitude', 'geoHash', 'displayName', 'address',
            'countryId', 'stateId', 'cityId', 'dateTime', 'filepath', 'file', 'imageName'
        );
    }

    public function test_a_user_can_upload_a_photo()
    {
        Storage::fake();

        Event::fake([ImageUploaded::class, IncrementPhotoMonth::class]);

        Carbon::setTestNow();

        $user = User::factory()->create([
            'active_team' => Team::factory()
        ]);

        $this->actingAs($user);

        $imageAttributes = $this->getImageAndAttributes();

        $response = $this->post('/submit', [
            'file' => $imageAttributes['file'],
        ]);

        $response->assertOk()->assertJson(['msg' => 'success']);

        // Image is uploaded
        $this->assertFileExists($imageAttributes['filepath']);

        // Image has the right dimensions
        $image = Image::make(file_get_contents($imageAttributes['filepath']));
        $this->assertEquals(500, $image->width());
        $this->assertEquals(500, $image->height());

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
        $this->assertEquals($imageAttributes['address']['suburb'], $photo->suburb);
        $this->assertEquals($imageAttributes['address']['city'], $photo->city);
        $this->assertEquals($imageAttributes['address']['state'], $photo->county);
        $this->assertEquals($imageAttributes['address']['postcode'], $photo->state_district);
        $this->assertEquals($imageAttributes['address']['country'], $photo->country);
        $this->assertEquals($imageAttributes['address']['country_code'], $photo->country_code);
        $this->assertEquals('Unknown', $photo->model);
        $this->assertEquals($imageAttributes['countryId'], $photo->country_id);
        $this->assertEquals($imageAttributes['stateId'], $photo->state_id);
        $this->assertEquals($imageAttributes['cityId'], $photo->city_id);
        $this->assertEquals('web', $photo->platform);
        $this->assertEquals($imageAttributes['geoHash'], $photo->geohash);
        $this->assertEquals($user->active_team, $photo->team_id);
        $this->assertEquals($imageAttributes['imageName'], $photo->five_hundred_square_filepath);

        Event::assertDispatched(
            ImageUploaded::class,
            function (ImageUploaded $e) use ($user, $imageAttributes) {
                return $e->city === $imageAttributes['address']['city'] &&
                    $e->state === $imageAttributes['address']['state'] &&
                    $e->country === $imageAttributes['address']['country'] &&
                    $e->countryCode === $imageAttributes['address']['country_code'] &&
                    $e->imageName === $imageAttributes['imageName'] &&
                    $e->teamName === $user->team->name &&
                    $e->userId === $user->id &&
                    $e->countryId === $imageAttributes['countryId'] &&
                    $e->stateId === $imageAttributes['stateId'] &&
                    $e->cityId === $imageAttributes['cityId'];
            }
        );

        Event::assertDispatched(
            IncrementPhotoMonth::class,
            function (IncrementPhotoMonth $e) use ($imageAttributes) {
                return $e->country_id === $imageAttributes['countryId'] &&
                    $e->state_id === $imageAttributes['stateId'] &&
                    $e->city_id === $imageAttributes['cityId'] &&
                    $imageAttributes['dateTime']->is($e->created_at);
            }
        );

        // Tear down
        File::delete($imageAttributes['filepath']);
    }

    public function test_a_users_info_is_updated_when_they_upload_a_photo()
    {
        Storage::fake();

        Carbon::setTestNow();

        $user = User::factory()->create([
            'active_team' => Team::factory()
        ]);

        $this->actingAs($user);

        $imageAttributes = $this->getImageAndAttributes();

        $this->assertEquals(0, $user->has_uploaded);
        $this->assertEquals(0, $user->xp);
        $this->assertEquals(0, $user->total_images);

        $this->post('/submit', [
            'file' => $imageAttributes['file'],
        ]);

        // User info gets updated
        $user->refresh();
        $this->assertEquals(1, $user->has_uploaded);
        $this->assertEquals(1, $user->xp);
        $this->assertEquals(1, $user->total_images);

        // Tear down
        File::delete($imageAttributes['filepath']);
    }

    public function test_unauthenticated_users_cannot_upload_photos()
    {
        $response = $this->post('/submit', [
            'file' => 'file',
        ]);

        $response->assertRedirect('login');
    }

    public function test_the_uploaded_photo_is_validated()
    {
        Storage::fake();

        $user = User::factory()->create();

        $this->actingAs($user);

        $this->postJson('/submit', ['file' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $nonImage = UploadedFile::fake()->image('some.pdf');

        $this->postJson('/submit', [
            'file' => $nonImage
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_it_throws_server_error_when_photo_has_no_location_data()
    {
        Storage::fake();

        $user = User::factory()->create();

        $this->actingAs($user);

        $image = UploadedFile::fake()->image('image.jpg');

        $response = $this->post('/submit', [
            'file' => $image
        ]);

        $response->assertStatus(500);
    }
}
