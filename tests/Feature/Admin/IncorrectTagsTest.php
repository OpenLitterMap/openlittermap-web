<?php

namespace Tests\Feature\Admin;


use App\Models\Litter\Categories\Smoking;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
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

        $smokingId = $this->photo->smoking_id;

        // We make sure xp and tags are correct
        $this->assertEquals(4, $this->user->xp);
        $this->assertInstanceOf(Smoking::class, $this->photo->smoking);

        // Admin marks the tagging as incorrect -------------------
        $this->actingAs($this->admin);

        $this->post('/admin/incorrect', ['photoId' => $this->photo->id])
            ->assertOk();

        $this->user->refresh();
        $this->photo->refresh();

        // Assert xp is decreased, and tags are cleared
        $this->assertEquals(1, $this->user->xp);
        $this->assertEquals(0, $this->user->count_correctly_verified);
        $this->assertEquals(0, $this->photo->verification);
        $this->assertEquals(0, $this->photo->verified);
        $this->assertEquals(0, $this->photo->total_litter);
        $this->assertNull($this->photo->result_string);
        $this->assertNull($this->photo->smoking_id);
        $this->assertDatabaseMissing('smoking', ['id' => $smokingId]);
    }

    public function test_leaderboards_are_updated_when_an_admin_marks_tagging_incorrect_from_a_user_with_public_name()
    {
        $country = Country::find($this->photo->country_id)->country;
        $state = State::find($this->photo->state_id)->state;
        $city = City::find($this->photo->city_id)->city;

        Redis::del("{$country}:Leaderboard");
        Redis::del("{$country}:{$state}:Leaderboard");
        Redis::del("{$country}:{$state}:{$city}:Leaderboard");

        $this->user->update([
            'show_name' => true,
            'show_username' => true
        ]);

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

        $this->assertEquals(4, Redis::zscore("{$country}:Leaderboard", $this->user->id));
        $this->assertEquals(4, Redis::zscore("{$country}:{$state}:Leaderboard", $this->user->id));
        $this->assertEquals(4, Redis::zscore("{$country}:{$state}:{$city}:Leaderboard", $this->user->id));

        // Admin marks the tagging as incorrect -------------------
        $this->actingAs($this->admin);

        $this->post('/admin/incorrect', ['photoId' => $this->photo->id]);

        // Assert leaderboards are updated ------------
        $this->assertEquals(1, Redis::zscore("{$country}:Leaderboard", $this->user->id));
        $this->assertEquals(1, Redis::zscore("{$country}:{$state}:Leaderboard", $this->user->id));
        $this->assertEquals(1, Redis::zscore("{$country}:{$state}:{$city}:Leaderboard", $this->user->id));
    }

    public function test_leaderboards_are_not_updated_when_an_admin_marks_tagging_incorrect_from_a_user_with_private_name()
    {
        $country = Country::find($this->photo->country_id)->country;
        $state = State::find($this->photo->state_id)->state;
        $city = City::find($this->photo->city_id)->city;

        Redis::del("{$country}:Leaderboard");
        Redis::del("{$country}:{$state}:Leaderboard");
        Redis::del("{$country}:{$state}:{$city}:Leaderboard");

        $this->user->update([
            'show_name' => false,
            'show_username' => false
        ]);

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

        $this->assertNull(Redis::zscore("{$country}:Leaderboard", $this->user->id));
        $this->assertNull(Redis::zscore("{$country}:{$state}:Leaderboard", $this->user->id));
        $this->assertNull(Redis::zscore("{$country}:{$state}:{$city}:Leaderboard", $this->user->id));

        // Admin marks the tagging as incorrect -------------------
        $this->actingAs($this->admin);

        $this->post('/admin/incorrect', ['photoId' => $this->photo->id]);

        // Assert leaderboards are not updated ------------
        $this->assertNull(Redis::zscore("{$country}:Leaderboard", $this->user->id));
        $this->assertNull(Redis::zscore("{$country}:{$state}:Leaderboard", $this->user->id));
        $this->assertNull(Redis::zscore("{$country}:{$state}:{$city}:Leaderboard", $this->user->id));
    }

    public function test_unauthorized_users_cannot_mark_tagging_as_incorrect()
    {
        // Unauthenticated users ---------------------
        $response = $this->post('/admin/incorrect', ['photoId' => 1]);

        $response->assertRedirect('/');

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

        // A non-admin user tries to perform the action ------------
        $anotherUser = User::factory()->create();

        $this->actingAs($anotherUser);

        $response = $this->post('/admin/incorrect', ['photoId' => $this->photo->id]);

        $response->assertRedirect('/');

        $this->assertInstanceOf(Smoking::class, $this->photo->fresh()->smoking);
    }

    public function test_it_throws_not_found_exception_if_photo_doesnt_exist()
    {
        $this->actingAs($this->admin);

        $response = $this->post('/admin/incorrect', ['photoId' => 0]);

        $response->assertNotFound();
    }
}
