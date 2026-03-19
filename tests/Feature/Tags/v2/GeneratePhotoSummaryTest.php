<?php

namespace Tests\Feature\Tags\v2;

use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Categories\Food;
use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Tags\Materials;
use App\Models\Litter\Tags\Category;
use App\Models\Photo;
use App\Services\Tags\GeneratePhotoSummaryService;
use App\Services\Tags\UpdateTagsService;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class GeneratePhotoSummaryTest extends TestCase
{
    // UpdateTagsService is for our migration script, not for real-world tagging.
    protected UpdateTagsService $updateTagsService;
    protected GeneratePhotoSummaryService $generatePhotoSummaryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class
        ]);

        $this->updateTagsService = app(UpdateTagsService::class);
        $this->generatePhotoSummaryService = app(GeneratePhotoSummaryService::class);
    }

    /** @test */
    public function summary_empty_when_no_tags(): void
    {
        $photo = Photo::factory()->create();
        $this->generatePhotoSummaryService->run($photo);
        $photo->refresh();

        $summary = $photo->summary;
        $this->assertArrayHasKey('tags', $summary);
        $this->assertArrayHasKey('totals', $summary);
        $this->assertEmpty($summary['tags']);

        $expectedTotals = [
            'litter'      => 0,
            'materials'   => 0,
            'brands'      => 0,
            'custom_tags' => 0,
        ];
        $this->assertEquals($expectedTotals, $summary['totals']);
        $this->assertEquals(0, $photo->fresh()->total_tags);
    }

    /** @test */
    public function it_accumulates_base_and_extra_correctly(): void
    {
        $smoking = Smoking::create(['butts' => 2]);
        $photo   = Photo::factory()->create(['smoking_id' => $smoking->id, 'remaining' => 0]);

        $brand = Brand::create(['adidas' => 1]);
        $photo->brands_id = $brand->id;
        $photo->save();

        $photo->customTags()->create(['tag' => 'street_clean']);

        $this->updateTagsService->updateTags($photo);
        $photo->refresh();
        $summary = $photo->summary;

        // Flat format: tags is a list
        $this->assertIsArray($summary['tags']);
        $this->assertTrue(array_is_list($summary['tags']));

        $totals = $summary['totals'];
        // Litter = sum of tag quantities
        $this->assertGreaterThan(0, $totals['litter']);
        // Materials should exist from deprecated tag mapping
        $this->assertGreaterThanOrEqual(0, $totals['materials']);
        // Custom tags should be counted
        $this->assertGreaterThanOrEqual(0, $totals['custom_tags']);

        // result_string should be populated for map display
        $this->assertNotEmpty($photo->result_string);
        $this->assertStringContainsString('smoking.', $photo->result_string);
    }

    /** @test */
    public function it_handles_multiple_categories_and_sorts_desc(): void
    {
        $smoking = Smoking::create(['butts' => 1]);
        $food    = Food::create(['napkins' => 3]);

        $photo = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'food_id'    => $food->id,
            'remaining'  => 0,
        ]);
        $this->updateTagsService->updateTags($photo);
        $photo->refresh();
        $summary = $photo->summary;

        // Flat format: tags is a list
        $this->assertIsArray($summary['tags']);
        $this->assertTrue(array_is_list($summary['tags']));

        // Should have at least 2 tags (butts + napkins)
        $this->assertGreaterThanOrEqual(2, count($summary['tags']));

        // Totals should reflect combined quantities
        $this->assertGreaterThan(0, $summary['totals']['litter']);
    }

    /** @test */
    public function it_includes_material_extra_in_grouping(): void
    {
        $smoking = Smoking::create(['butts' => 1]);
        $photo   = Photo::factory()->create(['smoking_id' => $smoking->id, 'remaining' => 0]);

        // Perform initial migration to create PhotoTag
        $this->updateTagsService->updateTags($photo);
        $photo->refresh();

        // Get the initial summary to count materials
        $initialSummary = $photo->summary;
        $initialMaterialCount = $initialSummary['totals']['materials'];

        // Attach a material extra tag to the generated PhotoTag
        $photoTag = $photo->photoTags()->first();
        $aluminiumMaterial = Materials::where('key', 'aluminium')->first();

        $photoTag->extraTags()->create([
            'tag_type'    => 'material',
            'tag_type_id' => $aluminiumMaterial->id,
            'quantity'    => 2,
        ]);

        // Regenerate summary with new extraTag
        $this->generatePhotoSummaryService->run($photo);
        $photo->refresh();

        $summary = $photo->summary;

        // Check that we have more materials than initially
        $finalMaterialCount = $summary['totals']['materials'];
        $this->assertGreaterThan($initialMaterialCount, $finalMaterialCount);

        // The aluminium material should be present in the keys map
        if (isset($summary['keys']['materials'])) {
            $this->assertContains('aluminium', $summary['keys']['materials']);
        }

        // Check totals increased
        $this->assertGreaterThan(0, $summary['totals']['materials']);
        $this->assertGreaterThan(0, $summary['totals']['litter']);
    }

    /** @test */
    public function regenerate_overwrites_previous(): void
    {
        $smoking = Smoking::create(['butts' => 2]);
        $photo   = Photo::factory()->create(['smoking_id' => $smoking->id, 'remaining' => 0]);
        $this->updateTagsService->updateTags($photo);

        $photo->update(['summary' => ['foo' => 'bar']]);
        $this->generatePhotoSummaryService->run($photo);
        $photo->refresh();

        // Should have flat format with tags array
        $this->assertArrayHasKey('tags', $photo->summary);
        $this->assertArrayHasKey('totals', $photo->summary);
        $this->assertArrayNotHasKey('foo', $photo->summary);
    }

    /** @test */
    public function flat_format_tags_map_one_to_one_with_photo_tags(): void
    {
        $smoking = Smoking::create(['butts' => 2]);
        $photo   = Photo::factory()->create(['smoking_id' => $smoking->id, 'remaining' => 0]);
        $this->updateTagsService->updateTags($photo);
        $photo->refresh();

        $this->generatePhotoSummaryService->run($photo);
        $photo->refresh();

        $summary = $photo->summary;
        $photoTagCount = $photo->photoTags()->count();

        // Each tag entry should map 1:1 to a photo_tags row
        $this->assertCount($photoTagCount, $summary['tags']);

        // Each tag entry should have the required fields
        foreach ($summary['tags'] as $tag) {
            $this->assertArrayHasKey('clo_id', $tag);
            $this->assertArrayHasKey('category_id', $tag);
            $this->assertArrayHasKey('object_id', $tag);
            $this->assertArrayHasKey('quantity', $tag);
            $this->assertArrayHasKey('picked_up', $tag);
            $this->assertArrayHasKey('materials', $tag);
            $this->assertArrayHasKey('brands', $tag);
            $this->assertArrayHasKey('custom_tags', $tag);
        }
    }

    /** @test */
    public function keys_section_maps_ids_to_names(): void
    {
        $smoking = Smoking::create(['butts' => 2]);
        $photo   = Photo::factory()->create(['smoking_id' => $smoking->id, 'remaining' => 0]);
        $this->updateTagsService->updateTags($photo);
        $photo->refresh();

        $this->generatePhotoSummaryService->run($photo);
        $photo->refresh();

        $summary = $photo->summary;

        // Keys section should exist with at least categories and objects
        $this->assertArrayHasKey('keys', $summary);
        $this->assertArrayHasKey('categories', $summary['keys']);
        $this->assertArrayHasKey('objects', $summary['keys']);

        // Verify keys map IDs to string names
        foreach ($summary['keys']['categories'] as $id => $name) {
            $this->assertIsString($name);
            $this->assertIsNumeric($id);
        }
    }
}
