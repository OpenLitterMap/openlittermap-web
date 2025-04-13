<?php

namespace Tests\Feature\Migration;

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
use Tests\TestCase;

class UpdateTagsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UpdateTagsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GenerateTagsSeeder::class);
        $this->seed(GenerateBrandsSeeder::class);
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
    }

    public function test_one_object_one_brand_links_automatically()
    {
        $alcohol = Alcohol::create([
            'beerBottle' => 1,
        ]);

        $brands = Brand::create([
            'heineken' => 1,
        ]);

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
        $photoTag = $tags->first();
        $this->assertEquals($beerBottleObjId, $photoTag->litter_object_id);

        $brandExtras = $photoTag->extraTags()->where('tag_type', 'brand')->get();
        $this->assertCount(1, $brandExtras, "Expected brand 'heineken' to be linked to 'beer_bottle'.");
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
}
