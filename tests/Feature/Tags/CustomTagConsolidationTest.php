<?php

namespace Tests\Feature\Tags;

use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Users\User;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\Feature\HasPhotoUploads;
use Tests\TestCase;

class CustomTagConsolidationTest extends TestCase
{
    use HasPhotoUploads;

    protected array $imageAndAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPhotoUploads();
        $this->seed(GenerateTagsSeeder::class);

        $this->imageAndAttributes = $this->getImageAndAttributes();
    }

    /** @test */
    public function three_custom_tags_consolidate_into_one_photo_tag(): void
    {
        $user = User::factory()->create();
        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        // POST: 3 custom tags as a consolidated payload (mimics frontend consolidation)
        $response = $this->actingAs($user)->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'custom' => true,
                    'key' => 'brand:goodalls',
                    'custom_tags' => ['object:container', 'material:hemp'],
                    'quantity' => 1,
                    'picked_up' => true,
                ],
            ],
        ]);

        $response->assertOk();

        // 1 PhotoTag row with no object
        $photoTags = PhotoTag::where('photo_id', $photo->id)->get();
        $this->assertCount(1, $photoTags, 'Should be 1 PhotoTag');

        $photoTag = $photoTags->first();
        $this->assertNull($photoTag->category_litter_object_id);

        // 3 custom_tag extras
        $extras = $photoTag->extraTags;
        $this->assertCount(3, $extras, 'Should have 3 extra tags');
        $this->assertTrue($extras->every(fn ($e) => $e->tag_type === 'custom_tag'));

        // Verify each custom tag was created
        $keys = $extras->map(fn ($e) => $e->extraTag->key)->sort()->values()->toArray();
        $this->assertEquals(['brand:goodalls', 'material:hemp', 'object:container'], $keys);

        // Summary should have all 3
        $photo->refresh();
        $this->assertNotNull($photo->summary);
        $this->assertEquals(3, $photo->summary['totals']['custom_tags']);
    }

    /** @test */
    public function edit_adds_more_custom_tags_via_replace(): void
    {
        $user = User::factory()->create();
        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        // POST: Initial 3 custom tags
        $this->actingAs($user)->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'custom' => true,
                    'key' => 'brand:goodalls',
                    'custom_tags' => ['object:container', 'material:hemp'],
                    'quantity' => 1,
                    'picked_up' => true,
                ],
            ],
        ])->assertOk();

        $this->assertCount(1, PhotoTag::where('photo_id', $photo->id)->get());

        // PUT: Replace with 5 custom tags (original 3 + 2 new)
        $response = $this->actingAs($user)->putJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'custom' => true,
                    'key' => 'brand:goodalls',
                    'custom_tags' => ['object:container', 'material:hemp', 'brand:supervalu', 'object:lid'],
                    'quantity' => 1,
                    'picked_up' => true,
                ],
            ],
        ]);

        $response->assertOk();

        // Still 1 PhotoTag
        $photoTags = PhotoTag::where('photo_id', $photo->id)->get();
        $this->assertCount(1, $photoTags, 'Should still be 1 PhotoTag after replace');

        // Now 5 custom_tag extras
        $photoTag = $photoTags->first();
        $extras = $photoTag->extraTags()->get();
        $this->assertCount(5, $extras, 'Should have 5 extra tags after replace');

        $keys = $extras->map(fn ($e) => $e->extraTag->key)->sort()->values()->toArray();
        $this->assertEquals(
            ['brand:goodalls', 'brand:supervalu', 'material:hemp', 'object:container', 'object:lid'],
            $keys
        );

        // Summary updated
        $photo->refresh();
        $this->assertEquals(5, $photo->summary['totals']['custom_tags']);
    }

    /** @test */
    public function custom_tags_deduplicate_in_database(): void
    {
        $user = User::factory()->create();
        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        $this->actingAs($user)->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'custom' => true,
                    'key' => 'brand:goodalls',
                    'quantity' => 1,
                    'picked_up' => true,
                ],
            ],
        ])->assertOk();

        // CustomTagNew row created
        $ct = CustomTagNew::where('key', 'brand:goodalls')->first();
        $this->assertNotNull($ct);
        $this->assertEquals($user->id, $ct->created_by);

        // Second photo reuses the same CustomTagNew
        $photo2 = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);
        $this->actingAs($user)->postJson('/api/v3/tags', [
            'photo_id' => $photo2->id,
            'tags' => [
                [
                    'custom' => true,
                    'key' => 'brand:goodalls',
                    'quantity' => 1,
                    'picked_up' => false,
                ],
            ],
        ])->assertOk();

        // Still only 1 CustomTagNew row
        $this->assertEquals(1, CustomTagNew::where('key', 'brand:goodalls')->count());
    }

    /** @test */
    public function photo_tag_extras_are_queryable_with_relationships(): void
    {
        $user = User::factory()->create();
        $photo = $this->createPhotoFromImageAttributes($this->imageAndAttributes, $user);

        $this->actingAs($user)->postJson('/api/v3/tags', [
            'photo_id' => $photo->id,
            'tags' => [
                [
                    'custom' => true,
                    'key' => 'brand:goodalls',
                    'custom_tags' => ['object:container'],
                    'quantity' => 1,
                    'picked_up' => true,
                ],
            ],
        ])->assertOk();

        // Verify via Eloquent relationships
        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();
        $this->assertNotNull($photoTag);
        $this->assertNull($photoTag->category_litter_object_id);

        $extras = $photoTag->extraTags()->with('extraTag')->get();
        $this->assertCount(2, $extras, 'Should have 2 extra tags');
        $this->assertTrue($extras->every(fn ($e) => $e->tag_type === 'custom_tag'));

        $keys = $extras->map(fn ($e) => $e->extraTag->key)->sort()->values()->toArray();
        $this->assertEquals(['brand:goodalls', 'object:container'], $keys);

        // Verify CustomTagNew records exist and are reusable
        $this->assertEquals(1, CustomTagNew::where('key', 'brand:goodalls')->count());
        $this->assertEquals(1, CustomTagNew::where('key', 'object:container')->count());
    }
}
