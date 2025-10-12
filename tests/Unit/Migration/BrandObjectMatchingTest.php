<?php

namespace Tests\Unit\Migration;

use App\Models\Litter\Categories\Alcohol;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Categories\Coffee;
use App\Models\Litter\Categories\Food;
use App\Models\Litter\Categories\SoftDrinks;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Litter\Tags\Taggable;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Tags\UpdateTagsService;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandObjectMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected UpdateTagsService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class
        ]);

        $this->service = app(UpdateTagsService::class);
        $this->user = User::factory()->create();
    }

    /**
     * Create brand-object relationships using the actual database structure
     */
    protected function createBrandObjectRelationship(string $categoryKey, string $objectKey, string $brandKey): void
    {
        // Get the category, object, and brand from the database
        $category = Category::where('key', $categoryKey)->first();
        $object = LitterObject::where('key', $objectKey)->first();
        $brand = BrandList::where('key', $brandKey)->first();

        if (!$category || !$object || !$brand) {
            $missingItems = [];
            if (!$category) $missingItems[] = "category: {$categoryKey}";
            if (!$object) $missingItems[] = "object: {$objectKey}";
            if (!$brand) $missingItems[] = "brand: {$brandKey}";

            $this->fail("Could not find: " . implode(', ', $missingItems));
        }

        // Create or get the CategoryObject pivot
        $categoryObject = CategoryObject::firstOrCreate([
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
        ]);

        // Create the taggable relationship
        Taggable::firstOrCreate([
            'category_litter_object_id' => $categoryObject->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $brand->id,
        ], [
            'quantity' => 1,
        ]);
    }

    /**
     * Create a photo with v4 format tags using the old category models
     * This simulates what photos look like before migration
     */
    protected function createPhotoWithOldTags(array $tags): Photo
    {
        $photo = Photo::factory()->create(['user_id' => $this->user->id]);

        // For each category, create the old-style records
        foreach ($tags as $category => $items) {
            switch ($category) {
                case 'softdrinks':
                    // Map v5 keys to v4 column names for SoftDrinks
                    $v4Items = [];
                    foreach ($items as $key => $quantity) {
                        $v4Key = match($key) {
                            'soda_can' => 'tinCan',
                            'water_bottle' => 'waterBottle',
                            'fizzy_bottle' => 'fizzyDrinkBottle',
                            default => $key
                        };
                        $v4Items[$v4Key] = $quantity;
                    }
                    $record = SoftDrinks::create($v4Items);
                    $photo->softdrinks_id = $record->id;
                    break;

                case 'alcohol':
                    // Map v5 keys to v4 column names for Alcohol
                    $v4Items = [];
                    foreach ($items as $key => $quantity) {
                        $v4Key = match($key) {
                            'beer_bottle' => 'beerBottle',
                            'beer_can' => 'beerCan',
                            'spirits_bottle' => 'spiritBottle',
                            'wine_bottle' => 'wineBottle',
                            default => $key
                        };
                        $v4Items[$v4Key] = $quantity;
                    }
                    $record = Alcohol::create($v4Items);
                    $photo->alcohol_id = $record->id;
                    break;

                case 'coffee':
                    // Map v5 keys to v4 column names for Coffee
                    $v4Items = [];
                    foreach ($items as $key => $quantity) {
                        $v4Key = match($key) {
                            'cup' => 'coffeeCups',
                            'lid' => 'coffeeLids',
                            default => $key
                        };
                        $v4Items[$v4Key] = $quantity;
                    }
                    $record = Coffee::create($v4Items);
                    $photo->coffee_id = $record->id;
                    break;

                case 'food':
                    // Map v5 keys to v4 column names for Food
                    $v4Items = [];
                    foreach ($items as $key => $quantity) {
                        $v4Key = match($key) {
                            'wrapper' => 'sweetWrappers',
                            'packaging' => 'paperFoodPackaging',
                            default => $key
                        };
                        $v4Items[$v4Key] = $quantity;
                    }
                    $record = Food::create($v4Items);
                    $photo->food_id = $record->id;
                    break;

                case 'brands':
                    // Brands stay the same
                    $record = Brand::create($items);
                    $photo->brands_id = $record->id;
                    break;
            }
        }

        $photo->save();
        return $photo->refresh();
    }

    /** @test */
    public function single_object_single_brand_direct_match()
    {
        // Create the brand-object relationship
        $this->createBrandObjectRelationship('softdrinks', 'soda_can', 'coke');

        // Create photo with old tags
        $photo = $this->createPhotoWithOldTags([
            'softdrinks' => ['soda_can' => 1],  // Will be converted to tinCan in the DB
            'brands' => ['coke' => 1],
        ]);

        // Run the migration
//        Log::shouldReceive('info')->withAnyArgs()->andReturnTrue();
//        Log::shouldReceive('warning')->withAnyArgs()->andReturnTrue();

        $this->service->updateTags($photo);
        $photo->refresh();

        // Verify migration completed
        $this->assertNotNull($photo->migrated_at);

        // Check PhotoTag was created for soda_can
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');
        $photoTag = $photo->photoTags()->where('litter_object_id', $sodaCanId)->first();
        $this->assertNotNull($photoTag, "PhotoTag for soda_can should exist");
        $this->assertEquals(1, $photoTag->quantity);

        // Check brand was attached
        $cokeId = BrandList::where('key', 'coke')->value('id');
        $brandTag = $photoTag->extraTags()
            ->where('tag_type', 'brand')
            ->where('tag_type_id', $cokeId)
            ->first();

        $this->assertNotNull($brandTag, "Brand (coke) should be attached to soda_can");
    }

    /** @test */
    public function pivot_lookup_matches_brands_across_multiple_categories()
    {
        $this->createBrandObjectRelationship('softdrinks', 'soda_can', 'coke');
        $this->createBrandObjectRelationship('food', 'wrapper', 'mcdonalds');

        $photo = $this->createPhotoWithOldTags([
            'softdrinks' => ['soda_can' => 1],
            'food' => ['wrapper' => 1],
            'coffee' => ['cup' => 1],
            'brands' => ['coke' => 1, 'mcdonalds' => 1],
        ]);

//        Log::shouldReceive('info')->withAnyArgs()->andReturnTrue();
//        Log::shouldReceive('warning')->withAnyArgs()->andReturnTrue();

        $this->service->updateTags($photo);
        $photo->refresh();

        // Verify each brand attached to correct object
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');
        $cokeId = BrandList::where('key', 'coke')->value('id');

        $sodaCanTag = $photo->photoTags()->where('litter_object_id', $sodaCanId)->first();
        $this->assertNotNull($sodaCanTag);

        $cokeBrand = $sodaCanTag->extraTags()
            ->where('tag_type', 'brand')
            ->where('tag_type_id', $cokeId)
            ->first();
        $this->assertNotNull($cokeBrand, "Coke should be attached to soda_can");

        $wrapperId = LitterObject::where('key', 'wrapper')->value('id');
        $mcdonaldsId = BrandList::where('key', 'mcdonalds')->value('id');

        $wrapperTag = $photo->photoTags()->where('litter_object_id', $wrapperId)->first();
        $this->assertNotNull($wrapperTag);

        $mcdonaldsBrand = $wrapperTag->extraTags()
            ->where('tag_type', 'brand')
            ->where('tag_type_id', $mcdonaldsId)
            ->first();
        $this->assertNotNull($mcdonaldsBrand, "McDonalds should be attached to wrapper");

        $cupId = LitterObject::where('key', 'cup')->value('id');
        $cupTag = $photo->photoTags()->where('litter_object_id', $cupId)->first();
        $this->assertNotNull($cupTag);

        $cupBrands = $cupTag->extraTags()->where('tag_type', 'brand')->get();
        $this->assertCount(0, $cupBrands, "Cup should have NO brand");
    }

    /** @test */
    public function unique_quantity_match_when_no_pivot()
    {
        $photo = $this->createPhotoWithOldTags([
            'coffee' => ['cup' => 1],
            'alcohol' => ['beer_bottle' => 3],
            'brands' => ['budweiser' => 3],
        ]);

//        Log::shouldReceive('info')->withAnyArgs()->andReturnTrue();
//        Log::shouldReceive('warning')->withAnyArgs()->andReturnTrue();

        $this->service->updateTags($photo);
        $photo->refresh();

        $beerBottleId = LitterObject::where('key', 'beer_bottle')->value('id');
        $budweiserId = BrandList::where('key', 'budweiser')->value('id');

        $beerBottleTag = $photo->photoTags()->where('litter_object_id', $beerBottleId)->first();
        $this->assertNotNull($beerBottleTag);

        $budweiserBrand = $beerBottleTag->extraTags()
            ->where('tag_type', 'brand')
            ->where('tag_type_id', $budweiserId)
            ->first();
        $this->assertNotNull($budweiserBrand, "Budweiser should match beer_bottle via quantity");

        $cupId = LitterObject::where('key', 'cup')->value('id');
        $cupTag = $photo->photoTags()->where('litter_object_id', $cupId)->first();
        $this->assertNotNull($cupTag);

        $cupBrands = $cupTag->extraTags()->where('tag_type', 'brand')->get();
        $this->assertCount(0, $cupBrands, "Cup should have NO brand");
    }

    /** @test */
    public function no_attachment_when_quantity_ambiguous()
    {
        $photo = $this->createPhotoWithOldTags([
            'softdrinks' => ['soda_can' => 1, 'water_bottle' => 1],
            'brands' => ['coke' => 1],
        ]);

//        Log::shouldReceive('info')->withAnyArgs()->andReturnTrue();
//        Log::shouldReceive('warning')->withAnyArgs()->andReturnTrue();

        $this->service->updateTags($photo);
        $photo->refresh();

        $cokeId = BrandList::where('key', 'coke')->value('id');
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');
        $waterBottleId = LitterObject::where('key', 'water_bottle')->value('id');

        $sodaCanTag = $photo->photoTags()->where('litter_object_id', $sodaCanId)->first();
        if ($sodaCanTag) {
            $sodaCanBrands = $sodaCanTag->extraTags()
                ->where('tag_type', 'brand')
                ->where('tag_type_id', $cokeId)
                ->count();
            $this->assertEquals(0, $sodaCanBrands, "Coke should NOT be attached to soda_can (ambiguous)");
        }

        $waterBottleTag = $photo->photoTags()->where('litter_object_id', $waterBottleId)->first();
        if ($waterBottleTag) {
            $waterBottleBrands = $waterBottleTag->extraTags()
                ->where('tag_type', 'brand')
                ->where('tag_type_id', $cokeId)
                ->count();
            $this->assertEquals(0, $waterBottleBrands, "Coke should NOT be attached to water_bottle (ambiguous)");
        }
    }

    /** @test */
    public function pivot_takes_priority_over_quantity_matching()
    {
        \Log::info("Test");
        $this->createBrandObjectRelationship('alcohol', 'beer_bottle', 'heineken');

        $photo = $this->createPhotoWithOldTags([
            'alcohol' => ['beer_can' => 1, 'beer_bottle' => 5],
            'brands' => ['heineken' => 1],
        ]);

        $this->service->updateTags($photo);
        $photo->refresh();

        $heinekenId = BrandList::where('key', 'heineken')->value('id');
        $beerBottleId = LitterObject::where('key', 'beer_bottle')->value('id');
        $beerCanId = LitterObject::where('key', 'beer_can')->value('id');

        $beerBottleTag = $photo->photoTags()->where('litter_object_id', $beerBottleId)->first();
        $this->assertNotNull($beerBottleTag);

        $heinekenBrand = $beerBottleTag->extraTags()
            ->where('tag_type', 'brand')
            ->where('tag_type_id', $heinekenId)
            ->first();
        $this->assertNotNull($heinekenBrand, "Heineken should match beer_bottle via pivot");

        $beerCanTag = $photo->photoTags()->where('litter_object_id', $beerCanId)->first();
        $this->assertNotNull($beerCanTag);

        $beerCanBrands = $beerCanTag->extraTags()->where('tag_type', 'brand')->get();
        $this->assertCount(0, $beerCanBrands, "Beer can should have NO brand");
    }
}
