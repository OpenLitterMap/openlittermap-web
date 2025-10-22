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
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BrandPivotVerificationTest extends TestCase
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
     * Helper to create a photo with old tags
     */
    protected function createPhotoWithOldTags(array $tags): Photo
    {
        $photo = Photo::factory()->create(['user_id' => $this->user->id]);

        foreach ($tags as $category => $items) {
            switch ($category) {
                case 'softdrinks':
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
                    $v4Items = [];
                    foreach ($items as $key => $quantity) {
                        $v4Key = match($key) {
                            'beer_bottle' => 'beerBottle',
                            'beer_can' => 'beerCan',
                            default => $key
                        };
                        $v4Items[$v4Key] = $quantity;
                    }
                    $record = Alcohol::create($v4Items);
                    $photo->alcohol_id = $record->id;
                    break;

                case 'brands':
                    $record = Brand::create($items);
                    $photo->brands_id = $record->id;
                    break;
            }
        }

        $photo->save();
        return $photo->refresh();
    }

    /**
     * Helper to create brand-object pivot
     */
    protected function createBrandPivot(string $categoryKey, string $objectKey, string $brandKey): void
    {
        $category = Category::where('key', $categoryKey)->first();
        $object = LitterObject::where('key', $objectKey)->first();
        $brand = BrandList::where('key', $brandKey)->first();

        if (!$category || !$object || !$brand) {
            $this->fail("Missing data for pivot: cat={$categoryKey}, obj={$objectKey}, brand={$brandKey}");
        }

        $categoryObject = CategoryObject::firstOrCreate([
            'category_id' => $category->id,
            'litter_object_id' => $object->id,
        ]);

        Taggable::firstOrCreate([
            'category_litter_object_id' => $categoryObject->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $brand->id,
        ], ['quantity' => 1]);
    }

    /** @test */
    public function verify_pivot_lookup_with_logging()
    {
        // Create specific pivots
        $this->createBrandPivot('softdrinks', 'soda_can', 'coke');
        $this->createBrandPivot('alcohol', 'beer_bottle', 'heineken');

        $photo = $this->createPhotoWithOldTags([
            'softdrinks' => ['soda_can' => 2, 'water_bottle' => 1],
            'alcohol' => ['beer_bottle' => 1, 'beer_can' => 1],
            'brands' => ['coke' => 1, 'heineken' => 1],
        ]);

        // Enable logging to see what's happening
        Log::shouldReceive('info')
            ->withArgs(function ($message) {
                echo "\n[LOG]: " . $message;
                return true;
            })
            ->andReturnTrue();

        $this->service->updateTags($photo);
        $photo->refresh();

        // Verify coke attached to soda_can via pivot
        $cokeId = BrandList::where('key', 'coke')->value('id');
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');

        $sodaCanTag = $photo->photoTags()->where('litter_object_id', $sodaCanId)->first();
        $cokeBrand = $sodaCanTag->extraTags()
            ->where('tag_type', 'brand')
            ->where('tag_type_id', $cokeId)
            ->first();

        $this->assertNotNull($cokeBrand, "Coke should attach to soda_can via pivot");

        // Log the actual pivot data for debugging
        echo "\n\n=== PIVOT VERIFICATION ===";
        echo "\nCoke ID: {$cokeId}";
        echo "\nSoda Can ID: {$sodaCanId}";
        echo "\nAttachment found: " . ($cokeBrand ? 'YES' : 'NO');
    }

    /** @test */
    public function verify_cross_category_brand_matching()
    {
        // Test that brands can match across different categories correctly
        $this->createBrandPivot('softdrinks', 'soda_can', 'coke');
        $this->createBrandPivot('food', 'wrapper', 'mcdonalds');
        $this->createBrandPivot('coffee', 'cup', 'starbucks');

        $photo = $this->createPhotoWithOldTags([
            'softdrinks' => ['soda_can' => 1],
            'food' => ['wrapper' => 1],
            'coffee' => ['cup' => 1],
            'brands' => ['coke' => 1, 'mcdonalds' => 1, 'starbucks' => 1],
        ]);

        $this->service->updateTags($photo);
        $photo->refresh();

        // Check each brand attached to correct category/object
        $assertions = [
            ['brand' => 'coke', 'object' => 'soda_can', 'category' => 'softdrinks'],
            ['brand' => 'mcdonalds', 'object' => 'wrapper', 'category' => 'food'],
            ['brand' => 'starbucks', 'object' => 'cup', 'category' => 'coffee'],
        ];

        foreach ($assertions as $assertion) {
            $brandId = BrandList::where('key', $assertion['brand'])->value('id');
            $objectId = LitterObject::where('key', $assertion['object'])->value('id');

            $photoTag = $photo->photoTags()->where('litter_object_id', $objectId)->first();
            $this->assertNotNull($photoTag, "PhotoTag for {$assertion['object']} should exist");

            $brandTag = $photoTag->extraTags()
                ->where('tag_type', 'brand')
                ->where('tag_type_id', $brandId)
                ->first();

            $this->assertNotNull($brandTag, "{$assertion['brand']} should attach to {$assertion['object']} in {$assertion['category']}");
        }
    }

    /** @test */
    public function verify_pivot_priority_order()
    {
        // Test that pivot has priority over quantity matching
        $this->createBrandPivot('softdrinks', 'water_bottle', 'coke');

        $photo = $this->createPhotoWithOldTags([
            'softdrinks' => [
                'soda_can' => 3,     // Better quantity match
                'water_bottle' => 1,  // Has pivot
            ],
            'brands' => ['coke' => 3],
        ]);

        $this->service->updateTags($photo);
        $photo->refresh();

        $cokeId = BrandList::where('key', 'coke')->value('id');
        $waterBottleId = LitterObject::where('key', 'water_bottle')->value('id');
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');

        // Coke should attach to water_bottle (pivot) not soda_can (quantity match)
        $waterBottleTag = $photo->photoTags()->where('litter_object_id', $waterBottleId)->first();
        $cokeBrandOnWaterBottle = $waterBottleTag->extraTags()
            ->where('tag_type', 'brand')
            ->where('tag_type_id', $cokeId)
            ->first();
        $this->assertNotNull($cokeBrandOnWaterBottle, "Coke should attach to water_bottle via pivot");

        $sodaCanTag = $photo->photoTags()->where('litter_object_id', $sodaCanId)->first();
        if ($sodaCanTag) {
            $cokeBrandOnSodaCan = $sodaCanTag->extraTags()
                ->where('tag_type', 'brand')
                ->where('tag_type_id', $cokeId)
                ->count();
            $this->assertEquals(0, $cokeBrandOnSodaCan, "Coke should NOT attach to soda_can");
        }
    }

    /** @test */
    public function verify_no_duplicate_brand_attachments()
    {
        // Ensure a brand doesn't attach to multiple objects
        $photo = $this->createPhotoWithOldTags([
            'softdrinks' => ['soda_can' => 2, 'water_bottle' => 2],
            'brands' => ['coke' => 2],
        ]);

        $this->service->updateTags($photo);
        $photo->refresh();

        $cokeId = BrandList::where('key', 'coke')->value('id');

        // Count how many PhotoTags have coke attached
        $photoTagsWithCoke = 0;
        foreach ($photo->photoTags as $photoTag) {
            $hasCoke = $photoTag->extraTags()
                ->where('tag_type', 'brand')
                ->where('tag_type_id', $cokeId)
                ->exists();
            if ($hasCoke) {
                $photoTagsWithCoke++;
            }
        }

        $this->assertEquals(1, $photoTagsWithCoke, "Coke should only attach to ONE object");
    }

    /** @test */
    public function verify_actual_pivot_data_in_database()
    {
        // Direct database verification of pivot relationships
        $this->createBrandPivot('softdrinks', 'soda_can', 'coke');
        $this->createBrandPivot('alcohol', 'beer_bottle', 'heineken');

        // Verify the pivots exist in database
        $pivotCount = Taggable::where('taggable_type', BrandList::class)->count();
        $this->assertGreaterThanOrEqual(2, $pivotCount, "Should have at least 2 brand pivots");

        // Get actual pivot data
        $pivots = Taggable::where('taggable_type', BrandList::class)
            ->with(['categoryLitterObject.category', 'categoryLitterObject.litterObject', 'taggable'])
            ->get();

        echo "\n\n=== DATABASE PIVOT DATA ===";
        foreach ($pivots as $pivot) {
            $category = $pivot->categoryLitterObject->category->key ?? 'unknown';
            $object = $pivot->categoryLitterObject->litterObject->key ?? 'unknown';
            $brand = $pivot->taggable->key ?? 'unknown';
            echo "\nPivot: {$category}.{$object} → {$brand}";
        }
    }

    /** @test */
    public function log_brand_matching_process()
    {
        // This test specifically logs the matching process
        $this->createBrandPivot('softdrinks', 'soda_can', 'coke');

        $photo = $this->createPhotoWithOldTags([
            'softdrinks' => ['soda_can' => 1, 'water_bottle' => 2],
            'alcohol' => ['beer_bottle' => 1],
            'brands' => ['coke' => 1, 'pepsi' => 2],
        ]);

        echo "\n\n=== BRAND MATCHING PROCESS ===";
        echo "\nPhoto has:";
        echo "\n  - soda_can: 1";
        echo "\n  - water_bottle: 2";
        echo "\n  - beer_bottle: 1";
        echo "\n  - coke: 1";
        echo "\n  - pepsi: 2";
        echo "\n\nExpected matches:";
        echo "\n  - coke → soda_can (via pivot)";
        echo "\n  - pepsi → water_bottle (quantity match)";

        $this->service->updateTags($photo);
        $photo->refresh();

        // Verify and log results
        $results = [];
        foreach ($photo->photoTags as $photoTag) {
            $objectKey = $photoTag->object->key ?? 'unknown';
            $brands = $photoTag->extraTags()
                ->where('tag_type', 'brand')
                ->get()
                ->map(fn($tag) => BrandList::find($tag->tag_type_id)->key ?? 'unknown')
                ->toArray();

            if (!empty($brands)) {
                $results[$objectKey] = $brands;
            }
        }

        echo "\n\nActual matches:";
        foreach ($results as $object => $brands) {
            foreach ($brands as $brand) {
                echo "\n  - {$brand} → {$object}";
            }
        }

        // Assertions
        $this->assertArrayHasKey('soda_can', $results, "soda_can should have brands");
        $this->assertContains('coke', $results['soda_can'], "coke should attach to soda_can");
        $this->assertArrayHasKey('water_bottle', $results, "water_bottle should have brands");
        $this->assertContains('pepsi', $results['water_bottle'], "pepsi should attach to water_bottle");
    }
}
