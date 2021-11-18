<?php

namespace Tests\Feature\Photos;

use App\Events\ImageUploaded;
use App\Events\Photo\IncrementPhotoMonth;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Teams\Team;
use App\Models\User\User;
use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class UploadPhotoFailedLocationTest extends TestCase
{
    use HasPhotoUploads;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setImagePath(false);

        // Suppose the Reverse Geocoding fails
        $this->mock(Client::class)
            ->shouldReceive('get')
            ->andThrow(\Exception::class);

        $country = Country::create(['country' => 'error_country', 'shortcode' => 'error']);
        $state = State::create(['state' => 'error_state', 'country_id' => $country->id]);
        City::create(['city' => 'error_city', 'country_id' => $country->id, 'state_id' => $state->id]);
    }

    public function test_a_user_can_upload_a_photo_even_if_reverse_geocoding_fails()
    {
        Storage::fake('s3');
        Storage::fake('bbox');
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

        // Response should be OK
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

        // The Photo is not persisted
        $this->assertCount(0, $user->fresh()->photos);

        // Events are not dispatched
        Event::assertNotDispatched(ImageUploaded::class);
        Event::assertNotDispatched(IncrementPhotoMonth::class);
    }

    public function test_a_users_info_is_not_updated_when_reverse_geocoding_fails()
    {
        Storage::fake('s3');
        Storage::fake('bbox');
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

        // User info stays the same
        $user->refresh();
        $this->assertEquals(0, $user->has_uploaded);
        $this->assertEquals(0, $user->xp);
        $this->assertEquals(0, $user->total_images);
    }
}
