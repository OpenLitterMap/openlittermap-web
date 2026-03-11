<?php

namespace Tests\Feature\Migration;

use App\Enums\CategoryKey;
use App\Models\Litter\Categories\Alcohol;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Categories\Food;
use App\Models\Litter\Categories\Smoking;
use App\Models\Litter\Categories\SoftDrinks;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\PhotoTag;
use App\Models\Photo;
use App\Services\Tags\UpdateTagsService;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * This is for our migration script, not for real-world tagging.
 */
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
        $this->assertTrue($photoTag->extraTags()->where('tag_type', 'custom_tag')->exists());
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

        $this->assertEquals(1, $photoTag->extraTags()->where('tag_type', 'custom_tag')->count());
    }

    /** @test */
    public function one_object_one_brand_attaches_brand_to_object(): void
    {
        $alcohol = Alcohol::create(['beerBottle' => 1]);
        $brands = Brand::create(['heineken' => 1]);

        $photo = Photo::factory()->create(['remaining' => 0]);
        $photo->update(['alcohol_id' => $alcohol->id, 'brands_id' => $brands->id]);

        $this->service->updateTags($photo);

        // 1 PhotoTag for the object
        $tags = PhotoTag::where('photo_id', $photo->id)->get();
        $this->assertCount(1, $tags);

        $beerBottleObjId = LitterObject::where('key', 'beer_bottle')->value('id');
        $this->assertEquals($beerBottleObjId, $tags->first()->litter_object_id);

        // Brand attached as extra tag
        $this->assertDatabaseHas('photo_tag_extra_tags', [
            'photo_tag_id' => $tags->first()->id,
            'tag_type'     => 'brand',
        ]);

        $heinekenId = BrandList::where('key', 'heineken')->value('id');
        $this->assertEquals($heinekenId, $tags->first()->extraTags()->where('tag_type', 'brand')->value('tag_type_id'));
    }

    /** @test */
    public function one_object_multiple_brands_skips_brand_attachment(): void
    {
        $softdrinks = SoftDrinks::create(['tinCan' => 2]);
        $brands = Brand::create(['coke' => 1, 'pepsi' => 1]);

        $photo = Photo::factory()->create(['remaining' => 0]);
        $photo->update(['softdrinks_id' => $softdrinks->id, 'brands_id' => $brands->id]);

        $this->service->updateTags($photo);

        $tags = PhotoTag::where('photo_id', $photo->id)->get();
        $this->assertCount(1, $tags);

        // Not 1:1 (1 object, 2 brands) — brands NOT attached
        $brandExtras = $tags->first()->extraTags()->where('tag_type', 'brand')->get();
        $this->assertCount(0, $brandExtras);
    }

    /** @test */
    public function multiple_objects_one_brand_skips_brand_attachment(): void
    {
        $alcohol = Alcohol::create(['beerCan' => 1, 'beerBottle' => 2]);
        $brands = Brand::create(['heineken' => 1]);

        $photo = Photo::factory()->create(['remaining' => 0]);
        $photo->update(['alcohol_id' => $alcohol->id, 'brands_id' => $brands->id]);

        $this->service->updateTags($photo);

        $tags = PhotoTag::where('photo_id', $photo->id)->get();
        $this->assertCount(2, $tags);

        // Not 1:1 (2 objects, 1 brand) — brands NOT attached
        foreach ($tags as $tag) {
            $this->assertEquals(0, $tag->extraTags()->where('tag_type', 'brand')->count());
        }
    }

    /** @test */
    public function multi_category_with_brand_skips_brand_attachment(): void
    {
        $smoking = Smoking::create(['butts' => 1]);
        $alcohol = Alcohol::create(['beerBottle' => 1]);
        $brands = Brand::create(['heineken' => 1]);

        $photo = Photo::factory()->create(['remaining' => 0]);
        $photo->update([
            'smoking_id' => $smoking->id,
            'alcohol_id' => $alcohol->id,
            'brands_id'  => $brands->id,
        ]);

        $this->service->updateTags($photo);

        $tags = PhotoTag::where('photo_id', $photo->id)->get();
        $this->assertCount(2, $tags);

        // Multi-category: brands NOT attached (ambiguous which object they belong to)
        foreach ($tags as $tag) {
            $this->assertEquals(0, $tag->extraTags()->where('tag_type', 'brand')->count());
        }
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

        // Multi-category (smoking+alcohol): brands NOT attached
        foreach ($tags as $tag) {
            $this->assertEquals(0, $tag->extraTags()->where('tag_type', 'brand')->count());
        }

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
        $this->assertTrue($photoTag->extraTags()->where('tag_type', 'custom_tag')->exists());
    }


    /** @test */
    public function update_tags_is_idempotent(): void
    {
        $smoking = Smoking::create(['butts' => 2]);
        $photo = Photo::factory()->create(['smoking_id' => $smoking->id]);

        $this->service->updateTags($photo);
        $this->service->updateTags($photo);

        $this->assertCount(1, PhotoTag::where('photo_id', $photo->id)->get());
        $this->assertEquals(2, PhotoTag::where('photo_id', $photo->id)->value('quantity'));

        // extras should also be unique
        $extraRows = DB::table('photo_tag_extra_tags')->where('photo_tag_id', PhotoTag::first()->id)->count();
        $this->assertEquals(2, $extraRows);
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
        $photo = Photo::factory()->create(['alcohol_id' => $alcohol->id]);

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

        $food = Food::create([ 'napkins' => 1 ]);
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
        $photo = Photo::factory()->create([ 'smoking_id' => $smoking->id ]);

        $this->service->updateTags($photo);
        $this->assertCount(0, PhotoTag::where('photo_id', $photo->id)->get(), 'Zero-qty tags should not migrate');
    }

    /** @test */
    public function it_idempotently_skips_already_migrated_column_based_photos()
    {
        $smoking = Smoking::create([ 'butts' => 1 ]);
        $photo = Photo::factory()->create([ 'smoking_id' => $smoking->id ]);

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

    /** @test */
    public function it_creates_one_plastic_tag_for_each_water_bottle_quantity(): void
    {
        // Ensure old category exists for v4 migration
        Category::firstOrCreate(['key' => CategoryKey::Softdrinks->value]);

        $softdrinks = Softdrinks::create([
            'waterBottle' => 2,   // two plastic items
            'tinCan'      => 1,   // one aluminium item
        ]);

        $photo = Photo::factory()->create([
            'softdrinks_id' => $softdrinks->id,
            'remaining'     => 0,
        ]);

        $this->service->updateTags($photo);

        // grab the PhotoTag for water_bottle
        $waterBottleObjId = LitterObject::where('key', 'water_bottle')->value('id');
        $waterBottleTag   = PhotoTag::where([
            'photo_id'         => $photo->id,
            'litter_object_id' => $waterBottleObjId,
        ])->first();

        // double-check the object quantity
        $this->assertEquals(2, $waterBottleTag->quantity);

        // there should be exactly ONE extra-tag row of type "material"…
        $materialExtras = $waterBottleTag
            ->extraTags()
            ->where('tag_type', 'material');

        $this->assertEquals(1, $materialExtras->count());

        // Materials are set membership — quantity is always 1
        $this->assertEquals(1, $materialExtras->value('quantity'));
    }

    /** @test */
    public function brands_only_with_custom_tags_creates_single_photo_tag(): void
    {
        $brands = Brand::create(['coke' => 1]);
        $photo = Photo::factory()->create(['brands_id' => $brands->id]);
        $photo->customTags()->create(['tag' => 'park_cleanup']);

        $this->service->updateTags($photo);

        // Should create ONE PhotoTag (brands-only), with brand + custom tag as extras
        $tags = PhotoTag::where('photo_id', $photo->id)->get();
        $this->assertCount(1, $tags);

        $photoTag = $tags->first();
        $this->assertEquals(1, $photoTag->extraTags()->where('tag_type', 'brand')->count());
        $this->assertEquals(1, $photoTag->extraTags()->where('tag_type', 'custom_tag')->count());
    }
}
