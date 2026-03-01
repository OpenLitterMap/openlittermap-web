<?php

namespace Tests\Feature\Tags;

use App\Enums\CategoryKey;
use App\Enums\LitterModels;
use App\Events\TagsVerifiedByAdmin;
use App\Models\Teams\Team;
use App\Models\Teams\TeamType;
use App\Models\Users\User;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\PhotoTag;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class AddNewTagsToPhotosTest extends TestCase
{
    use HasPhotoUploads;

    protected array $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPhotoUploads();

        $this->imageAndAttributes = $this->getImageAndAttributes();
    }

    /**
     * Test new tagging upload
     */
    public function test_it_adds_tags_to_a_photo (): void
    {
        $this->seed(GenerateTagsSeeder::class);
        $this->seed(GenerateBrandsSeeder::class);

        $user = User::factory()->create();

        $this->actingAs($user);

        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        $category = Category::where('key', CategoryKey::Smoking->value)->first();
        $object = LitterObject::where('key', 'butts')->first();
        $pickedUp = true;
        $quantity = 3;
        $brand = BrandList::where('key', 'marlboro')->first();
        $materials = Materials::whereIn('key', ['plastic', 'paper'])->get();

        $tags = [
            [
                'category' => ['id' => $category->id],
                'object' => ['id' => $object->id],
                'picked_up' => $pickedUp,
                'quantity' => $quantity,
                'materials' => [
                    ['id' => $materials[0]->id, 'key' => $materials[0]->key],
                    ['id' => $materials[1]->id, 'key' => $materials[1]->key]
                ],
                'brands' => [
                    $brand
                ],
                'custom_tags' => [
                    'new tag 1',
                    'new tag 2'
                ]
            ]
        ];

        $response = $this->post('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => $tags
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'picked_up' => $pickedUp,
            'quantity' => $quantity,
        ]);

        // Retrieve the created photo tag record.
        $photoTag = PhotoTag::where('photo_id', $photo->id)
            ->where('category_id', $category->id)
            ->where('litter_object_id', $object->id)
            ->first();
        $this->assertNotNull($photoTag);

        // Assert that extra tag records were created for the materials.
        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type'     => LitterModels::MATERIALS->value,
            'tag_type_id'  => $materials[0]->id,
            'quantity'     => 1,  // assuming a default extra quantity of 1
        ]);
        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type'     => LitterModels::MATERIALS->value,
            'tag_type_id'  => $materials[1]->id,
            'quantity'     => 1,
        ]);

        // Assert that an extra tag record was created for the brand.
        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type'     => LitterModels::BRANDS->value,
            'tag_type_id'  => $brand->id,
            'quantity'     => 1,
        ]);

        // For custom tags, first ensure the tags are created.
        $customTag1 = CustomTagNew::where('key', 'new tag 1')->first();
        $customTag2 = CustomTagNew::where('key', 'new tag 2')->first();
        $this->assertNotNull($customTag1);
        $this->assertNotNull($customTag2);

        // Assert that extra tag records were created for each custom tag.
        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type'     => LitterModels::CUSTOM_TAGS->value,
            'tag_type_id'  => $customTag1->id,
            'quantity'     => 1,
        ]);
        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type'     => LitterModels::CUSTOM_TAGS->value,
            'tag_type_id'  => $customTag2->id,
            'quantity'     => 1,
        ]);
    }

    public function test_it_falls_back_when_object_does_not_match_category(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        // "butts" belongs only to "smoking", not "alcohol"
        $object = LitterObject::where('key', 'butts')->first();
        $wrongCategory = Category::where('key', CategoryKey::Alcohol->value)->first();
        $correctCategory = $object->categories()->first();

        $response = $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => $wrongCategory->key,
                    'object' => $object->key,
                ]
            ]
        ]);

        $response->assertOk();

        // Falls back to the correct category (smoking) instead of erroring
        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'category_id' => $correctCategory->id,
            'litter_object_id' => $object->id,
        ]);
    }

    public function test_it_fails_to_upload_if_the_user_does_not_own_the_photo (): void
    {
        // User 1 does not upload an image
        $user1 = User::factory()->create();

        // User 2 uploads an image
        $user2 = User::factory()->create();

        $this->actingAs($user2);

        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user2);

        $this->assertEquals($user2->id, $photo->user_id);

        // Log in as user1
        $this->actingAs($user1);

        $response = $this->post('/api/tags', [
            'photoId' => $photo->id,
            'tags' => [
                'object' => 'butts'
            ]
        ]);

        $response->assertStatus(405);
//        $content = json_decode($response->getContent(), true);
//        $this->assertEquals('Unauthenticated.', $content['msg']);
    }

    /**
     * Test XP calculation uses proper multipliers:
     * Upload=5, Object=1×qty, Brand=3×brandQty, Material=2×parentQty, CustomTag=1×parentQty
     */
    public function test_xp_calculation_uses_correct_multipliers(): void
    {
        $this->seed(GenerateTagsSeeder::class);
        $this->seed(GenerateBrandsSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);
        $photo->update(['remaining' => false]); // Simulate picked_up=true at upload

        $category = Category::where('key', CategoryKey::Smoking->value)->first();
        $object = LitterObject::where('key', 'butts')->first();
        $brand = BrandList::where('key', 'marlboro')->first();
        $materials = Materials::whereIn('key', ['plastic', 'paper'])->get();

        $quantity = 3;

        $response = $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category' => ['id' => $category->id],
                    'object' => ['id' => $object->id, 'key' => $object->key],
                    'quantity' => $quantity,
                    'picked_up' => false,
                    'materials' => [
                        ['id' => $materials[0]->id, 'key' => $materials[0]->key],
                        ['id' => $materials[1]->id, 'key' => $materials[1]->key],
                    ],
                    'brands' => [
                        ['id' => $brand->id, 'key' => $brand->key, 'quantity' => 2],
                    ],
                    'custom_tags' => ['test-tag'],
                ],
            ],
        ]);

        $response->assertOk();

        $photo->refresh();

        // Expected XP:
        // Upload base:  5
        // Object:       3 × 1  = 3  (qty=3, object XP=1)
        // 2 Materials:  2 × (3 × 2) = 12  (each material: parentQty × 2)
        // 1 Brand:      2 × 3  = 6  (brandQty=2, brand XP=3)
        // 1 Custom tag: 3 × 1  = 3  (parentQty × 1)
        // PickedUp:     5           (remaining=false because picked_up=true in upload)
        // Total: 5 + 3 + 12 + 6 + 3 + 5 = 34
        $this->assertEquals(34, $photo->xp, 'XP should use enum multipliers: Upload=5, Object=1, Brand=3, Material=2, Custom=1, PickedUp=5');
    }

    /**
     * When category_id is provided explicitly, use it instead of auto-resolving
     * from object->categories()->first(). This lets mobile send the user's
     * intended category for multi-category objects like bottle, can, etc.
     */
    public function test_explicit_category_id_overrides_auto_resolution(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        // "bottle" belongs to both "alcohol" and "soft_drinks".
        // Without explicit category_id, auto-resolution picks the first (alcohol).
        $object = LitterObject::where('key', 'bottle')->first();
        $categories = $object->categories()->orderBy('categories.id')->get();
        $this->assertGreaterThanOrEqual(2, $categories->count(), 'bottle must belong to 2+ categories');

        // Pick the second category (not the auto-resolved first one)
        $secondCategory = $categories[1];

        $response = $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category_id' => $secondCategory->id,
                    'object' => ['id' => $object->id],
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'category_id' => $secondCategory->id,
            'litter_object_id' => $object->id,
        ]);

        // Verify it did NOT use the first (auto-resolved) category
        $firstCategory = $categories[0];
        $this->assertDatabaseMissing('photo_tags', [
            'photo_id' => $photo->id,
            'category_id' => $firstCategory->id,
            'litter_object_id' => $object->id,
        ]);
    }

    /**
     * Single-category object with no category sent resolves correctly.
     */
    public function test_single_category_object_resolves_without_category_sent(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        // "butts" belongs only to "smoking"
        $object = LitterObject::where('key', 'butts')->first();
        $categories = $object->categories()->get();
        $this->assertCount(1, $categories, 'butts must belong to exactly 1 category');

        $response = $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'object' => ['id' => $object->id],
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'category_id' => $categories[0]->id,
            'litter_object_id' => $object->id,
        ]);
    }

    /**
     * Multi-category object with no category sent falls back to first().
     */
    public function test_multi_category_object_falls_back_when_no_category_sent(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        // "bottle" belongs to "alcohol" and "soft_drinks"
        $object = LitterObject::where('key', 'bottle')->first();
        $firstCategory = $object->categories()->first();
        $this->assertNotNull($firstCategory);

        $response = $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'object' => ['id' => $object->id],
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'category_id' => $firstCategory->id,
            'litter_object_id' => $object->id,
        ]);
    }

    /**
     * Multi-category object with WRONG category sent falls back to first().
     */
    public function test_multi_category_object_falls_back_when_wrong_category_sent(): void
    {
        $this->seed(GenerateTagsSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        // "bottle" belongs to "alcohol" and "soft_drinks" — NOT "smoking"
        $object = LitterObject::where('key', 'bottle')->first();
        $wrongCategory = Category::where('key', CategoryKey::Smoking->value)->first();
        $firstCategory = $object->categories()->first();

        // Verify smoking is not a valid category for bottle
        $this->assertFalse(
            $object->categories()->where('categories.id', $wrongCategory->id)->exists(),
            'smoking must not be a valid category for bottle'
        );

        $response = $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'category_id' => $wrongCategory->id,
                    'object' => ['id' => $object->id],
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertOk();

        // Should fall back to the first valid category, not the wrong one
        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'category_id' => $firstCategory->id,
            'litter_object_id' => $object->id,
        ]);

        $this->assertDatabaseMissing('photo_tags', [
            'photo_id' => $photo->id,
            'category_id' => $wrongCategory->id,
            'litter_object_id' => $object->id,
        ]);
    }

    /**
     * Non-trusted users get immediate leaderboard credit:
     * TagsVerifiedByAdmin fires so metrics are processed, even though
     * the photo stays at verified=0 (not visible on the global map).
     */
    public function test_non_trusted_user_tagging_fires_metrics_event(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        $this->seed(GenerateTagsSeeder::class);

        // Explicitly set verification_required=true (non-trusted, like a new user)
        $user = User::factory()->create(['verification_required' => true]);

        $this->actingAs($user);

        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        $object = LitterObject::where('key', 'butts')->first();

        $response = $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                ['object' => ['id' => $object->id], 'quantity' => 1],
            ],
        ]);

        $response->assertOk();

        // Event fires so ProcessPhotoMetrics runs → user appears on leaderboard
        Event::assertDispatched(TagsVerifiedByAdmin::class, function ($event) use ($photo) {
            return $event->photo_id === $photo->id;
        });

        // Photo stays unverified (not on global map until admin review)
        $photo->refresh();
        $this->assertEquals(0, $photo->verified->value);
    }

    /**
     * School students must NOT fire the metrics event at tag time.
     * Teacher must approve first (safeguarding pipeline).
     */
    public function test_school_student_tagging_does_not_fire_metrics_event(): void
    {
        Event::fake([TagsVerifiedByAdmin::class]);

        $this->seed(GenerateTagsSeeder::class);

        $schoolType = TeamType::firstOrCreate(['team' => 'school']);
        $team = Team::factory()->create([
            'type_id' => $schoolType->id,
            'is_trusted' => false,
        ]);

        $user = User::factory()->create(['verification_required' => true]);
        $user->teams()->attach($team);
        $user->active_team = $team->id;
        $user->save();

        $this->actingAs($user);

        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);
        $photo->team_id = $team->id;
        $photo->save();

        $object = LitterObject::where('key', 'butts')->first();

        $response = $this->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                ['object' => ['id' => $object->id], 'quantity' => 1],
            ],
        ]);

        $response->assertOk();

        // Event must NOT fire — teacher must approve first
        Event::assertNotDispatched(TagsVerifiedByAdmin::class);
    }
}
