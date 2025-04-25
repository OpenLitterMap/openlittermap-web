<?php

namespace Tests\Feature\Tags\v2;

use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Categories\Food;
use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Tags\Materials;
use App\Models\Photo;
use App\Services\Photos\GeneratePhotoSummaryService;
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

        $totals = $summary['totals'];
        $this->assertEquals(4, $totals['total_tags']);
        $this->assertEquals(2, $totals['total_objects']);
        $this->assertEquals(0, $totals['materials']);
        $this->assertEquals(1, $totals['brands']);
        $this->assertEquals(1, $totals['custom_tags']);

        $this->assertArrayHasKey('smoking', $totals['by_category']);
        $this->assertEquals(4, $totals['by_category']['smoking']);

        $this->assertArrayHasKey('smoking', $summary['tags']);
        $objects = $summary['tags']['smoking'];
        $this->assertCount(1, $objects);
        $entry = reset($objects);
        $this->assertEquals(2, $entry['quantity']);
        $this->assertEquals(['adidas' => 1], $entry['brands']);
        $this->assertEquals([], $entry['materials']);
        $this->assertEquals(['street_clean' => 1], $entry['custom_tags']);
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

        $categories = array_keys($summary['tags']);
        $this->assertEquals(['food', 'smoking'], $categories);

        $this->assertEquals(3, $summary['totals']['by_category']['food']);
        $this->assertEquals(1, $summary['totals']['by_category']['smoking']);
    }

    /** @test */
    public function it_includes_material_extra_in_grouping(): void
    {
        $smoking = Smoking::create(['butts' => 1]);
        $photo   = Photo::factory()->create(['smoking_id' => $smoking->id, 'remaining' => 0]);

        // Perform initial migration to create PhotoTag
        $this->updateTagsService->updateTags($photo);
        $photo->refresh();

        // Attach a material extra tag to the generated PhotoTag
        $photoTag = $photo->photoTags()->first();
        $materialList = Materials::first();
        $photoTag->extraTags()->create([
            'tag_type'    => 'material',
            'tag_type_id' => $materialList->id,
            'quantity'    => 2,
        ]);

        // Regenerate summary with new extraTag
        $this->generatePhotoSummaryService->run($photo);
        $photo->refresh();

        $summary = $photo->summary;
        $this->assertArrayHasKey('smoking', $summary['tags']);
        $objects = $summary['tags']['smoking'];
        $entry = reset($objects);

        // Material grouping should include our extra
        $this->assertEquals([$materialList->key => 2], $entry['materials']);
        // Totals
        $this->assertEquals(2, $summary['totals']['materials']);
        $this->assertEquals(3, $summary['totals']['total_tags']);
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

        $this->assertArrayHasKey('smoking', $photo->summary['tags']);
        $this->assertArrayNotHasKey('foo', $photo->summary);
    }
}
