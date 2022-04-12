<?php

namespace Tests\Feature\Admin;


use App\Models\Location\Country;
use App\Models\User\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class GetPhotoTest extends TestCase
{
    use HasPhotoUploads;

    /** @var User */
    protected $admin;
    /** @var User */
    protected $user;

    /** @var array */
    private $imageAndAttributes;

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

    public function test_it_throws_not_found_exception_if_country_does_not_exist()
    {
        $this->actingAs($this->user);

        // Admin gets the next photo by country -------------------
        $this->actingAs($this->admin)
            ->getJson('/admin/get-image?country_id=' . 50000)
            ->assertStatus(422)
            ->assertJsonValidationErrors('country_id');
    }

}
