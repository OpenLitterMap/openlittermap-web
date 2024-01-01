<?php

namespace Tests\Feature\Admin;


use App\Actions\LogAdminVerificationAction;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Categories\Smoking;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class CorrectTagsKeepPhotoTest extends TestCase
{
    use HasPhotoUploads;

    /** @var User */
    protected $admin;

    /** @var User */
    protected $user;

    /** @var Photo */
    protected $photo;

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

        $imageAndAttributes = $this->getImageAndAttributes();

        $this->post('/submit', ['file' => $imageAndAttributes['file']]);

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

    public function test_an_admin_can_mark_photos_as_correctly_tagged()
    {
        // We make sure xp and tags are correct
        Redis::zrem('xp.users', $this->admin->id);
        $this->assertSame(4, $this->user->xp);
        $this->assertNull($this->admin->xp);
        $this->assertSame(0, $this->admin->xp_redis);
        $this->assertInstanceOf(Smoking::class, $this->photo->smoking);

        // Admin marks the tagging as correct -------------------
        $this->actingAs($this->admin);

        $this->post('/admin/verify-tags-as-correct', ['photoId' => $this->photo->id])
            ->assertOk();

        $this->user->refresh();
        $this->photo->refresh();

        // Assert xp and tags don't change
        $this->assertSame(4, $this->user->xp);
        $this->assertSame(1.0, $this->photo->verification);
        $this->assertSame(2, $this->photo->verified);
        $this->assertSame(3, $this->photo->total_litter);
        $this->assertInstanceOf(Smoking::class, $this->photo->smoking);
        // Admin is rewarded with 1 XP
        $this->assertSame(1, $this->admin->xp);
        $this->assertSame(1, $this->admin->xp_redis);
    }

    public function test_unauthorized_users_cannot_mark_tagging_as_correct()
    {
        // Unauthenticated users ---------------------
        $response = $this->post('/admin/verify-tags-as-correct', ['photoId' => 1]);

        $response->assertRedirect('/');

        // A non-admin user tries to perform the action ------------
        $anotherUser = User::factory()->create();

        $this->actingAs($anotherUser);

        $response = $this->post('/admin/verify-tags-as-correct', ['photoId' => $this->photo->id]);

        $response->assertRedirect('/');

        $this->assertEqualsWithDelta(0.1, $this->photo->verification, PHP_FLOAT_EPSILON);
        $this->assertSame(0, $this->photo->verified);
    }

    public function test_it_throws_not_found_exception_if_photo_doesnt_exist()
    {
        $this->actingAs($this->admin);

        $response = $this->post('/admin/verify-tags-as-correct', ['photoId' => 0]);

        $response->assertNotFound();
    }

    public function test_it_fires_tags_verified_by_admin_event()
    {
        Event::fake(TagsVerifiedByAdmin::class);

        // Admin marks the tagging as correct -------------------
        $this->actingAs($this->admin);

        $this->post('/admin/verify-tags-as-correct', ['photoId' => $this->photo->id]);

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
            ->post('/admin/verify-tags-as-correct', ['photoId' => $this->photo->id]);

        // this is not working for sean: 23rd July 2022
         $spy->shouldHaveReceived('run');
    }
}
