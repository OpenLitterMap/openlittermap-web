<?php

namespace Tests\Feature\Admin;

use App\Actions\LogAdminVerificationAction;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
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

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setImagePath();

        $this->admin = User::factory()->create(['verification_required' => false]);

        $this->admin->assignRole(Role::create(['name' => 'admin']));

        $this->user = User::factory()->create(['verification_required' => true]);

        // User uploads an image -------------------
        $this->actingAs($this->user);

        $this->imageAndAttributes = $this->getImageAndAttributes();

        $resp = $this->post('/submit', ['file' => $this->imageAndAttributes['file']]);

        $this->photo = $this->user->fresh()->photos->last();

        // User tags the image
        $this->actingAs($this->user);

        $this->post('/add-tags', [
            'photo_id' => $this->photo->id,
            'picked_up' => false,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ]);

        $this->photo->refresh();
    }

    public function test_an_admin_can_verify_and_delete_photos_uploaded_by_users()
    {
        // We make sure the photo exists
        Redis::zrem('xp.users', $this->admin->id);
        Storage::disk('s3')->assertExists($this->imageAndAttributes['filepath']);
        Storage::disk('bbox')->assertExists($this->imageAndAttributes['filepath']);
        $this->assertSame(4, $this->user->xp);
        $this->assertNull($this->admin->xp);
        $this->assertSame(0, $this->admin->xp_redis);

        // Admin verifies the photo -------------------
        $this->actingAs($this->admin);

        $response = $this->post('/admin/verify', ['photoId' => $this->photo->id]);

        $response->assertOk();
        $this->user->refresh();
        $this->photo->refresh();

        // And it's gone
        Storage::disk('s3')->assertMissing($this->imageAndAttributes['filepath']);
        Storage::disk('bbox')->assertMissing($this->imageAndAttributes['filepath']);
        $this->assertSame(4, $this->user->xp);
        $this->assertSame('/assets/verified.jpg', $this->photo->filename);
        $this->assertSame(1.0, $this->photo->verification);
        $this->assertSame(2, $this->photo->verified);
        // Admin is rewarded with 1 XP
        $this->assertSame(1, $this->admin->xp);
        $this->assertSame(1, $this->admin->xp_redis);
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

        $this->assertEqualsWithDelta(0.1, $this->photo->verification, PHP_FLOAT_EPSILON);
        $this->assertSame(0, $this->photo->verified);
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
                return $e->photo_id == $this->photo->id;
            }
        );
    }

    public function test_it_logs_the_admin_action()
    {
        $spy = $this->spy(LogAdminVerificationAction::class);

        $this->actingAs($this->admin)
            ->post('/admin/verify', ['photoId' => $this->photo->id]);

        $spy->shouldHaveReceived('run');
    }
}
