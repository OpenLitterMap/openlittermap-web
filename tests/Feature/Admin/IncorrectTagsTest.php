<?php

namespace Tests\Feature\Admin;


use App\Actions\LogAdminVerificationAction;
use App\Models\Photo;
use App\Models\Tag;
use App\Models\User\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class IncorrectTagsTest extends TestCase
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

        /** @var User $admin */
        $this->admin = User::factory()->create(['verification_required' => false]);
        $this->admin->assignRole(Role::create(['name' => 'admin']));
        $this->user = User::factory()->create(['verification_required' => true]);

        // User uploads an image -------------------
        $this->actingAs($this->user);
        $imageAndAttributes = $this->getImageAndAttributes();
        $this->post('/submit', ['file' => $imageAndAttributes['file']]);
        $this->photo = $this->user->fresh()->photos->last();
    }

    public function test_an_admin_can_mark_photos_as_incorrectly_tagged()
    {
        $tag = Tag::factory()->create();
        // User tags the image
        $this->actingAs($this->user);

        $this->post('/add-tags', [
            'photo_id' => $this->photo->id,
            'picked_up' => false,
            'tags' => [$tag->category->name => [$tag->name => 3]]
        ]);

        $this->photo->refresh();

        // We make sure xp and tags are correct
        $this->assertEquals(4, $this->user->xp);
        $this->assertEquals(0, $this->admin->xp);
        $this->assertCount(1, $this->photo->fresh()->tags);

        // Admin marks the tagging as incorrect -------------------
        $this->actingAs($this->admin);

        $this->post('/admin/incorrect', ['photoId' => $this->photo->id])
            ->assertOk();

        $this->user->refresh();
        $this->photo->refresh();

        // Assert xp is decreased, and tags are cleared
        $this->assertEquals(1, $this->user->xp);
        $this->assertEquals(0, $this->photo->verification);
        $this->assertEquals(0, $this->photo->verified);
        $this->assertEquals(0, $this->photo->total_litter);
        $this->assertNull($this->photo->result_string);
        $this->assertCount(0, $this->photo->fresh()->tags);
        // Admin is rewarded with 1 XP
        $this->assertEquals(1, $this->admin->xp);
    }

    public function test_leaderboards_are_updated_when_an_admin_marks_tagging_incorrect()
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

        // Admin marks the tagging as incorrect -------------------
        $this->actingAs($this->admin)->post('/admin/incorrect', ['photoId' => $this->photo->id]);

        // Assert leaderboards are updated ------------
        $this->assertEquals(1, $this->admin->xp_redis);
        $this->assertEquals(1, Redis::zscore("xp.users", $this->user->id));
        $this->assertEquals(1, Redis::zscore("xp.country.{$this->photo->country_id}", $this->user->id));
        $this->assertEquals(1, Redis::zscore("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}", $this->user->id));
        $this->assertEquals(1, Redis::zscore("xp.country.{$this->photo->country_id}.state.{$this->photo->state_id}.city.{$this->photo->city_id}", $this->user->id));
    }

    public function test_unauthorized_users_cannot_mark_tagging_as_incorrect()
    {
        $tag = Tag::factory()->create();
        // Unauthenticated users ---------------------
        $response = $this->post('/admin/incorrect', ['photoId' => 1]);

        $response->assertRedirect('/');

        // User tags the image
        $this->actingAs($this->user);

        $this->post('/add-tags', [
            'photo_id' => $this->photo->id,
            'picked_up' => false,
            'tags' => [$tag->category->name => [$tag->name => 3]]
        ]);

        // A non-admin user tries to perform the action ------------
        $anotherUser = User::factory()->create();

        $this->actingAs($anotherUser);

        $response = $this->post('/admin/incorrect', ['photoId' => $this->photo->id]);

        $response->assertRedirect('/');

        $this->assertCount(1, $this->photo->fresh()->tags);
    }

    public function test_it_throws_not_found_exception_if_photo_doesnt_exist()
    {
        $this->actingAs($this->admin);

        $response = $this->post('/admin/incorrect', ['photoId' => 0]);

        $response->assertNotFound();
    }


    public function test_it_logs_the_admin_action()
    {
        $spy = $this->spy(LogAdminVerificationAction::class);

        $this->actingAs($this->admin)
            ->post('/admin/incorrect', ['photoId' => $this->photo->id]);

        $spy->shouldHaveReceived('run');
    }
}
