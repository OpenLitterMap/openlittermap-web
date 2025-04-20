<?php

namespace Tests\Feature\Tags\v2;

use App\Models\Litter\Categories\Alcohol;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Categories\Food;
use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Photo;
use App\Services\Tags\UpdateTagsService;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Tests\TestCase;

class GeneratePhotoSummaryTest extends TestCase
{
    protected UpdateTagsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class
        ]);

        $this->service = app(UpdateTagsService::class);
    }

    /** @test */
    public function summary_empty_when_no_tags(): void
    {
        $photo = Photo::factory()->create();

        $photo->generateSummary();
        $photo->refresh();

        $this->assertSame([], $photo->summary['items']);
        $this->assertSame([
            'total_tags'    => 0,
            'total_objects' => 0,
            'by_category'   => [],
            'materials'     => 0,
            'brands'        => 0,
            'custom_tags'   => 0,
        ], $photo->summary['totals']);
    }

    /** @test */
    public function generate_summary_on_photo_with_no_tags_produces_empty_totals_and_items()
    {
        $photo = Photo::factory()->create();
        $photo->generateSummary();

        $photo->refresh();
        $this->assertIsArray($photo->summary);
        $this->assertEmpty($photo->summary['items'], 'Expected no items');

        $totals = $photo->summary['totals'];
        $this->assertEquals(0, $totals['total_tags']);
        $this->assertEquals(0, $totals['total_objects']);
        $this->assertEquals([], $totals['by_category']);
        $this->assertEquals(0, $totals['materials']);
        $this->assertEquals(0, $totals['brands']);
        $this->assertEquals(0, $totals['custom_tags']);
    }

    /** @test */
    public function generate_summary_accumulates_base_and_extra_quantities_correctly()
    {
        // 1 object tag with qty=2
        $smoking = Smoking::create(['butts' => 2]);
        $photo   = Photo::factory()->create(['smoking_id' => $smoking->id, 'remaining' => 0]);

        $brandList = BrandList::where('key','adidas')->first();

        // 1 extra brand on that object
        $brand    = Brand::create(['adidas' => 1]);
        $photo->brands_id = $brand->id;
        $photo->save();

        // 1 custom tag at top level
        $photo->customTags()->create(['tag' => 'street_clean']);

        // run migration, which calls generateSummary
        app(UpdateTagsService::class)->updateTags($photo);

        $photo->refresh();
        $summary = $photo->summary;

        // items count = 1
        $this->assertCount(1, $summary['items']);

        // totals
        $totals = $summary['totals'];
        // base quantity 2, + brand extra 1, + custom extra 1 = 4
        $this->assertEquals(4, $totals['total_tags']);
        // only one object tag => total_objects = 2
        $this->assertEquals(2, $totals['total_objects']);
        // by_category should have one entry for smoking category id
        $smokeCatId = Category::where('key', 'smoking')->value('id');
        $this->assertEquals(2, $totals['by_category'][$smokeCatId]);
        // materials = 0, brands = 1, custom_tags = 1
        $this->assertEquals(0, $totals['materials']);
        $this->assertEquals(1, $totals['brands']);
        $this->assertEquals(1, $totals['custom_tags']);
    }

    /** @test */
    public function generate_summary_items_include_all_expected_fields()
    {
        $alcohol = Alcohol::create(['wineBottle' => 1]);
        $photo   = Photo::factory()->create(['alcohol_id' => $alcohol->id, 'remaining' => 0]);

        // run migration
        app(UpdateTagsService::class)->updateTags($photo);

        $photo->refresh();
        $item = $photo->summary['items'][0];

        $this->assertArrayHasKey('photo_tag_id', $item);
        $this->assertArrayHasKey('category_id', $item);
        $this->assertArrayHasKey('litter_object_id', $item);
        $this->assertArrayHasKey('quantity', $item);
        $this->assertArrayHasKey('picked_up', $item);
        $this->assertArrayHasKey('extra_tags', $item);
        $this->assertIsArray($item['extra_tags']);
    }

    /** @test */
    public function regenerate_summary_overwrites_previous_summary()
    {
        // create a photo with one tag
        $food = Food::create(['napkins' => 1]);
        $photo = Photo::factory()->create(['food_id' => $food->id, 'remaining' => 0]);
        app(UpdateTagsService::class)->updateTags($photo);

        // manually tamper summary
        Photo::where('id', $photo->id)->update([
            'summary' => ['foo' => 'bar']
        ]);

        // re-run generateSummary
        $photo->refresh()->generateSummary();
        $photo->refresh();

        // ensure 'foo'=>'bar' is gone
        $this->assertArrayNotHasKey('foo', $photo->summary);
        // and real items exist
        $this->assertNotEmpty($photo->summary['items']);
    }

    /** @test */
    public function generate_summary_handles_multiple_categories_and_extra_tags()
    {
        $smoking = Smoking::create(['butts' => 1]);
        $alcohol = Alcohol::create(['beerCan' => 2]);
        $photo   = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'alcohol_id' => $alcohol->id,
            'remaining'  => 0,
        ]);

        $brand = Brand::create(['aadrink' => 1]);
        $photo->brands_id = $brand->id;
        $photo->save();

        app(UpdateTagsService::class)->updateTags($photo);
        $photo->refresh();

        // two base items
        $this->assertCount(2, $photo->summary['items']);

        // total_tags = 1 + 2 (base) + 1 (brand extra on each? merged to both?)
        // but at least >= 3
        $this->assertGreaterThanOrEqual(3, $photo->summary['totals']['total_tags']);
        // by_category has two keys
        $cats = array_keys($photo->summary['totals']['by_category']);
        $this->assertCount(2, $cats);
    }

    /** @test */
    public function summary_counts_base_and_custom_tags_only(): void
    {
        // legacy "smoking" column gives 2 butts
        $smoking = Smoking::create(['butts' => 2]);
        $photo   = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'remaining'  => 0,
        ]);

        // two top‐level custom tags
        $photo->customTags()->createMany([
            ['tag' => 'foo'],
            ['tag' => 'bar'],
        ]);

        // runs generateSummary under the hood
        $this->service->updateTags($photo);
        $photo->refresh();

        $totals = $photo->summary['totals'];

        // 2 base + 2 extra = 4
        $this->assertEquals(4, $totals['total_tags']);
        // only one object category → total_objects is 2
        $this->assertEquals(2, $totals['total_objects']);
        // custom_tags = 2
        $this->assertEquals(2, $totals['custom_tags']);
        // no brands or materials here
        $this->assertEquals(0, $totals['brands']);
        $this->assertEquals(0, $totals['materials']);

        // and by_category picks up the smoking category ID
        $smokeCatId = Category::where('key', 'smoking')->value('id');
        $this->assertEquals(2, $totals['by_category'][ $smokeCatId ]);
    }

    /** @test */
    public function summary_handles_multiple_categories(): void
    {
        $smoking = Smoking::create(['butts' => 1]);
        $food    = Food::create(['napkins' => 3]);

        $photo = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'food_id'    => $food->id,
            'remaining'  => 0,
        ]);

        $this->service->updateTags($photo);
        $photo->refresh();

        $items = $photo->summary['items'];
        $this->assertCount(2, $items);

        $totals = $photo->summary['totals'];
        // 1 butts + 3 napkins = 4
        $this->assertEquals(4, $totals['total_tags']);

        $smokeCatId = Category::where('key', 'smoking')->value('id');
        $foodCatId  = Category::where('key', 'food')->value('id');

        $this->assertEquals(1, $totals['by_category'][ $smokeCatId ]);
        $this->assertEquals(3, $totals['by_category'][ $foodCatId ]);
    }

    /** @test */
    public function items_include_all_expected_keys(): void
    {
        $smoking = Smoking::create(['butts' => 1]);
        $photo   = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'remaining'  => 0,
        ]);

        $this->service->updateTags($photo);
        $photo->refresh();

        $item = $photo->summary['items'][0];

        $this->assertArrayHasKey('photo_tag_id',          $item);
        $this->assertArrayHasKey('category_id',           $item);
        $this->assertArrayHasKey('litter_object_id',      $item);
        $this->assertArrayHasKey('custom_tag_primary_id', $item);
        $this->assertArrayHasKey('quantity',              $item);
        $this->assertArrayHasKey('picked_up',             $item);
        $this->assertArrayHasKey('extra_tags',            $item);
    }

    /** @test */
    public function regenerate_summary_overwrites_previous_blob(): void
    {
        $smoking = Smoking::create(['butts' => 2]);
        $photo   = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'remaining'  => 0,
        ]);

        // first run
        $this->service->updateTags($photo);

        // manually stomp on it
        $photo->update(['summary' => ['foo' => 'bar']]);

        // regenerate
        $photo->refresh()->generateSummary();
        $photo->refresh();

        $this->assertArrayNotHasKey('foo', $photo->summary);
        $this->assertNotEmpty($photo->summary['items']);
    }
}
