<?php

namespace Tests\Unit\Migration;

use App\Models\Litter\Categories\Alcohol;
use App\Models\Litter\Categories\Brand;
use App\Models\Litter\Categories\Coffee;
use App\Models\Litter\Categories\Food;
use App\Models\Litter\Categories\SoftDrinks;
use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\LitterObject;
use App\Models\Photo;
use App\Models\Users\User;
use App\Services\Tags\UpdateTagsService;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

        // Create brand_object pivot table
        if (!Schema::hasTable('brand_object')) {
            Schema::create('brand_object', function ($table) {
                $table->unsignedBigInteger('brand_id');
                $table->unsignedBigInteger('litter_object_id');
                $table->timestamps();

                $table->index(['brand_id', 'litter_object_id']);
            });
        }

        $this->service = app(UpdateTagsService::class);
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('brand_object');
        parent::tearDown();
    }

    /**
     * Map new v5 keys to old v4 keys (reverse of deprecation map)
     * v5 uses snake_case, v4 uses camelCase
     */
    protected function toV4Key(string $v5Key): string
    {
        return match($v5Key) {
            // Softdrinks
            'soda_can' => 'tinCan',
            'water_bottle' => 'waterBottle',
            'fizzy_bottle' => 'fizzyDrinkBottle',
            'sports_bottle' => 'sportsDrink',
            'cup' => 'plastic_cups',

            // Alcohol
            'beer_bottle' => 'beerBottle',
            'beer_can' => 'beerCan',
            'spirits_bottle' => 'spiritBottle',
            'wine_bottle' => 'wineBottle',
            'packaging' => 'paperCardAlcoholPackaging',

            // Coffee
            'cup' => 'coffeeCups',
            'lid' => 'coffeeLids',

            // Food
            'wrapper' => 'sweetWrappers',
            'packaging' => 'paperFoodPackaging',

            // Brands
            'coke' => 'coke',
            'budweiser' => 'budweiser',
            'mcdonalds' => 'mcdonalds',
            'heineken' => 'heineken',

            // Fallback: try camelCase conversion
            default => lcfirst(str_replace('_', '', ucwords($v5Key, '_')))
        };
    }

    /**
     * Create photo with v4 tags using actual v4 column names
     */
    protected function createPhotoWithTags(array $tags): Photo
    {
        $photo = Photo::factory()->create(['user_id' => $this->user->id]);

        foreach ($tags as $category => $items) {
            // Convert v5 keys to v4 keys
            $v4Items = [];
            foreach ($items as $key => $value) {
                $v4Key = $this->toV4Key($key);
                $v4Items[$v4Key] = $value;
            }

            switch ($category) {
                case 'food':
                    $record = Food::create($v4Items);
                    $photo->food_id = $record->id;
                    break;

                case 'coffee':
                    $record = Coffee::create($v4Items);
                    $photo->coffee_id = $record->id;
                    break;

                case 'alcohol':
                    $record = Alcohol::create($v4Items);
                    $photo->alcohol_id = $record->id;
                    break;

                case 'softdrinks':
                    $record = SoftDrinks::create($v4Items);
                    $photo->softdrinks_id = $record->id;
                    break;

                case 'brands':
                    $record = Brand::create($v4Items);
                    $photo->brands_id = $record->id;
                    break;
            }
        }

        $photo->save();
        return $photo->refresh();
    }

    /** @test */
    public function rule_1_single_object_single_brand_direct_match()
    {
        $cokeId = BrandList::where('key', 'coke')->value('id');
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');

        DB::table('brand_object')->insert([
            'brand_id' => $cokeId,
            'litter_object_id' => $sodaCanId,
        ]);

        $photo = $this->createPhotoWithTags([
            'softdrinks' => ['soda_can' => 1],  // Will convert to tinCan
            'brands' => ['coke' => 1],
        ]);

        Log::shouldReceive('info')->withAnyArgs();

        $this->service->updateTags($photo);

        $photoTag = $photo->photoTags()->where('litter_object_id', $sodaCanId)->first();
        $this->assertNotNull($photoTag, "PhotoTag for soda_can should exist");
        $this->assertEquals(1, $photoTag->quantity);

        $brandTag = $photoTag->extraTags()
            ->where('tag_type', 'brand')
            ->where('tag_type_id', $cokeId)
            ->first();

        $this->assertNotNull($brandTag, "Brand (coke) should be attached to soda_can");
    }

    /** @test */
    public function rule_2_pivot_lookup_matches_brand_across_multiple_categories()
    {
        $cokeId = BrandList::where('key', 'coke')->value('id');
        $mcdonaldsId = BrandList::where('key', 'mcdonalds')->value('id');
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');
        $wrapperId = LitterObject::where('key', 'wrapper')->value('id');
        $cupId = LitterObject::where('key', 'cup')->value('id');

        DB::table('brand_object')->insert([
            ['brand_id' => $cokeId, 'litter_object_id' => $sodaCanId],
            ['brand_id' => $mcdonaldsId, 'litter_object_id' => $wrapperId],
        ]);

        $photo = $this->createPhotoWithTags([
            'softdrinks' => ['soda_can' => 1],
            'food' => ['wrapper' => 1],
            'coffee' => ['cup' => 1],
            'brands' => ['coke' => 1, 'mcdonalds' => 1],
        ]);

        Log::shouldReceive('info')->withAnyArgs();

        $this->service->updateTags($photo);

        // Coke → soda_can
        $sodaCanTag = $photo->photoTags()->where('litter_object_id', $sodaCanId)->first();
        $cokeBrand = $sodaCanTag->extraTags()->where('tag_type', 'brand')->where('tag_type_id', $cokeId)->first();
        $this->assertNotNull($cokeBrand, "Coke should be attached to soda_can");

        // Mcdonalds → wrapper
        $wrapperTag = $photo->photoTags()->where('litter_object_id', $wrapperId)->first();
        $mcdonaldsBrand = $wrapperTag->extraTags()->where('tag_type', 'brand')->where('tag_type_id', $mcdonaldsId)->first();
        $this->assertNotNull($mcdonaldsBrand, "Mcdonalds should be attached to wrapper");

        // Cup has no brand
        $cupTag = $photo->photoTags()->where('litter_object_id', $cupId)->first();
        $cupBrands = $cupTag->extraTags()->where('tag_type', 'brand')->get();
        $this->assertCount(0, $cupBrands, "Cup should have NO brand");
    }

    /** @test */
    public function rule_3_unique_quantity_match_when_no_pivot()
    {
        $budweiserId = BrandList::where('key', 'budweiser')->value('id');
        $cupId = LitterObject::where('key', 'cup')->value('id');
        $beerBottleId = LitterObject::where('key', 'beer_bottle')->value('id');

        // NO pivots

        $photo = $this->createPhotoWithTags([
            'coffee' => ['cup' => 1],
            'alcohol' => ['beer_bottle' => 3],
            'brands' => ['budweiser' => 3],
        ]);

        Log::shouldReceive('info')->withAnyArgs();

        $this->service->updateTags($photo);

        // Budweiser → beer_bottle (qty 3 matches)
        $beerBottleTag = $photo->photoTags()->where('litter_object_id', $beerBottleId)->first();
        $budweiserBrand = $beerBottleTag->extraTags()->where('tag_type', 'brand')->where('tag_type_id', $budweiserId)->first();
        $this->assertNotNull($budweiserBrand, "Budweiser should match beer_bottle via quantity");

        // Cup has no brand
        $cupTag = $photo->photoTags()->where('litter_object_id', $cupId)->first();
        $cupBrands = $cupTag->extraTags()->where('tag_type', 'brand')->get();
        $this->assertCount(0, $cupBrands, "Cup should have NO brand");
    }

    /** @test */
    public function rule_4_no_attachment_when_quantity_ambiguous()
    {
        $cokeId = BrandList::where('key', 'coke')->value('id');

        $photo = $this->createPhotoWithTags([
            'softdrinks' => ['soda_can' => 1, 'water_bottle' => 1],
            'brands' => ['coke' => 1],
        ]);

        Log::shouldReceive('info')->withAnyArgs();
        Log::shouldReceive('warning')->withAnyArgs();

        $this->service->updateTags($photo);

        // Brand should NOT be attached
        $brandAttachments = DB::table('photo_tag_extra_tags')
            ->where('tag_type', 'brand')
            ->where('tag_type_id', $cokeId)
            ->get();

        $this->assertCount(0, $brandAttachments, "Ambiguous quantity - coke should NOT be attached");
    }

    /** @test */
    public function pivot_takes_priority_over_quantity_matching()
    {
        $heinekenId = BrandList::where('key', 'heineken')->value('id');
        $beerCanId = LitterObject::where('key', 'beer_can')->value('id');
        $beerBottleId = LitterObject::where('key', 'beer_bottle')->value('id');

        // Pivot: heineken → beer_bottle
        DB::table('brand_object')->insert([
            'brand_id' => $heinekenId,
            'litter_object_id' => $beerBottleId,
        ]);

        $photo = $this->createPhotoWithTags([
            'alcohol' => ['beer_can' => 1, 'beer_bottle' => 5],
            'brands' => ['heineken' => 1],
        ]);

        Log::shouldReceive('info')->withAnyArgs();

        $this->service->updateTags($photo);

        // Should use pivot (beer_bottle), not quantity (beer_can)
        $beerBottleTag = $photo->photoTags()->where('litter_object_id', $beerBottleId)->first();
        $heinekenBrand = $beerBottleTag->extraTags()->where('tag_type', 'brand')->where('tag_type_id', $heinekenId)->first();
        $this->assertNotNull($heinekenBrand, "Heineken should match beer_bottle via pivot");

        // Beer can has no brand
        $beerCanTag = $photo->photoTags()->where('litter_object_id', $beerCanId)->first();
        $beerCanBrands = $beerCanTag->extraTags()->where('tag_type', 'brand')->get();
        $this->assertCount(0, $beerCanBrands, "Beer can should have NO brand");
    }
}
