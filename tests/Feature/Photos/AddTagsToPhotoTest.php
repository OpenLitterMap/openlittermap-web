<?php

namespace Tests\Feature\Photos;

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

        $this->actingAs($user);

        $this->post('/submit', [
            'file' => $this->imageAndAttributes['file'],
        ]);

        $photo = $user->fresh()->photos->last();

        // User adds tags to an image -------------------
        $this->post('/add-tags', [
            'photo_id' => $photo->id,
            'picked_up' => true,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ])->assertOk();

        // Assert tags are stored correctly ------------
        $photo->refresh();

        $this->assertTrue($photo->picked_up);
        $this->assertNotNull($photo->smoking_id);
        $this->assertInstanceOf(Smoking::class, $photo->smoking);
        $this->assertSame(3, $photo->smoking->butts);
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
            'picked_up' => true,
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

        $this->assertSame(9, $user->xp); // 1 xp from uploading, + 8xp from total litter tagged
        $this->assertSame(8, $photo->total_litter);
        $this->assertTrue($photo->picked_up);
        $this->assertEqualsWithDelta(0.1, $photo->verification, PHP_FLOAT_EPSILON);
    }

    public function test_it_forbids_adding_tags_to_a_verified_photo()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->post('/submit', [
            'file' => $this->imageAndAttributes['file'],
        ]);

        $photo = $user->fresh()->photos->last();

        $photo->update(['verified' => 1]);

        // User adds tags to the verified photo -------------------
        $response = $this->post('/add-tags', [
            'photo_id' => $photo->id,
            'picked_up' => true,
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
        $user = User::factory()->create([
            'verification_required' => true
        ]);

        $this->actingAs($user);

        // Missing photo_id -------------------
        $this->postJson('/add-tags', [
            'tags' => ['smoking' => ['butts' => 3]],
            'picked_up' => false
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo_id']);

        // Non-existing photo_id -------------------
        $this->postJson('/add-tags', [
            'photo_id' => 0,
            'tags' => ['smoking' => ['butts' => 3]],
            'picked_up' => false
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo_id']);

        // photo_id not belonging to the user -------------------
        $this->postJson('/add-tags', [
            'photo_id' => Photo::factory()->create()->id,
            'tags' => ['smoking' => ['butts' => 3]],
            'picked_up' => false
        ])
            ->assertForbidden();
    }

    public function test_request_tags_is_validated()
    {
        $user = User::factory()->create([
            'verification_required' => true
        ]);

        $this->actingAs($user);

        $this->post('/submit', [
            'file' => $this->imageAndAttributes['file'],
        ]);

        $photo = $user->fresh()->photos->last();

        // tags is empty -------------------
        $this->postJson('/add-tags', [
            'photo_id' => $photo->id,
            'tags' => [],
            'picked_up' => false
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tags']);

        // tags is not an array -------------------
        $this->postJson('/add-tags', [
            'photo_id' => $photo->id,
            'tags' => "asdf",
            'picked_up' => false
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tags']);
    }

    public function test_request_picked_up_is_validated()
    {
        $user = User::factory()->create([
            'verification_required' => true
        ]);

        $this->actingAs($user);

        $this->post('/submit', [
            'file' => $this->imageAndAttributes['file'],
        ]);

        $photo = $user->fresh()->photos->last();

        // presence is missing -------------------
        $this->postJson('/add-tags', [
            'photo_id' => $photo->id,
            'tags' => ['smoking' => ['butts' => 3]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['picked_up']);

        // picked_up is not a boolean -------------------
        $this->postJson('/add-tags', [
            'photo_id' => $photo->id,
            'tags' => ['smoking' => ['butts' => 3]],
            'picked_up' => 'asdf'
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['picked_up']);
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
            'picked_up' => true,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ])->assertOk();

        // Assert event is fired ------------
        $photo->refresh();

        $this->assertSame(1.0, $photo->verification);
        $this->assertSame(2, $photo->verified);

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
        $this->actingAs($user);
        $this->post('/submit', ['file' => $this->imageAndAttributes['file'],]);
        $photo = $user->fresh()->photos->last();
        Redis::del("xp.users");
        Redis::del("xp.country.$photo->country_id");
        Redis::del("xp.country.$photo->country_id.state.$photo->state_id");
        Redis::del("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id");
        $this->assertNull(Redis::zscore("xp.users", $user->id));
        $this->assertNull(Redis::zscore("xp.country.$photo->country_id", $user->id));
        $this->assertNull(Redis::zscore("xp.country.$photo->country_id.state.$photo->state_id", $user->id));
        $this->assertNull(Redis::zscore("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id", $user->id));

        // User adds tags to an image -------------------
        $this->post('/add-tags', [
            'photo_id' => $photo->id,
            'picked_up' => false,
            'tags' => ['smoking' => ['butts' => 3]]
        ])->assertOk();

        // Assert leaderboards are updated ------------
        // 3xp from tags
        $this->assertSame('3', Redis::zscore("xp.users", $user->id));
        $this->assertSame('3', Redis::zscore("xp.country.$photo->country_id", $user->id));
        $this->assertSame('3', Redis::zscore("xp.country.$photo->country_id.state.$photo->state_id", $user->id));
        $this->assertSame('3', Redis::zscore("xp.country.$photo->country_id.state.$photo->state_id.city.$photo->city_id", $user->id));
    }
}
