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

    public function test_a_user_can_upload_a_photo()
    {
        Storage::fake();
        Event::fake([ImageUploaded::class, IncrementPhotoMonth::class]);

        Carbon::setTestNow();

        $user = User::factory()->create([
            'active_team' => Team::factory()
        ]);

        $this->actingAs($user);

        // Test image attributes ----------------------------
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
        // Test image attributes ----------------------------

        $this->assertEquals(0, $user->has_uploaded);
        $this->assertEquals(0, $user->xp);
        $this->assertEquals(0, $user->total_images);

        $response = $this->post('/submit', [
            'file' => $file,
        ]);

        $response->assertOk()->assertJson(['msg' => 'success']);

        // Image is uploaded
        $this->assertFileExists($filepath);

        // Image has the right dimensions
        $image = Image::make(file_get_contents($filepath));
        $this->assertEquals(500, $image->width());
        $this->assertEquals(500, $image->height());

        // User info gets updated
        $user->refresh();
        $this->assertEquals(1, $user->has_uploaded);
        $this->assertEquals(1, $user->xp);
        $this->assertEquals(1, $user->total_images);

        // The Photo is persisted correctly
        $this->assertCount(1, $user->photos);
        /** @var Photo $photo */
        $photo = $user->photos->first();

        $this->assertEquals($imageName, $photo->filename);
        $this->assertEquals($dateTime, $photo->datetime);
        $this->assertEquals($latitude, $photo->lat);
        $this->assertEquals($longitude, $photo->lon);
        $this->assertEquals($displayName, $photo->display_name);
        $this->assertEquals($address['house_number'], $photo->location);
        $this->assertEquals($address['road'], $photo->road);
        $this->assertEquals($address['suburb'], $photo->suburb);
        $this->assertEquals($address['city'], $photo->city);
        $this->assertEquals($address['state'], $photo->county);
        $this->assertEquals($address['postcode'], $photo->state_district);
        $this->assertEquals($address['country'], $photo->country);
        $this->assertEquals($address['country_code'], $photo->country_code);
        $this->assertEquals('Unknown', $photo->model);
        $this->assertEquals($countryId, $photo->country_id);
        $this->assertEquals($stateId, $photo->state_id);
        $this->assertEquals($cityId, $photo->city_id);
        $this->assertEquals('web', $photo->platform);
        $this->assertEquals($geoHash, $photo->geohash);
        $this->assertEquals($user->active_team, $photo->team_id);
        $this->assertEquals($imageName, $photo->five_hundred_square_filepath);

        // The right events are fired
        Event::assertDispatched(
            ImageUploaded::class,
            function (ImageUploaded $e) use ($user, $address, $imageName, $countryId, $stateId, $cityId) {
                return $e->city === $address['city'] &&
                    $e->state === $address['state'] &&
                    $e->country === $address['country'] &&
                    $e->countryCode === $address['country_code'] &&
                    $e->imageName === $imageName &&
                    $e->userId === $user->id &&
                    $e->countryId === $countryId &&
                    $e->stateId === $stateId &&
                    $e->cityId === $cityId;
            }
        );

        Event::assertDispatched(
            IncrementPhotoMonth::class,
            function (IncrementPhotoMonth $e) use ($countryId, $stateId, $cityId, $dateTime) {
                return $e->country_id === $countryId &&
                    $e->state_id === $stateId &&
                    $e->city_id === $cityId &&
                    $dateTime->is($e->created_at);
            }
        );

        // Tear down
        File::delete($filepath);
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

    public function test_it_throws_server_error_when_user_has_no_images_remaining()
    {
        Storage::fake();

        $user = User::factory()->create([
            'images_remaining' => 0
        ]);

        $this->actingAs($user);

        $image = UploadedFile::fake()->createWithContent(
            'image.jpg',
            file_get_contents($this->imagePath)
        );

        $response = $this->post('/submit', [
            'file' => $image
        ]);

        $response->assertStatus(500);
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
