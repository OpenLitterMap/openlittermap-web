<?php

namespace Tests\Feature;

use App\Models\User\User;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

use App\Models\Photo;

class UploadPhotoToProductionTest extends TestCase
{
    use WithoutMiddleware;

    private $imagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imagePath = storage_path('framework/testing/1x1.jpg');
    }

    public function test_it_uploads_the_photo_to_s3_on_production()
    {
        Carbon::setTestNow();

        Storage::fake('s3');

        $user = User::factory()->create();

        $this->actingAs($user);

        $file = UploadedFile::fake()->createWithContent(
            'image.jpg',
            file_get_contents($this->imagePath)
        );

        $dateTime = now();
        $year = $dateTime->year;
        $month = $dateTime->month < 10 ? "0$dateTime->month" : $dateTime->month;
        $day = $dateTime->day < 10 ? "0$dateTime->day" : $dateTime->day;

        $filepath = "$year/$month/$day/{$file->hashName()}";
        $imageName = Storage::disk('s3')->url($filepath);

        app()->detectEnvironment(function () {
            return 'production';
        });

        $response = $this->post('/submit', [
            'file' => $file,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        // Image is uploaded
        Storage::disk('s3')->assertExists($filepath);

        // User info gets updated
        $user->refresh();
        $this->assertEquals(1, $user->has_uploaded);
        $this->assertEquals(1, $user->xp);
        $this->assertEquals(1, $user->total_images);

        // The Photo is persisted correctly
        $this->assertCount(1, $user->photos);

        $photo = $user->photos->first();
        $this->assertEquals($imageName, $photo->filename);
        $this->assertEquals($imageName, $photo->five_hundred_square_filepath);
    }

    public function test_it_throws_server_error_when_user_uploads_photos_with_the_same_datetime_on_production()
    {
        Carbon::setTestNow();

        $user = User::factory()->create();

        $this->actingAs($user);

        Photo::factory()->create([
            'user_id' => $user->id,
            'datetime' => now()
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'image.jpg',
            file_get_contents($this->imagePath)
        );

        app()->detectEnvironment(function () {
            return 'production';
        });

        $response = $this->post('/submit', [
            'file' => $file,
        ]);

        $response->assertStatus(500);
        $response->assertSee('Server Error');
    }
}
