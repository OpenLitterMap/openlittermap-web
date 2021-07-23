<?php

namespace Tests\Feature\Admin;


use App\Events\TagsVerifiedByAdmin;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\Models\Role;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class CorrectTagsDeletePhotoTest extends TestCase
{
    use HasPhotoUploads;

    /** @var User */
    protected $admin;
    /** @var User */
    protected $user;
    /** @var Photo */
    protected $photo;
    /** @var array */
    private $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setImagePath();

        /** @var User $admin */
        $this->admin = User::factory()->create(['verification_required' => false]);

        $this->admin->assignRole(Role::create(['name' => 'admin']));

        $this->user = User::factory()->create(['verification_required' => true]);

        // User uploads an image -------------------
        $this->actingAs($this->user);

        $this->imageAndAttributes = $this->getImageAndAttributes();

        $this->post('/submit', ['file' => $this->imageAndAttributes['file']]);

        $this->photo = $this->user->fresh()->photos->last();

        // User tags the image
        $this->actingAs($this->user);

        $this->post('/add-tags', [
            'photo_id' => $this->photo->id,
            'presence' => true,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ]);

        $this->photo->refresh();
    }

    protected function tearDown(): void
    {
        if (File::exists($this->imageAndAttributes['filepath'])) {
            File::delete($this->imageAndAttributes['filepath']);
        }

        parent::tearDown();
    }

    public function test_an_admin_can_verify_and_delete_photos_uploaded_by_users()
    {
        // We make sure the photo exists
        $this->assertFileExists($this->imageAndAttributes['filepath']);
        $this->assertEquals(4, $this->user->xp);

        // Admin verifies the photo -------------------
        $this->actingAs($this->admin);

        $this->post('/admin/verify', ['photoId' => $this->photo->id]);

        $this->user->refresh();
        $this->photo->refresh();

        // And it's gone
        $this->assertFileDoesNotExist($this->imageAndAttributes['filepath']);
        $this->assertEquals(4, $this->user->xp);
        $this->assertEquals('/assets/verified.jpg', $this->photo->filename);
        $this->assertEquals(1, $this->photo->verification);
        $this->assertEquals(2, $this->photo->verified);
    }

    public function test_unauthorized_users_cannot_verify_and_delete_photos()
    {
        // Unauthenticated users ---------------------
        $response = $this->post('/admin/verify', ['photoId' => 1]);

        $response->assertRedirect('/');

        // A non-admin user tries to verify the photo ------------
        $anotherUser = User::factory()->create();

        $this->actingAs($anotherUser);

        $response = $this->post('/admin/verify', ['photoId' => $this->photo->id]);

        $response->assertRedirect('/');

        $this->assertEquals(0.1, $this->photo->verification);
        $this->assertEquals(0, $this->photo->verified);
    }

    public function test_it_throws_not_found_exception_if_photo_doesnt_exist()
    {
        $this->actingAs($this->admin);

        $response = $this->post('/admin/verify', ['photoId' => 0]);

        $response->assertNotFound();
    }

    public function test_it_fires_tags_verified_by_admin_event()
    {
        Event::fake(TagsVerifiedByAdmin::class);

        // Admin verifies the photo -------------------
        $this->actingAs($this->admin);

        $this->post('/admin/verify', ['photoId' => $this->photo->id]);

        Event::assertDispatched(
            TagsVerifiedByAdmin::class,
            function (TagsVerifiedByAdmin $e) {
                return $e->photo_id === $this->photo->id;
            }
        );
    }
}
