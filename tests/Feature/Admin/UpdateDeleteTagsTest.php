<?php

namespace Tests\Feature\Admin;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Categories\Alcohol;
use App\Models\Litter\Categories\Smoking;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\User\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\Models\Role;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class UpdateDeleteTagsTest extends TestCase
{
    use HasPhotoUploads;

    protected $admin;
    protected $user;
    protected $photo;
    private $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setImagePath();

        /** @var User $admin */
        $this->admin = User::factory()->create(['verification_required' => false]);

        $this->admin->assignRole(Role::create(['name' => 'admin']));

        $this->user = User::factory()->create(['verification_required' => true]);

        // User uploads and tags an image -------------------
        $this->actingAs($this->user);

        $this->imageAndAttributes = $this->getImageAndAttributes();

        $this->post('/submit', ['file' => $this->imageAndAttributes['file']]);

        $this->photo = $this->user->fresh()->photos->last();

        $this->post('/add-tags', [
            'photo_id' => $this->photo->id,
            'presence' => true,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->imageAndAttributes['filepath'])) {
            File::delete($this->imageAndAttributes['filepath']);
        }

        parent::tearDown();
    }

    public function provider(): array
    {
        return [
            ['route' => '/admin/contentsupdatedelete', 'deletesPhoto' => true, 'tagsKey' => 'categories'],
            ['route' => '/admin/update-tags', 'deletesPhoto' => false, 'tagsKey' => 'tags']
        ];
    }

    /**
     * @dataProvider provider
     */
    public function test_an_admin_can_update_tags(
        $route, $deletesPhoto, $tagsKey
    )
    {
        // Admin updates the tags and deletes the photo -------------------
        $this->actingAs($this->admin);

        $this->assertFileExists($this->imageAndAttributes['filepath']);

        $this->post($route, [
            'photoId' => $this->photo->id,
            $tagsKey => [
                'alcohol' => [
                    'beerBottle' => 10
                ]
            ]
        ])->assertOk();

        // Assert tags are stored correctly ------------
        $this->photo->refresh();

        $this->assertNotNull($this->photo->smoking_id);
        $this->assertInstanceOf(Smoking::class, $this->photo->smoking);
        $this->assertEquals(3, $this->photo->smoking->butts);

        $this->assertNotNull($this->photo->alcohol_id);
        $this->assertInstanceOf(Alcohol::class, $this->photo->alcohol);
        $this->assertEquals(10, $this->photo->alcohol->beerBottle);

        if ($deletesPhoto) {
            // Assert photo is deleted
            $this->assertFileDoesNotExist($this->imageAndAttributes['filepath']);
            // TODO this checks only local envs, should add tests for s3
        }
    }

    /**
     * @dataProvider provider
     */
    public function test_user_and_photo_info_are_updated_when_an_admin_updates_tags_of_a_photo(
        $route, $deletesPhoto, $tagsKey
    )
    {
        // Admin updates the tags and deletes the photo -------------------
        $this->actingAs($this->admin);

        $this->post($route, [
            'photoId' => $this->photo->id,
            $tagsKey => [
                'alcohol' => [
                    'beerBottle' => 10
                ]
            ]
        ])->assertOk();

        // Assert tags are stored correctly ------------
        $this->photo->refresh();

        $this->user->refresh();
        $this->photo->refresh();

        $this->assertEquals(14, $this->user->xp); // 1 xp from uploading, + 3xp from smoking + 10xp from alcohol

        $this->assertEquals(13, $this->photo->total_litter);
        $this->assertEquals(1, $this->photo->remaining);
        $this->assertEquals(1, $this->photo->verification);
        $this->assertEquals(2, $this->photo->verified);
    }

    /**
     * @dataProvider provider
     */
    public function test_it_fires_tags_verified_by_admin_event_when_an_admin_updates_tags_of_a_photo(
        $route, $deletesPhoto, $tagsKey
    )
    {
        Event::fake(TagsVerifiedByAdmin::class);

        // Admin updates the tags and deletes the photo -------------------
        $this->actingAs($this->admin);

        $this->post($route, [
            'photoId' => $this->photo->id,
            $tagsKey => [
                'alcohol' => [
                    'beerBottle' => 10
                ]
            ]
        ]);

        // Assert event is fired ------------
        Event::assertDispatched(
            TagsVerifiedByAdmin::class,
            function (TagsVerifiedByAdmin $e) {
                return $e->photo_id === $this->photo->id;
            }
        );
    }

    /**
     * @dataProvider provider
     */
    public function test_leaderboards_are_updated_when_an_admin_updates_tags_of_a_photo_from_a_user_with_public_name(
        $route, $deletesPhoto, $tagsKey
    )
    {
        $this->user->update([
            'show_name' => true,
            'show_username' => true
        ]);

        // Admin updates the tags and deletes the photo -------------------
        $this->actingAs($this->admin);

        $country = Country::find($this->photo->country_id)->country;
        $state = State::find($this->photo->state_id)->state;
        $city = City::find($this->photo->city_id)->city;

        Redis::del("{$country}:Leaderboard");
        Redis::del("{$country}:{$state}:Leaderboard");
        Redis::del("{$country}:{$state}:{$city}:Leaderboard");

        $this->assertEquals(0, Redis::zscore("{$country}:Leaderboard", $this->user->id));
        $this->assertEquals(0, Redis::zscore("{$country}:{$state}:Leaderboard", $this->user->id));
        $this->assertEquals(0, Redis::zscore("{$country}:{$state}:{$city}:Leaderboard", $this->user->id));

        $this->post($route, [
            'photoId' => $this->photo->id,
            $tagsKey => [
                'alcohol' => [
                    'beerBottle' => 10
                ]
            ]
        ]);

        // Assert leaderboards are updated ------------
        $this->assertEquals(1, Redis::zscore("{$country}:Leaderboard", $this->user->id));
        $this->assertEquals(1, Redis::zscore("{$country}:{$state}:Leaderboard", $this->user->id));
        $this->assertEquals(1, Redis::zscore("{$country}:{$state}:{$city}:Leaderboard", $this->user->id));
    }

    /**
     * @dataProvider provider
     */
    public function test_leaderboards_are_not_updated_when_an_admin_updates_tags_of_a_photo_from_a_user_with_private_name(
        $route, $deletesPhoto, $tagsKey
    )
    {
        $this->user->update([
            'show_name' => false,
            'show_username' => false
        ]);

        // Admin updates the tags and deletes the photo -------------------
        $this->actingAs($this->admin);

        $country = Country::find($this->photo->country_id)->country;
        $state = State::find($this->photo->state_id)->state;
        $city = City::find($this->photo->city_id)->city;

        Redis::del("{$country}:Leaderboard");
        Redis::del("{$country}:{$state}:Leaderboard");
        Redis::del("{$country}:{$state}:{$city}:Leaderboard");

        $this->assertEquals(0, Redis::zscore("{$country}:Leaderboard", $this->user->id));
        $this->assertEquals(0, Redis::zscore("{$country}:{$state}:Leaderboard", $this->user->id));
        $this->assertEquals(0, Redis::zscore("{$country}:{$state}:{$city}:Leaderboard", $this->user->id));

        $this->post($route, [
            'photoId' => $this->photo->id,
            $tagsKey => [
                'alcohol' => [
                    'beerBottle' => 10
                ]
            ]
        ]);

        // Assert leaderboards are updated ------------
        $this->assertEquals(0, Redis::zscore("{$country}:Leaderboard", $this->user->id));
        $this->assertEquals(0, Redis::zscore("{$country}:{$state}:Leaderboard", $this->user->id));
        $this->assertEquals(0, Redis::zscore("{$country}:{$state}:{$city}:Leaderboard", $this->user->id));
    }
}
