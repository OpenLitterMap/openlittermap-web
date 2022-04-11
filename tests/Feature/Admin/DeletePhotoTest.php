<?php

namespace Tests\Feature\Admin;


use App\Actions\LogAdminVerificationAction;
use App\Events\ImageDeleted;
use App\Models\Photo;
use App\Models\Tag;
use App\Models\User\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class DeletePhotoTest extends TestCase
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

        /** @var User $admin */
        $this->admin = User::factory()->create(['verification_required' => false]);
        $this->admin->assignRole(Role::create(['name' => 'admin']));
        $this->user = User::factory()->create(['verification_required' => true]);

        // User uploads an image -------------------
        $this->actingAs($this->user);
        $this->imageAndAttributes = $this->getImageAndAttributes();
        $this->post('/submit', ['file' => $this->imageAndAttributes['file']]);
        $this->photo = $this->user->fresh()->photos->last();
    }

    public function test_an_admin_can_delete_photos_uploaded_by_users()
    {
        $tag = Tag::factory()->create();
        // User tags the image
        $this->actingAs($this->user);

        $this->post('/add-tags', [
            'photo_id' => $this->photo->id,
            'picked_up' => false,
            'tags' => [$tag->category->name => [$tag->name => 3]]
        ]);

        $this->user->refresh();

        // We make sure the photo exists
        Storage::disk('s3')->assertExists($this->imageAndAttributes['filepath']);
        Storage::disk('bbox')->assertExists($this->imageAndAttributes['filepath']);
        $this->assertEquals(0, $this->admin->xp);
        $this->assertEquals(1, $this->user->has_uploaded);
        $this->assertEquals(4, $this->user->xp);
        $this->assertEquals(1, $this->user->total_images);
        $this->assertInstanceOf(Photo::class, $this->photo);

        // Admin deletes the photo -------------------
        $this->actingAs($this->admin);
$this->withoutExceptionHandling();
        $this->post('/admin/destroy', ['photoId' => $this->photo->id]);

        $this->user->refresh();

        // Admin is rewarded with 1 XP
        $this->assertEquals(1, $this->admin->xp);
        // And it's gone
        $this->assertEquals(1, $this->user->has_uploaded); // TODO shouldn't it decrement?
        $this->assertEquals(0, $this->user->xp);
        $this->assertEquals(0, $this->user->total_images);
        Storage::disk('s3')->assertMissing($this->imageAndAttributes['filepath']);
        Storage::disk('bbox')->assertMissing($this->imageAndAttributes['filepath']);
        $this->assertCount(0, $this->user->photos);
        $this->assertDatabaseMissing('photos', ['id' => $this->photo->id]);
    }

    public function test_it_fires_imaged_deleted_event_when_an_admin_deletes_a_photo()
    {
        Event::fake(ImageDeleted::class);

        // Admin deletes the photo -------------------
        $this->actingAs($this->admin);

        $this->post('/admin/destroy', ['photoId' => $this->photo->id]);

        Event::assertDispatched(
            ImageDeleted::class,
            function (ImageDeleted $e) {
                return
                    $this->user->is($e->user) &&
                    $this->photo->country_id === $e->countryId &&
                    $this->photo->state_id === $e->stateId &&
                    $this->photo->city_id === $e->cityId;
            }
        );
    }

    public function test_leaderboards_are_updated_when_an_admin_deletes_a_photo()
    {
        $tag = Tag::factory()->create();
        // User has already uploaded an image, so their xp is 1
        Redis::zrem('xp.users', $this->admin->id);
        Redis::zadd("xp.users", 1, $this->user->id);
        Redis::zadd("xp.country.{$this->photo->country_id}", 1, $this->user->id);
        Redis::zadd("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}", 1, $this->user->id);
        Redis::zadd("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}.city.{$this->photo->city_id}", 1, $this->user->id);
        // User tags the image
        $this->post('/add-tags', [
            'photo_id' => $this->photo->id,
            'picked_up' => false,
            'tags' => [$tag->category->name => [$tag->name => 3]]
        ]);
        $this->assertEquals(0, $this->admin->xp_redis);
        $this->assertEquals(4, Redis::zscore("xp.users", $this->user->id));
        $this->assertEquals(4, Redis::zscore("xp.country.{$this->photo->country_id}", $this->user->id));
        $this->assertEquals(4, Redis::zscore("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}", $this->user->id));
        $this->assertEquals(4, Redis::zscore("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}.city.{$this->photo->city_id}", $this->user->id));

        // Admin deletes the photo -------------------
        $this->actingAs($this->admin)->post('/admin/destroy', ['photoId' => $this->photo->id]);

        // Assert leaderboards are updated ------------
        $this->assertEquals(1, $this->admin->xp_redis);
        $this->assertEquals(0, Redis::zscore("xp.users", $this->user->id));
        $this->assertEquals(0, Redis::zscore("xp.country.{$this->photo->country_id}", $this->user->id));
        $this->assertEquals(0, Redis::zscore("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}", $this->user->id));
        $this->assertEquals(0, Redis::zscore("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}.city.{$this->photo->city_id}", $this->user->id));
    }

    public function test_unauthorized_users_cannot_delete_photos()
    {
        // Unauthenticated users ---------------------
        $response = $this->post('/admin/destroy', ['photoId' => 1]);

        $response->assertRedirect('/');

        // A non-admin user tries to delete the photo ------------
        $anotherUser = User::factory()->create();

        $this->actingAs($anotherUser);

        $response = $this->post('/admin/destroy', ['photoId' => $this->photo->id]);

        $response->assertRedirect('/');

        $this->assertInstanceOf(Photo::class, $this->photo->fresh());
    }

    public function test_it_throws_not_found_exception_if_photo_doesnt_exist()
    {
        $this->actingAs($this->admin);

        $response = $this->post('/admin/destroy', ['photoId' => 0]);

        $response->assertNotFound();
    }

    public function test_it_logs_the_admin_action()
    {
        $spy = $this->spy(LogAdminVerificationAction::class);

        $this->actingAs($this->admin)
            ->post('/admin/destroy', ['photoId' => $this->photo->id]);

        $spy->shouldHaveReceived('run');
    }
}
