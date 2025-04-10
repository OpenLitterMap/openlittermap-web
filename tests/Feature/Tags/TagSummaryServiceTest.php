<?php

namespace Tests\Feature\Tags;

use App\Models\Photo;
use App\Models\User\User;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTagExtraTags;
use App\Services\Tags\TagSummaryService;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TagSummaryService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->seed(GenerateTagsSeeder::class);

        $this->service = new TagSummaryService();
    }

    /** @test */
    public function it_generates_basic_summary_with_object_only()
    {
        $photo = Photo::factory()->create();
        $category = Category::firstWhere('key', 'alcohol');
        $object = LitterObject::firstWhere('key', 'beer_can');

        PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'quantity' => 2,
        ]);

        $this->service->generateTagSummary($photo);
        $summary = $photo->fresh()->summary;

        $this->assertEquals(2, $summary['totals']['tags']);
        $this->assertEquals(2, $summary['totals']['objects']);
        $this->assertEquals(2, $summary['totals']['categories'][$category->key][$object->key]);
    }

    /** @test */
    public function it_counts_brands_and_materials_correctly()
    {
        $photo = Photo::factory()->create();
        $category = Category::firstWhere('key', 'softdrinks');
        $object = LitterObject::firstWhere('key', 'soda_can');

        $tag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'quantity' => 1,
        ]);

        PhotoTagExtraTags::insert([
            [
                'photo_tag_id' => $tag->id,
                'tag_type' => 'brand',
                'tag_type_id' => 101,
                'quantity' => 1,
            ],
            [
                'photo_tag_id' => $tag->id,
                'tag_type' => 'material',
                'tag_type_id' => 202,
                'quantity' => 1,
            ],
        ]);

        $this->service->generateTagSummary($photo);
        $summary = $photo->fresh()->summary;

        $this->assertEquals(1, $summary['totals']['brands']);
        $this->assertEquals(1, $summary['totals']['materials']);
        $this->assertEquals(1, $summary['totals']['categories'][$category->key]['brands'][101]);
        $this->assertEquals(1, $summary['totals']['categories'][$category->key]['materials'][202]);
    }

    /** @test */
    public function it_handles_custom_tags_without_objects()
    {
        $photo = Photo::factory()->create();

        $photoTag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => null,
            'litter_object_id' => null,
            'quantity' => 1,
        ]);

        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'custom',
            'tag_type_id' => 555,
            'quantity' => 2,
        ]);

        PhotoTagExtraTags::create([
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'custom',
            'tag_type_id' => 555,
            'quantity' => 2,
        ]);

        $this->service->generateTagSummary($photo);
        $summary = $photo->fresh()->summary;

        $this->assertEquals(5, $summary['totals']['tags']);                 // 1 base tag + 2 + 2 custom quantities
        $this->assertEquals(8, $summary['totals']['custom_tags']);          // 2 + 2 counted in 2 places
        $this->assertEquals(4, $summary['totals']['categories']['uncategorized']['custom_tags'][555]);
        $this->assertEquals(4, $summary['totals']['categories']['custom_tags'][555]);

    }

    /** @test */
    public function it_populates_metadata_correctly()
    {
        $photo = Photo::factory()->create([
            'user_id' => User::factory()->create()->id,
            'datetime' => now(),
            'remaining' => false,
        ]);

        $this->service->generateTagSummary($photo);
        $summary = $photo->fresh()->summary;

        $this->assertEquals($photo->id, $summary['metadata']['photo_id']);
        $this->assertEquals($photo->user_id, $summary['metadata']['user_id']);
        $this->assertEquals(!$photo->remaining, $summary['metadata']['picked_up']);
    }

    /** @test */
    public function it_handles_multiple_tags_in_one_category()
    {
        $photo = Photo::factory()->create();
        $category = Category::firstWhere('key', 'alcohol');
        $can = LitterObject::firstWhere('key', 'beer_can');
        $bottle = LitterObject::firstWhere('key', 'wine_bottle');

        PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $category->id, 'litter_object_id' => $can->id, 'quantity' => 2]);
        PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $category->id, 'litter_object_id' => $bottle->id, 'quantity' => 1]);

        $this->service->generateTagSummary($photo);
        $summary = $photo->fresh()->summary;

        $this->assertEquals(3, $summary['totals']['tags']);
        $this->assertEquals(3, $summary['totals']['objects']);
        $this->assertEquals(2, $summary['totals']['categories']['alcohol']['beer_can']);
        $this->assertEquals(1, $summary['totals']['categories']['alcohol']['wine_bottle']);
    }

    /** @test */
    public function it_handles_multiple_categories()
    {
        $photo = Photo::factory()->create();
        $alcohol = Category::firstWhere('key', 'alcohol');
        $smoking = Category::firstWhere('key', 'smoking');
        $beer = LitterObject::firstWhere('key', 'beer_can');
        $butts = LitterObject::firstWhere('key', 'butts');

        PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $alcohol->id, 'litter_object_id' => $beer->id, 'quantity' => 1]);
        PhotoTag::create(['photo_id' => $photo->id, 'category_id' => $smoking->id, 'litter_object_id' => $butts->id, 'quantity' => 2]);

        $this->service->generateTagSummary($photo);
        $summary = $photo->fresh()->summary;

        $this->assertEquals(3, $summary['totals']['tags']);
        $this->assertEquals(1, $summary['totals']['categories']['alcohol']['beer_can']);
        $this->assertEquals(2, $summary['totals']['categories']['smoking']['butts']);
    }

    /** @test */
    public function it_handles_empty_photo_summary()
    {
        $photo = Photo::factory()->create();

        $this->service->generateTagSummary($photo);
        $summary = $photo->fresh()->summary;

        $this->assertEquals(0, $summary['totals']['tags']);
        $this->assertEquals(0, $summary['totals']['objects']);
        $this->assertEmpty($summary['totals']['categories']);
    }

    /** @test */
    public function it_handles_duplicate_extras_with_different_indexes()
    {
        $photo = Photo::factory()->create();
        $category = Category::firstWhere('key', 'alcohol');
        $object = LitterObject::firstWhere('key', 'beer_can');

        $tag = PhotoTag::create([
            'photo_id' => $photo->id,
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
            'quantity' => 1,
        ]);

        PhotoTagExtraTags::create(['photo_tag_id' => $tag->id, 'tag_type' => 'brand', 'tag_type_id' => 999, 'quantity' => 1, 'index' => 0]);
        PhotoTagExtraTags::create(['photo_tag_id' => $tag->id, 'tag_type' => 'brand', 'tag_type_id' => 999, 'quantity' => 2, 'index' => 1]);

        $this->service->generateTagSummary($photo);
        $summary = $photo->fresh()->summary;

        $this->assertEquals(3, $summary['totals']['brands']);
        $this->assertEquals(3, $summary['totals']['categories']['alcohol']['brands'][999]);
    }
}
