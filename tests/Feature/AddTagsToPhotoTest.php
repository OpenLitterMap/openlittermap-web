<?php

namespace Tests\Feature;

use App\Events\ImageUploaded;
use App\Events\Photo\IncrementPhotoMonth;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Categories\Smoking;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Teams\Team;
use App\Models\User\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Tests\TestCase;

use App\Models\Photo;

class AddTagsToPhotoTest extends TestCase
{
    use HasPhotoUploads;

    protected $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setImagePath();

        $this->imageAndAttributes = $this->getImageAndAttributes();
    }

    protected function tearDown(): void
    {
        if (File::exists($this->imageAndAttributes['filepath'])) {
            File::delete($this->imageAndAttributes['filepath']);
        }

        parent::tearDown();
    }

    public function test_a_user_can_add_tags_to_a_photo()
    {
        // User uploads an image -------------------------
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->post('/submit', [
            'file' => $this->imageAndAttributes['file'],
        ]);

        $photo = $user->fresh()->photos->last();

        // User adds tags to an image -------------------
        $this->post('/add-tags', [
            'photo_id' => $photo->id,
            'presence' => true,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ])->assertOk();

        // Assert tags are stored correctly ------------
        $photo->refresh();

        $this->assertEquals(1, $photo->remaining);
        $this->assertNotNull($photo->smoking_id);
        $this->assertInstanceOf(Smoking::class, $photo->smoking);
        $this->assertEquals(3, $photo->smoking->butts);
    }

    public function test_user_and_photo_info_are_updated_when_a_user_adds_tags_to_a_photo()
    {
        // User uploads an image -------------------------
        $user = User::factory()->create([
            'verification_required' => true
        ]);

        $this->actingAs($user);

        $this->post('/submit', [
            'file' => $this->imageAndAttributes['file'],
        ]);

        $photo = $user->fresh()->photos->last();

        // User adds tags to an image -------------------
        $this->post('/add-tags', [
            'photo_id' => $photo->id,
            'presence' => false,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ],
                'alcohol' => [
                    'beerBottle' => 5
                ]
            ]
        ])->assertOk();

        // Assert user and photo info are updated correctly ------------
        $user->refresh();
        $photo->refresh();

        $this->assertEquals(9, $user->xp); // 1 xp from uploading, + 8xp from total litter tagged
        $this->assertEquals(8, $photo->total_litter);
        $this->assertEquals(0, $photo->remaining);
        $this->assertEquals(0.1, $photo->verification);
    }

    public function test_it_fires_tags_verified_by_admin_event_when_a_verified_user_adds_tags_to_a_photo()
    {
        Event::fake(TagsVerifiedByAdmin::class);

        // User uploads an image -------------------------
        $user = User::factory()->create([
            'verification_required' => false
        ]);

        $this->actingAs($user);

        $this->post('/submit', [
            'file' => $this->imageAndAttributes['file'],
        ]);

        $photo = $user->fresh()->photos->last();

        // User adds tags to an image -------------------
        $this->post('/add-tags', [
            'photo_id' => $photo->id,
            'presence' => false,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ])->assertOk();

        // Assert event is fired ------------
        $photo->refresh();

        $this->assertEquals(1, $photo->verification);
        $this->assertEquals(2, $photo->verified);

        Event::assertDispatched(
            TagsVerifiedByAdmin::class,
            function (TagsVerifiedByAdmin $e) use ($photo) {
                return $e->photo_id === $photo->id;
            }
        );
    }

    public function test_leaderboards_are_updated_when_a_user_with_public_name_adds_tags_to_a_photo()
    {
        // User uploads an image -------------------------
        $user = User::factory()->create([
            'show_name' => true
        ]);

        $this->actingAs($user);

        $this->post('/submit', [
            'file' => $this->imageAndAttributes['file'],
        ]);

        $photo = $user->fresh()->photos->last();

        $country = Country::find($photo->country_id)->country;
        $state = State::find($photo->state_id)->state;
        $city = City::find($photo->city_id)->city;

        Redis::del("{$country}:Leaderboard");
        Redis::del("{$country}:{$state}:Leaderboard");
        Redis::del("{$country}:{$state}:{$city}:Leaderboard");

        $this->assertEquals(0, Redis::zscore("{$country}:Leaderboard", $user->id));
        $this->assertEquals(0, Redis::zscore("{$country}:{$state}:Leaderboard", $user->id));
        $this->assertEquals(0, Redis::zscore("{$country}:{$state}:{$city}:Leaderboard", $user->id));

        // User adds tags to an image -------------------
        $this->post('/add-tags', [
            'photo_id' => $photo->id,
            'presence' => true,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ])->assertOk();

        // Assert leaderboards are updated ------------
        $this->assertEquals(1, Redis::zscore("{$country}:Leaderboard", $user->id));
        $this->assertEquals(1, Redis::zscore("{$country}:{$state}:Leaderboard", $user->id));
        $this->assertEquals(1, Redis::zscore("{$country}:{$state}:{$city}:Leaderboard", $user->id));
    }

    public function test_leaderboards_are_not_updated_when_a_user_with_private_name_adds_tags_to_a_photo()
    {
        // User uploads an image -------------------------
        $user = User::factory()->create([
            'show_name' => false,
            'show_username' => false
        ]);

        $this->actingAs($user);

        $this->post('/submit', [
            'file' => $this->imageAndAttributes['file'],
        ]);

        $photo = $user->fresh()->photos->last();

        $country = Country::find($photo->country_id)->country;
        $state = State::find($photo->state_id)->state;
        $city = City::find($photo->city_id)->city;

        Redis::del("{$country}:Leaderboard");
        Redis::del("{$country}:{$state}:Leaderboard");
        Redis::del("{$country}:{$state}:{$city}:Leaderboard");

        $this->assertEquals(0, Redis::zscore("{$country}:Leaderboard", $user->id));
        $this->assertEquals(0, Redis::zscore("{$country}:{$state}:Leaderboard", $user->id));
        $this->assertEquals(0, Redis::zscore("{$country}:{$state}:{$city}:Leaderboard", $user->id));

        // User adds tags to an image -------------------
        $this->post('/add-tags', [
            'photo_id' => $photo->id,
            'presence' => true,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ])->assertOk();

        // Assert leaderboards are not updated ------------
        $this->assertEquals(0, Redis::zscore("{$country}:Leaderboard", $user->id));
        $this->assertEquals(0, Redis::zscore("{$country}:{$state}:Leaderboard", $user->id));
        $this->assertEquals(0, Redis::zscore("{$country}:{$state}:{$city}:Leaderboard", $user->id));
    }
}
