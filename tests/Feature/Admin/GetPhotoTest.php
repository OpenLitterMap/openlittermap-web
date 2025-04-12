<?php

namespace Tests\Feature\Admin;

use App\Models\Location\Country;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class GetPhotoTest extends TestCase
{
    use HasPhotoUploads;

    protected $admin;
    protected $user;
    private array $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setImagePath();

        /** @var User $admin */
        $this->admin = User::factory()->create(['verification_required' => false]);
        $this->admin->assignRole(Role::create(['name' => 'admin']));

        $this->user = User::factory()->create(['verification_required' => true]);

        $this->imageAndAttributes = $this->getImageAndAttributes();
    }

    public function test_an_admin_can_filter_photos_by_country()
    {
        // User uploads a photo in the US
        $this->actingAs($this->user)->post('/submit', ['photo' => $this->imageAndAttributes['file']]);

        \Log::info(['photosCount', Photo::count()]);

        $photoInUS = $this->user->fresh()->photos->last();

        $this->assertDatabaseHas('photos', [
            'country_id' => $photoInUS->country_id,
            'user_id' => $this->user->id,
        ]);

        Country::where('shortcode', 'ca')->delete();

        // User uploads a photo in Canada
        $canada = Country::factory()->create([
            'country' => 'Canada',
            'shortcode' => 'ca',
            'slug' => 'canada',
            'created_by' => $this->user->id,
        ]);

        $this->address = [
            "house_number" => "123",
            "road" => "Bloor Street",
            "city" => "Toronto",
            "county" => "York",
            "state" => "Ontario",
            "postcode" => "M5H 2N2",
            "country" => "Canada",
            "country_code" => "ca",
            "suburb" => "Downtown"
        ];

        $canadaAttributes = $this->getImageAndAttributes('jpg')['file'];

        $this->actingAs($this->user)->post('/submit', ['photo' => $canadaAttributes]);

        \Log::info('Latest photo:', \App\Models\Photo::latest()->first()->toArray());

        $this->assertDatabaseHas('photos', [
            'country_id' => $canada->id,
            'user_id' => $this->user->id,
        ]);

        // Admin gets the next photo by country -------------------
        $response = $this->actingAs($this->admin)
            ->getJson('/admin/get-next-image-to-verify?country_id=' . $canada->id)
            ->assertOk();

        \Log::info($response->json());

        // And it's the correct photo
        $this->assertEquals($canada->id, $response->json('photo.country_id'));
    }

    public function test_it_throws_not_found_exception_if_country_does_not_exist()
    {
        $this->actingAs($this->user);

        // Admin gets the next photo by country -------------------
        $this->actingAs($this->admin)
            ->getJson('/admin/get-next-image-to-verify?country_id=' . 50000)
            ->assertStatus(422)
            ->assertJsonValidationErrors('country_id');
    }


    public function test_an_admin_should_not_see_photos_of_users_that_dont_want_their_photos_tagged_by_others()
    {
        $this->user->update(['prevent_others_tagging_my_photos' => true]);
        $this->actingAs($this->user)->post('/submit', ['photo' => $this->imageAndAttributes['file']]);

        $response = $this->actingAs($this->admin)->getJson('/admin/get-next-image-to-verify')->assertOk();

        $this->assertNull($response->json('photo'));
    }
}
