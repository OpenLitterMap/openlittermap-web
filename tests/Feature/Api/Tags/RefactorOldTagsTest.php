<?php

namespace Tests\Feature\Api\Tags;

use App\Enums\VerificationStatus;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class RefactorOldTagsTest extends TestCase
{
    use HasPhotoUploads;

    protected $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
        Storage::fake('bbox');

        $this->setImagePath();
        $this->seed([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class,
        ]);

        $this->imageAndAttributes = $this->getImageAndAttributes();
    }

    public function test_a_user_can_add_tags_to_a_photo()
    {
        // User uploads an image
        $user = User::factory()->create();

        $this->actingAs($user, 'api');

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );

        $photo = $user->fresh()->photos->last();

        // User adds tags to an image
        $this->post('/api/add-tags', [
            'photo_id' => $photo->id,
            'tags' => [
                'smoking' => [
                    'butts' => 3
                ]
            ]
        ])
        ->assertOk()
        ->assertJson(['success' => true, 'msg' => 'tags-added']);

        // Assert v5 PhotoTags are created
        $photo->refresh();

        $this->assertTrue($photo->photoTags()->exists());
        $photoTag = $photo->photoTags()->first();
        $this->assertNotNull($photoTag->category_litter_object_id);
        $this->assertNotNull($photoTag->summary ?? $photo->summary);
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

        $photo->update(['verified' => VerificationStatus::VERIFIED->value]);

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
        $this->assertCount(0, PhotoTag::where('photo_id', $photo->id)->get());
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
        $this->postJson('/api/add-tags', [
            'photo_id' => $photo->id,
            'tags' => []
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tags']);

        // tags is not an array -------------------
        $this->postJson('/api/add-tags', [
            'photo_id' => $photo->id,
            'tags' => "asdf"
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tags']);
    }

    public function test_photo_info_is_updated_when_an_untrusted_user_adds_tags()
    {
        Event::fake(TagsVerifiedByAdmin::class);

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
            ]
        ])->assertOk();

        // v5: photo gets summary + XP from UpdateTagsService
        $photo->refresh();

        $this->assertNotNull($photo->summary);
        $this->assertGreaterThan(0, $photo->xp);
        // v5: verification float no longer written (deprecated)

        // v5: untrusted user does NOT trigger metrics processing
        Event::assertNotDispatched(TagsVerifiedByAdmin::class);
    }

    public function test_a_photo_can_be_marked_as_picked_up_or_not()
    {
        $user = User::factory()->create(['verification_required' => true]);
        $this->actingAs($user, 'api');

        // Photo 1: explicitly picked up -------------------
        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );
        $photo1 = $user->photos()->orderByDesc('id')->first();

        $this->post('/api/add-tags', [
            'photo_id' => $photo1->id,
            'picked_up' => true,
            'tags' => ['smoking' => ['butts' => 3]]
        ])->assertOk();

        $this->assertTrue($photo1->fresh()->picked_up);

        // Photo 2: explicitly not picked up -------------------
        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );
        $photo2 = $user->photos()->orderByDesc('id')->first();

        $this->post('/api/add-tags', [
            'photo_id' => $photo2->id,
            'picked_up' => false,
            'tags' => ['smoking' => ['butts' => 3]]
        ])->assertOk();

        $this->assertFalse($photo2->fresh()->picked_up);

        // Photo 3: no picked_up sent — defaults to user's items_remaining
        $user->items_remaining = false;
        $user->save();

        $this->post('/api/photos/submit',
            $this->getApiImageAttributes($this->imageAndAttributes)
        );
        $photo3 = $user->photos()->orderByDesc('id')->first();

        $this->post('/api/add-tags', [
            'photo_id' => $photo3->id,
            'tags' => ['smoking' => ['butts' => 3]]
        ])->assertOk();

        // items_remaining=false means pickedUp=true
        $this->assertTrue($photo3->fresh()->picked_up);
    }

    public function test_it_fires_tags_verified_by_admin_event_when_a_trusted_user_adds_tags()
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

        // Assert event is fired and photo is verified
        $photo->refresh();

        // v5: verification float no longer written (deprecated)
        $this->assertEquals(VerificationStatus::ADMIN_APPROVED, $photo->verified);

        Event::assertDispatched(
            TagsVerifiedByAdmin::class,
            function (TagsVerifiedByAdmin $e) use ($photo) {
                return $e->photo_id === $photo->id;
            }
        );
    }
}
