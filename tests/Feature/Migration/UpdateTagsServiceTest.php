<?php

namespace Tests\Feature\Migration;

use App\Models\Litter\Categories\Food;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Categories\Alcohol;
use App\Models\Photo;
use App\Services\Tags\UpdateTagsService;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UpdateTagsServiceTest extends TestCase
{
    use RefreshDatabase;

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
    public function it_migrates_photo_tags_and_primary_custom_tag()
    {
        $smoking = Smoking::create(['butts' => 3]);
        $photo = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'remaining' => 0,
        ]);

        $photo->customTags()->createMany([
            ['tag' => 'random_litter'],
            ['tag' => 'party_waste'],
        ]);

        $this->service->updateTags($photo);

        $this->assertDatabaseHas('photo_tags', [
            'photo_id' => $photo->id,
            'quantity' => 3,
        ]);

        foreach (['random_litter', 'party_waste'] as $customTag) {
            $this->assertDatabaseHas('custom_tags_new', ['key' => $customTag]);
        }
    }

    /** @test */
    public function it_handles_photos_with_no_legacy_tags()
    {
        $photo = Photo::factory()->create(['remaining' => 0]);

        $this->service->updateTags($photo);

        $this->assertDatabaseMissing('photo_tags', [
            'photo_id' => $photo->id,
        ]);
    }

    /** @test */
    public function it_creates_photo_tags_for_each_object()
    {
        $alcohol = Alcohol::create([
            'beerCan' => 2,
            'wineBottle' => 1,
        ]);
        $photo = Photo::factory()->create([
            'alcohol_id' => $alcohol->id,
            'remaining' => 0,
        ]);

        $this->service->updateTags($photo);

        $this->assertCount(2, PhotoTag::where('photo_id', $photo->id)->get());
    }

    /** @test */
    public function it_attaches_materials_as_extra_tags()
    {
        $alcohol = Alcohol::create(['beerBottle' => 1]);
        $photo = Photo::factory()->create([
            'alcohol_id' => $alcohol->id,
            'remaining' => 0,
        ]);

        $this->service->updateTags($photo);

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'material',
        ]);
    }

    /** @test */
    public function it_creates_single_photo_tag_when_only_custom_tag_exists()
    {
        $photo = Photo::factory()->create(['remaining' => 0]);
        $photo->customTags()->create(['tag' => 'illegal_dumping']);

        $this->service->updateTags($photo);

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertNotNull($photoTag);
        $this->assertNotNull($photoTag->custom_tag_primary_id);
        $this->assertDatabaseHas('custom_tags_new', ['key' => 'illegal_dumping']);
    }

    /** @test */
    public function it_attaches_custom_tags_as_extras_when_objects_exist()
    {
        $alcohol = Alcohol::create(['beerCan' => 1]);
        $photo = Photo::factory()->create([
            'alcohol_id' => $alcohol->id,
        ]);
        $photo->customTags()->create(['tag' => 'camping']);

        $this->service->updateTags($photo);

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();

        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $photoTag->id,
            'tag_type' => 'custom_tag',
        ]);

        $this->assertNull($photoTag->custom_tag_primary_id);
        $this->assertEquals(1, $photoTag->extraTags()->where('tag_type', 'custom_tag')->count());
    }

    public function test_one_object_one_brand_links_automatically()
    {
        $alcohol = Alcohol::create(['beerBottle' => 1]);
        $brands = Brand::create(['heineken' => 1]);

        $photo = Photo::factory()->create(['remaining' => 0]);
        $photo->alcohol_id = $alcohol->id;
        $photo->brands_id  = $brands->id;
        $photo->save();

        // Migrate
        $this->service->updateTags($photo);

        // We expect exactly 1 PhotoTag for object 'beer_bottle'
        $tags = PhotoTag::where('photo_id', $photo->id)->get();
        $this->assertCount(1, $tags);

        $beerBottleObjId = LitterObject::where('key', 'beer_bottle')->value('id');
        $this->assertEquals($beerBottleObjId, $tags->first()->litter_object_id);

        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $tags->first()->id,
            'tag_type'     => 'brand',
        ]);
    }

    /**
     * 1) Combine old "smoking" + "alcohol" keys:
     *    - smoking => 'cigaretteBox' = 2
     *    - alcohol => 'beerBottle' = 3
     *    - brand within alcohol => 'heineken' = 1 (some code bases store brands in the same table)
     */
    public function test_migrates_smoking_and_alcohol_tags_in_one_photo()
    {
        // Create a Smoking category row with old keys
        $smoking = Smoking::create([
            'cigaretteBox' => 2,
        ]);

        $alcohol = Alcohol::create([
            'beerBottle' => 3,
        ]);

        $brand = Brand::create([
            'heineken' => 1,
        ]);

        // Create a Photo and link it to these categories
        $photo = Photo::factory()->create(['remaining' => 0]);
        $photo->smoking_id = $smoking->id;
        $photo->alcohol_id = $alcohol->id;
        $photo->brands_id  = $brand->id;
        $photo->save();

        // Add a custom tag for variety
        $photo->customTags()->create(['tag' => 'festival_cleanup']);

        // Migrate
        $this->service->updateTags($photo);

        // We expect to see two objects (cigarette_box + beer_bottle) in photo_tags
        $tags = PhotoTag::where('photo_id', $photo->id)->get();
        $this->assertCount(2, $tags);

        $cigaretteBoxId = LitterObject::where('key', 'cigarette_box')->value('id');
        $beerBottleId   = LitterObject::where('key', 'beer_bottle')->value('id');

        // Check "cigarette_box"
        $cigBoxTag = $tags->firstWhere('litter_object_id', $cigaretteBoxId);
        $this->assertNotNull($cigBoxTag, "Expected a PhotoTag for 'cigaretteBox' object.");
        $this->assertEquals(2, $cigBoxTag->quantity);

        // Check "beer_bottle"
        $beerBottleTag = $tags->firstWhere('litter_object_id', $beerBottleId);
        $this->assertNotNull($beerBottleTag, "Expected a PhotoTag for 'beerBottle' object.");
        $this->assertEquals(3, $beerBottleTag->quantity);

        // Check brand
        // \Log::info($tags->toArray());
//        $beerBottles = $beerBottleTag->extraTags()->where('tag_type', 'brand')->get();
//        $this->assertCount(1, $beerBottles);

        $this->assertDatabaseHas('custom_tags_new', ['key' => 'festival_cleanup']);
    }

    /** @test */
    public function primary_custom_tag_is_created_when_only_custom_tags_exist(): void
    {
        $photo = Photo::factory()->create(['remaining' => 0]);
        $photo->customTags()->createMany([['tag' => 'illegal_dumping']]);

        $this->service->updateTags($photo);

        $photoTag = PhotoTag::where('photo_id', $photo->id)->first();
        $this->assertNotNull($photoTag);
        $this->assertNotNull($photoTag->custom_tag_primary_id);
        $this->assertEquals(0, $photoTag->extraTags()->count());
    }

    /** @test */
    public function one_object_one_brand_creates_pivot_and_brand_extra(): void
    {
        $alcohol = Alcohol::create(['beerBottle' => 1]);
        $brands  = Brand::create(['heineken'    => 1]);

        $photo = Photo::factory()->create();
        $photo->update(['alcohol_id' => $alcohol->id, 'brands_id' => $brands->id]);

        // migrate
        $this->service->updateTags($photo);

        //   1) one photo_tag for beerBottle
        $tag = PhotoTag::where('photo_id', $photo->id)->first();
        $this->assertEquals(
            LitterObject::where('key', 'beer_bottle')->value('id'),
            $tag->litter_object_id
        );

        //   2) brand extra‑tag exists
        $this->assertEquals(1, $tag->extraTags()->where('tag_type', 'brand')->count());

        //   3) pivot exists in category_object.taggables
        $catObjId = CategoryObject::where('litter_object_id', $tag->litter_object_id)->value('id');
        $this->assertDatabaseHas('taggables', [
            'category_litter_object_id' => $catObjId,
            'taggable_type'             => BrandList::class,
            'taggable_id'               => BrandList::where('key', 'heineken')->value('id'),
        ]);
    }

    /** @test */
    public function multiple_objects_and_brands_reuse_only_existing_pivots(): void
    {
        $alcohol  = Alcohol::create(['beerBottle' => 1]);
        $smoking  = Smoking::create(['cigaretteBox' => 1]);
        $brandsRow = Brand::create(['heineken' => 1, 'marlboro' => 1]);

        // create ONE historical pivot: beerBottle ⇄ heineken
        $beerBottleId   = LitterObject::where('key', 'beer_bottle')->value('id');
        $heinekenId     = BrandList::where('key', 'heineken')->value('id');
        $catAlcoholId   = CategoryObject::firstOrCreate([
            'category_id'      => Category::where('key', 'alcohol')->value('id'),
            'litter_object_id' => $beerBottleId,
        ])->id;

        DB::table('taggables')->insert([
            'category_litter_object_id' => $catAlcoholId,
            'taggable_type'             => BrandList::class,
            'taggable_id'               => $heinekenId,
            'quantity'                  => 1,
        ]);

        $photo = Photo::factory()->create();
        $photo->update([
            'alcohol_id' => $alcohol->id,  // beerBottle
            'smoking_id' => $smoking->id,  // cigaretteBox
            'brands_id'  => $brandsRow->id,
        ]);

        $this->service->updateTags($photo);

        // beerBottle should have ONE brand extra (heineken)
        $beerTag = $photo->photoTags()
            ->where('litter_object_id', $beerBottleId)
            ->first();

        $this->assertEquals(
            1,
            $beerTag->extraTags()->where('tag_type', 'brand')->count(),
            'Historical beerBottle ⇄ heineken pivot should be reused.'
        );

        $cigaretteBoxId = LitterObject::where('key', 'cigarette_box')->value('id');

        $cigTag = $photo->photoTags()
            ->where('litter_object_id', $cigaretteBoxId)
            ->first();

        $this->assertEquals(0, $cigTag->extraTags()->where('tag_type', 'brand')->count());
    }

    /** @test */
    public function update_tags_is_idempotent(): void
    {
        $smoking = Smoking::create(['butts' => 2]);
        $photo   = Photo::factory()->create(['smoking_id' => $smoking->id]);

        $this->service->updateTags($photo);
        $this->service->updateTags($photo);   // second run

        $this->assertCount(1, PhotoTag::where('photo_id', $photo->id)->get());
        $this->assertEquals(
            2,
            PhotoTag::where('photo_id', $photo->id)->value('quantity')
        );

        // extras should also be unique
        $extraRows = DB::table('photo_tag_extra_tags')->where('photo_tag_id', PhotoTag::first()->id)->count();
        $this->assertEquals(0, $extraRows);
    }

    /** @test */
    public function photo_with_only_brands_creates_brand_only_tag(): void
    {
        $brandRow = Brand::create(['coke'=>1, 'pepsi'=>1]);
        $photo = Photo::factory()->create(['brands_id'=>$brandRow->id]);

        $this->service->updateTags($photo);

        $this->assertCount(1, PhotoTag::where('photo_id',$photo->id)->get());
        $this->assertEquals(2, PhotoTag::first()->extraTags()->where('tag_type','brand')->count());
    }

    /** @test */
    public function photo_migrated_at_is_updated(): void
    {
        $photo = Photo::factory()->create(['remaining' => 0]);

        $this->service->updateTags($photo);

        $this->assertNotNull($photo->fresh()->migrated_at);
    }

    /** @test */
    public function empty_brand_or_object_blocks_do_not_throw(): void
    {
        $alcohol = Alcohol::create([]);
        $photo   = Photo::factory()->create(['alcohol_id' => $alcohol->id]);

        $this->service->updateTags($photo);

        // still zero photo_tags
        $this->assertDatabaseCount('photo_tags', 0);
    }

    /** @test */
    public function it_migrates_smoking_column_tags_to_photo_tags()
    {
        // Create legacy Smoking record with two tag counts
        $smoking = Smoking::create([
            'butts'         => 2,
            'cigaretteBox'  => 3,
            'lighters'      => 0,    // zero should be ignored
        ]);

        // Attach to photo
        $photo = Photo::factory()->create([ 'smoking_id' => $smoking->id ]);

        // Run migration
        $this->service->updateTags($photo);

        // Refresh and fetch tags
        $photo->refresh();
        $tags = PhotoTag::where('photo_id', $photo->id)->get();

        // Expect exactly two tags: butts (2), cigaretteBox (3)
        $this->assertCount(2, $tags);

        $buttsTag = $tags->firstWhere('litter_object_id', LitterObject::where('key', 'butts')->value('id'));
        $cigBoxTag = $tags->firstWhere('litter_object_id', LitterObject::where('key', 'cigarette_box')->value('id'));

        $this->assertNotNull($buttsTag);
        $this->assertEquals(2, $buttsTag->quantity);

        $this->assertNotNull($cigBoxTag);
        $this->assertEquals(3, $cigBoxTag->quantity);
    }

    /** @test */
    public function it_handles_multiple_column_categories_on_same_photo()
    {
        // Setup additional category: Food with 'napkins'
        LitterObject::firstOrCreate(['key' => 'napkins']);

        $food      = Food::create([ 'napkins' => 1 ]);
        $smoking = Smoking::create([ 'butts' => 1 ]);

        // Photo with both smoking_id and food_id set
        $photo = Photo::factory()->create([
            'smoking_id' => $smoking->id,
            'food_id'    => $food->id,
        ]);

        $this->service->updateTags($photo);

        $tags = PhotoTag::where('photo_id', $photo->id)->get();
        // Expect two tags: butts and napkins
        $this->assertCount(2, $tags);

        $keys = $tags->map(fn($t) => LitterObject::find($t->litter_object_id)->key)->sort()->values();
        $this->assertEquals(['butts','napkins'], $keys->all());
    }

    /** @test */
    public function it_ignores_zero_quantities_when_migrating_columns()
    {
        $smoking = Smoking::create([ 'butts' => 0, 'cigaretteBox' => 0 ]);
        $photo   = Photo::factory()->create([ 'smoking_id' => $smoking->id ]);

        $this->service->updateTags($photo);
        $this->assertCount(0, PhotoTag::where('photo_id', $photo->id)->get(), 'Zero-qty tags should not migrate');
    }

    /** @test */
    public function it_idempotently_skips_already_migrated_column_based_photos()
    {
        $smoking = Smoking::create([ 'butts' => 1 ]);
        $photo   = Photo::factory()->create([ 'smoking_id' => $smoking->id ]);

        // First migration
        $this->service->updateTags($photo);
        $this->assertCount(1, PhotoTag::where('photo_id', $photo->id)->get());

        // Change legacy data to see if second run tries to re-migrate
        $smoking->update([ 'butts' => 5 ]);

        // Second migration should detect migrated_at and skip
        $this->service->updateTags($photo);
        $this->assertCount(1, PhotoTag::where('photo_id', $photo->id)->get(), 'Should not duplicate tags');

        // Quantity remains original (1), not updated to 5
        $this->assertEquals(1, PhotoTag::where('photo_id', $photo->id)->value('quantity'));
    }
}
