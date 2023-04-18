<?php

namespace Tests\Feature\Api;

use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Categories\Smoking;
use App\Models\Location\City;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Models\Photo;
use App\Models\User\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class AddTagsToPhotoTest extends TestCase
{
    use HasPhotoUploads;

    protected $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setImagePath();

        $this->imageAndAttributes = $this->getImageAndAttributes();
    }

    public function test_a_user_can_add_tags_to_a_photo()
    {
        // User uploads an image -------------------------
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );

        $photo = $user->fresh()->photos->last();

        // User adds tags to an image -------------------
        $this->post('/api/add-tags', [
            'photo_id' => $photo->id,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ])
            ->assertOk()
            ->assertJson(['success' => true, 'msg' => 'dispatched']);

        // Assert tags are stored correctly ------------
        $photo->refresh();

        $this->assertNotNull($photo->smoking_id);
        $this->assertInstanceOf(Smoking::class, $photo->smoking);
        $this->assertEquals(3, $photo->smoking->butts);
    }

    public function test_it_forbids_adding_tags_to_a_verified_photo()
    {
        // User uploads an image -------------------------
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );

        $photo = $user->fresh()->photos->last();

        $photo->update(['verified' => 1]);

        // User adds tags to the verified photo -------------------
        $response = $this->postJson('/api/add-tags', [
            'photo_id' => $photo->id,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ]);

        $response->assertForbidden();
        $this->assertNull($photo->fresh()->smoking_id);
    }

    public function test_request_photo_id_is_validated()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        // Missing photo_id -------------------
        $this->postJson('/api/add-tags', [
            'tags' => ['smoking' => ['butts' => 3]]
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo_id']);

        // Non-existing photo_id -------------------
        $this->postJson('/api/add-tags', [
            'photo_id' => 0,
            'tags' => ['smoking' => ['butts' => 3]]
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo_id']);

        // photo_id not belonging to the user -------------------
        $this->postJson('/api/add-tags', [
            'photo_id' => Photo::factory()->create()->id,
            'tags' => ['smoking' => ['butts' => 3]]
        ])
            ->assertForbidden();
    }

    public function test_request_tags_are_validated()
    {
        // User uploads an image -------------------------
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );

        $photo = $user->fresh()->photos->last();

        // tags are empty -------------------
//        $this->postJson('/api/add-tags', [
//            'photo_id' => $photo->id,
//            'tags' => []
//        ])
//        ->assertStatus(422)
//        ->assertJsonValidationErrors(['tags']);

        // tags is not an array -------------------
        $this->postJson('/api/add-tags', [
            'photo_id' => $photo->id,
            'tags' => "asdf"
        ])
        ->assertStatus(500); // should be 422?
        // ->assertJsonValidationErrors(['tags']);
    }

    public function test_user_and_photo_info_are_updated_when_a_user_adds_tags_to_a_photo()
    {
        // User uploads an image -------------------------
        $user = User::factory()->create([
            'verification_required' => true
        ]);

        $this->actingAs($user, 'api');

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );

        $photo = $user->fresh()->photos->last();

        // User adds tags to an image -------------------
        $this->post('/api/add-tags', [
            'photo_id' => $photo->id,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ],
                'alcohol' => [
                    'beerBottle' => 5
                ],
                'brands' => [
                    'aldi' => 1
                ]
            ]
        ])->assertOk();

        // Assert user and photo info are updated correctly ------------
        $user->refresh();
        $photo->refresh();

        $this->assertEquals(10, $user->xp); // 1 xp from uploading, + 8xp from total litter + 1xp from brand
        $this->assertEquals(8, $photo->total_litter);
        $this->assertEquals(0.1, $photo->verification);
    }

    public function test_a_photo_can_be_marked_as_picked_up_or_not()
    {
        // User uploads an image -------------------------
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );
        $photo = $user->fresh()->photos->last();

        // User marks the litter as picked up -------------------
        $this->post('/api/add-tags', [
            'photo_id' => $photo->id,
            'picked_up' => true,
            'tags' => ['smoking' => ['butts' => 3]]
        ]);

        $photo->refresh();
        $this->assertTrue($photo->picked_up);

        // User marks the litter as not picked up -------------------
        $this->post('/api/add-tags', [
            'photo_id' => $photo->id,
            'picked_up' => false,
            'tags' => ['smoking' => ['butts' => 3]]
        ]);

        $photo->refresh();
        $this->assertFalse($photo->picked_up);

        // User doesn't indicate whether litter is picked up -------------------
        // So it should default to user's predefined settings
        $user->items_remaining = false;
        $user->save();
        $this->post('/api/add-tags', [
            'photo_id' => $photo->id,
            'tags' => ['smoking' => ['butts' => 3]]
        ]);

        $photo->refresh();
        $this->assertTrue($photo->picked_up);
    }

    public function test_it_fires_tags_verified_by_admin_event_when_a_verified_user_adds_tags_to_a_photo()
    {
        Event::fake(TagsVerifiedByAdmin::class);

        // User uploads an image -------------------------
        $user = User::factory()->create([
            'verification_required' => false
        ]);

        $this->actingAs($user, 'api');

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );

        $photo = $user->fresh()->photos->last();

        // User adds tags to an image -------------------
        $this->post('/api/add-tags', [
            'photo_id' => $photo->id,
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

    public function test_leaderboards_are_updated_when_a_user_adds_tags_to_a_photo()
    {
        // User uploads an image -------------------------
        /** @var User $user */
        $user = User::factory()->create();
        $this->actingAs($user, 'api');
        $this->post('/api/photos/submit', $this->getApiImageAttributes($this->imageAndAttributes));
        $photo = $user->fresh()->photos->last();
        Redis::del("xp.users");
        Redis::del("xp.country.$photo->country_id");
        Redis::del("xp.country.$photo->country_id.state.$photo->state_id");
        Redis::del("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id");
        $this->assertEquals(0, Redis::zscore("xp.users", $user->id));
        $this->assertEquals(0, Redis::zscore("xp.country.$photo->country_id", $user->id));
        $this->assertEquals(0, Redis::zscore("xp.country.$photo->country_id.state.$photo->state_id", $user->id));
        $this->assertEquals(0, Redis::zscore("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id", $user->id));

        // User adds tags to an image -------------------
        $this->post('/api/add-tags', [
            'photo_id' => $photo->id,
            'tags' => ['smoking' => ['butts' => 3]]
        ])->assertOk();

        // Assert leaderboards are updated ------------
        // 3xp from tags
        $this->assertEquals(3, Redis::zscore("xp.users", $user->id));
        $this->assertEquals(3, Redis::zscore("xp.country.$photo->country_id", $user->id));
        $this->assertEquals(3, Redis::zscore("xp.country.$photo->country_id.state.$photo->state_id", $user->id));
        $this->assertEquals(3, Redis::zscore("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id", $user->id));
    }
}
