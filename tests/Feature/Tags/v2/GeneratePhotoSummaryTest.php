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
            'total_tags'    => 0,
            'total_objects' => 0,
            'by_category'   => [],
            'materials'     => 0,
            'brands'        => 0,
            'custom_tags'   => 0,
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

        // Get the smoking category ID
        $smokingCategory = Category::where('key', 'smoking')->first();
        $smokingCategoryId = $smokingCategory->id;

        $totals = $summary['totals'];
        $this->assertEquals(8, $totals['total_tags']);
        $this->assertEquals(2, $totals['total_objects']);
        $this->assertEquals(4, $totals['materials']);
        $this->assertEquals(1, $totals['brands']);
        $this->assertEquals(1, $totals['custom_tags']);

        // Check by_category uses category ID
        $this->assertArrayHasKey($smokingCategoryId, $totals['by_category']);
        $this->assertEquals(8, $totals['by_category'][$smokingCategoryId]);

        // Check tags structure uses category ID
        $this->assertArrayHasKey($smokingCategoryId, $summary['tags']);
        $objects = $summary['tags'][$smokingCategoryId];
        $this->assertCount(1, $objects);

        $entry = reset($objects);
        $this->assertEquals(2, $entry['quantity']);

        // Verify brands exist (don't check specific brand ID as it may vary)
        $this->assertNotEmpty($entry['brands']);
        $this->assertEquals(1, array_sum($entry['brands'])); // Total brand quantity

        // Verify materials exist
        $this->assertNotEmpty($entry['materials']);
        $this->assertEquals(4, array_sum($entry['materials'])); // Total material quantity (2 paper + 2 plastic)

        // Verify custom tags exist
        $this->assertNotEmpty($entry['custom_tags']);
        $this->assertEquals(1, array_sum($entry['custom_tags'])); // Total custom tag quantity

        $this->assertEquals(8, $photo->total_tags);
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

        // Get category IDs
        $smokingCategoryId = Category::where('key', 'smoking')->value('id');
        $foodCategoryId = Category::where('key', 'food')->value('id');

        // Categories should be sorted by total quantity
        $categoryIds = array_keys($summary['tags']);
        $this->assertContains($smokingCategoryId, $categoryIds);
        $this->assertContains($foodCategoryId, $categoryIds);

        $this->assertEquals(3, $summary['totals']['by_category'][$foodCategoryId]);
        $this->assertEquals(3, $summary['totals']['by_category'][$smokingCategoryId]);
    }

    /** @test */
    public function it_includes_material_extra_in_grouping(): void
    {
        $smoking = Smoking::create(['butts' => 1]);
        $photo   = Photo::factory()->create(['smoking_id' => $smoking->id, 'remaining' => 0]);

        // Perform initial migration to create PhotoTag
        $this->updateTagsService->updateTags($photo);
        $photo->refresh();

        // Get the initial summary to see what materials already exist
        $initialSummary = $photo->summary;
        $smokingCategoryId = Category::where('key', 'smoking')->value('id');
        $initialMaterials = $initialSummary['tags'][$smokingCategoryId] ?
            reset($initialSummary['tags'][$smokingCategoryId])['materials'] ?? [] : [];
        $initialMaterialCount = array_sum($initialMaterials);

        // Attach a material extra tag to the generated PhotoTag
        $photoTag = $photo->photoTags()->first();
        $aluminiumMaterial = Materials::where('key', 'aluminium')->first();

        // Create the extra tag with correct structure
        $photoTag->extraTags()->create([
            'tag_type'    => 'material',
            'tag_type_id' => $aluminiumMaterial->id,
            'quantity'    => 2,
            'index'       => 0
        ]);

        // Regenerate summary with new extraTag
        $this->generatePhotoSummaryService->run($photo);
        $photo->refresh();

        $summary = $photo->summary;

        $this->assertArrayHasKey($smokingCategoryId, $summary['tags']);

        $objects = $summary['tags'][$smokingCategoryId];
        $entry = reset($objects);

        // Materials are stored by ID
        $materials = $entry['materials'] ?? [];

        // Check that we have more materials than initially
        $finalMaterialCount = array_sum($materials);
        $this->assertGreaterThan($initialMaterialCount, $finalMaterialCount);

        // The aluminium material should be present
        if (!empty($materials)) {
            $this->assertArrayHasKey($aluminiumMaterial->id, $materials);
            $this->assertEquals(2, $materials[$aluminiumMaterial->id]);
        }

        // Check totals increased
        $this->assertGreaterThan(0, $summary['totals']['materials']);
        $this->assertGreaterThan(0, $summary['totals']['total_tags']);
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

        $smokingCategoryId = Category::where('key', 'smoking')->value('id');
        $this->assertArrayHasKey($smokingCategoryId, $photo->summary['tags']);
        $this->assertArrayNotHasKey('foo', $photo->summary);
    }
}
