<?php

namespace Tests\Feature\Migration;

use App\Actions\Tags\AddTagsToPhotoActionNew;
use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Tags\CustomTagNew;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Models\Photo;
use App\Services\Tags\UpdateTagsService;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTagsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AddTagsToPhotoActionNew $addTagsToPhotoActionNew;
    protected UpdateTagsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GenerateTagsSeeder::class);
        $this->service = app(UpdateTagsService::class);
        $this->addTagsToPhotoActionNew = app(AddTagsToPhotoActionNew::class);
    }

    /** @test */
    public function it_migrates_old_tags_and_custom_tags_to_the_new_structure()
    {
        // Arrange: create legacy category resource in smokings table
        $smoking = Smoking::create([
            'butts' => 3,
            'cigaretteBox' => 2,
        ]);

        // Create photo and associate the legacy record via foreign key
        $photo = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'remaining' => 0,
        ]);

        // Create old-style custom tags
        $photo->customTags()->createMany([
            ['tag' => 'random_litter'],
            ['tag' => 'party_waste'],
        ]);

        // Act: run the migration
        $this->service->updateTags($photo);

        // Assert: photo_tags created
        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'quantity' => 3,
        ]);

        // Assert: extra material tags created
        $this->assertGreaterThanOrEqual(
            1,
            PhotoTagExtraTags::where('photo_tag_id', PhotoTag::first()->id)
                ->where('tag_type', 'material')
                ->count()
        );

        // Assert: custom tags migrated
        foreach (['random_litter', 'party_waste'] as $customKey) {
            $this->assertDatabaseHas('custom_tags_new', ['key' => $customKey]);
        }

        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'custom_tag_primary_id' => CustomTagNew::where('key', 'random_litter')->first()->id,
        ]);
    }


    /** @test */
    public function it_skips_empty_tags_gracefully()
    {
        $photo = Photo::factory()->create([
            'tags' => [],
            'customTags' => [],
        ]);

        $this->service->updateTags($photo);

        $this->assertDatabaseMissing('photo_tags', [
            'photo_id' => $photo->id,
        ]);
    }

    public function it_parses_deprecated_tags_and_creates_photo_tags_and_material_links()
    {
        $photo = Photo::factory()->create([
            'tags' => [
                'alcohol' => [
                    'beerBottle' => 1,
                ],
            ],
            'remaining' => 0,
        ]);

        $this->service->updateTags($photo);

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();
        $this->assertNotNull($photoTag);

        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'material',
        ]);
    }

    /** @test */
    public function it_creates_brand_object_relationships_and_links_extra_tags()
    {
        $photo = Photo::factory()->create([
            'tags' => [
                'softdrinks' => [
                    'energy_can' => 1,
                    'redbull' => 1,  // brand
                ],
            ],
            'remaining' => 1,
        ]);

        $this->service->updateTags($photo);

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'brand',
        ]);
    }

    /** @test */
    public function it_creates_single_photo_tag_with_custom_tags_when_no_objects_exist()
    {
        $photo = Photo::factory()->create([
            'tags' => [],
            'customTags' => ['illegal_dumping', 'garden_waste'],
            'remaining' => 0,
        ]);

        $this->service->updateTags($photo);

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertNotNull($photoTag);
        $this->assertNotNull($photoTag->custom_tag_primary_id);
        $this->assertDatabaseCount('photo_tag_extra_tags', 1); // only 1 extra tag expected
    }

    /** @test */
    public function it_creates_multiple_photo_tags_when_multiple_objects_in_one_category()
    {
        $photo = Photo::factory()->create([
            'tags' => [
                'alcohol' => [
                    'beer_can' => 1,
                    'wineBottle' => 1,
                ],
            ],
            'remaining' => 0,
        ]);

        $this->service->updateTags($photo);

        $photoTags = PhotoTag::where('photo_id', $photo->id)->get();

        $this->assertCount(2, $photoTags);
    }

    /** @test */
    public function it_attaches_custom_tags_as_extras_if_objects_also_exist()
    {
        $photo = Photo::factory()->create([
            'tags' => [
                'alcohol' => ['beer_can' => 1],
            ],
            'customTags' => ['festival', 'camping'],
        ]);

        $this->service->updateTags($photo);

        $photoTag = PhotoTag::where('photo_id', $photo->id)->latest()->first();

        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'custom',
        ]);
    }

    /** @test */
    public function it_skips_undefined_tags_and_logs_warning()
    {
        $photo = Photo::factory()->create([
            'tags' => [
                'unknown_category' => [
                    'non_existent_tag' => 1,
                ],
            ]
        ]);

        $this->service->updateTags($photo);

        $this->assertDatabaseMissing('photo_tags', ['photo_id' => $photo->id]);
    }

    /** @test */
    public function it_reuses_existing_category_object_relationships()
    {
        $photo = Photo::factory()->create([
            'tags' => [
                'smoking' => [
                    'skins' => 1,
                ],
            ]
        ]);

        // Run once to create the relationship
        $this->service->updateTags($photo);
        $this->assertDatabaseCount('category_object', 1);

        // Run again, should not create duplicate
        $this->service->updateTags($photo);
        $this->assertDatabaseCount('category_object', 1);
    }
}
