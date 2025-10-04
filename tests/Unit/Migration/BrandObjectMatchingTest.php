<?php

namespace Tests\Unit\Migration;

use App\Models\Litter\Tags\BrandList;
use App\Models\Litter\Tags\Category;
use App\Models\Litter\Tags\CategoryObject;
use App\Models\Litter\Tags\LitterObject;
use App\Services\Tags\ClassifyTagsService;
use Database\Seeders\Tags\GenerateBrandsSeeder;
use Database\Seeders\Tags\GenerateTagsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrandObjectMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected ClassifyTagsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            GenerateTagsSeeder::class,
            GenerateBrandsSeeder::class
        ]);

        $this->service = app(ClassifyTagsService::class);
    }

    /** @test */
    public function it_matches_brand_to_object_via_pivot_when_unique()
    {
        // Setup: coke has pivot to soda_can only
        $categoryId = Category::where('key', 'softdrinks')->value('id');
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');
        $waterBottleId = LitterObject::where('key', 'water_bottle')->value('id');
        $cokeId = BrandList::where('key', 'coke')->value('id');

        // Create pivot for soda_can ⇄ coke
        $catObj = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $sodaCanId,
        ]);

        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $cokeId,
            'quantity' => 1,
        ]);

        $group = [
            'category_id' => $categoryId,
            'objects' => [
                ['id' => $waterBottleId, 'key' => 'water_bottle', 'quantity' => 1],
                ['id' => $sodaCanId, 'key' => 'soda_can', 'quantity' => 1],
            ],
            'brands' => [
                ['id' => $cokeId, 'key' => 'coke', 'quantity' => 1],
            ],
        ];

        $result = $this->service->resolveBrandObjectLinks(999, $group);

        $this->assertCount(1, $result);
        $this->assertEquals('soda_can', $result[0]['object']['key']);
        $this->assertEquals('coke', $result[0]['brand']['key']);
    }

    /** @test */
    public function it_matches_multiple_brands_to_correct_objects_via_pivots()
    {
        // Setup: lucozade → sports_bottle, coke → soda_can
        $categoryId = Category::where('key', 'softdrinks')->value('id');
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');
        $sportsBottleId = LitterObject::where('key', 'sports_bottle')->value('id');
        $cokeId = BrandList::where('key', 'coke')->value('id');
        $lucozadeId = BrandList::where('key', 'lucozade')->value('id');

        // Pivot 1: soda_can ⇄ coke
        $catObj1 = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $sodaCanId,
        ]);
        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj1->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $cokeId,
            'quantity' => 1,
        ]);

        // Pivot 2: sports_bottle ⇄ lucozade
        $catObj2 = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $sportsBottleId,
        ]);
        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj2->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $lucozadeId,
            'quantity' => 1,
        ]);

        $group = [
            'category_id' => $categoryId,
            'objects' => [
                ['id' => $sodaCanId, 'key' => 'soda_can', 'quantity' => 1],
                ['id' => $sportsBottleId, 'key' => 'sports_bottle', 'quantity' => 1],
            ],
            'brands' => [
                ['id' => $cokeId, 'key' => 'coke', 'quantity' => 1],
                ['id' => $lucozadeId, 'key' => 'lucozade', 'quantity' => 1],
            ],
        ];

        $result = $this->service->resolveBrandObjectLinks(999, $group);

        $this->assertCount(2, $result);

        $cokeMatch = collect($result)->firstWhere('brand.key', 'coke');
        $this->assertEquals('soda_can', $cokeMatch['object']['key']);

        $lucozadeMatch = collect($result)->firstWhere('brand.key', 'lucozade');
        $this->assertEquals('sports_bottle', $lucozadeMatch['object']['key']);
    }

    /** @test */
    public function it_uses_quantity_matching_when_no_pivot_exists_and_quantity_is_unique()
    {
        $categoryId = Category::where('key', 'softdrinks')->value('id');
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');
        $waterBottleId = LitterObject::where('key', 'water_bottle')->value('id');
        $cokeId = BrandList::where('key', 'coke')->value('id');

        // No pivots created
        $group = [
            'category_id' => $categoryId,
            'objects' => [
                ['id' => $waterBottleId, 'key' => 'water_bottle', 'quantity' => 1],
                ['id' => $sodaCanId, 'key' => 'soda_can', 'quantity' => 3],
            ],
            'brands' => [
                ['id' => $cokeId, 'key' => 'coke', 'quantity' => 3],
            ],
        ];

        $result = $this->service->resolveBrandObjectLinks(999, $group);

        $this->assertCount(1, $result);
        $this->assertEquals('soda_can', $result[0]['object']['key']);
        $this->assertEquals(3, $result[0]['object']['quantity']);
    }

    /** @test */
    public function it_uses_quantity_as_tiebreaker_when_brand_has_multiple_pivots()
    {
        // Setup: coke has pivots to BOTH soda_can and water_bottle
        $categoryId = Category::where('key', 'softdrinks')->value('id');
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');
        $waterBottleId = LitterObject::where('key', 'water_bottle')->value('id');
        $cokeId = BrandList::where('key', 'coke')->value('id');

        // Pivot 1: soda_can ⇄ coke
        $catObj1 = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $sodaCanId,
        ]);
        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj1->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $cokeId,
            'quantity' => 1,
        ]);

        // Pivot 2: water_bottle ⇄ coke
        $catObj2 = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $waterBottleId,
        ]);
        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj2->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $cokeId,
            'quantity' => 1,
        ]);

        $group = [
            'category_id' => $categoryId,
            'objects' => [
                ['id' => $waterBottleId, 'key' => 'water_bottle', 'quantity' => 1],
                ['id' => $sodaCanId, 'key' => 'soda_can', 'quantity' => 3],
            ],
            'brands' => [
                ['id' => $cokeId, 'key' => 'coke', 'quantity' => 3],
            ],
        ];

        $result = $this->service->resolveBrandObjectLinks(999, $group);

        $this->assertCount(1, $result);
        $this->assertEquals('soda_can', $result[0]['object']['key']);
        $this->assertEquals(3, $result[0]['brand']['quantity']);
    }

    /** @test */
    public function it_does_not_match_same_brand_to_multiple_objects()
    {
        // Ensure that once a brand is matched, it's not matched again
        $categoryId = Category::where('key', 'softdrinks')->value('id');
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');
        $waterBottleId = LitterObject::where('key', 'water_bottle')->value('id');
        $cokeId = BrandList::where('key', 'coke')->value('id');

        // Create pivots for both objects
        $catObj1 = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $sodaCanId,
        ]);
        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj1->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $cokeId,
            'quantity' => 1,
        ]);

        $catObj2 = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $waterBottleId,
        ]);
        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj2->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $cokeId,
            'quantity' => 1,
        ]);

        $group = [
            'category_id' => $categoryId,
            'objects' => [
                ['id' => $waterBottleId, 'key' => 'water_bottle', 'quantity' => 1],
                ['id' => $sodaCanId, 'key' => 'soda_can', 'quantity' => 1],
            ],
            'brands' => [
                ['id' => $cokeId, 'key' => 'coke', 'quantity' => 1],
            ],
        ];

        $result = $this->service->resolveBrandObjectLinks(999, $group);

        // Should only match to ONE object, not both
        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_handles_three_brands_with_mixed_pivot_and_quantity_matching()
    {
        // coke has pivot to soda_can
        // pepsi has NO pivot but quantity matches fizzy_bottle
        // sprite has pivot to water_bottle BUT quantity doesn't match
        $categoryId = Category::where('key', 'softdrinks')->value('id');
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');
        $fizzyBottleId = LitterObject::where('key', 'fizzy_bottle')->value('id');
        $waterBottleId = LitterObject::where('key', 'water_bottle')->value('id');
        $cokeId = BrandList::where('key', 'coke')->value('id');
        $pepsiId = BrandList::where('key', 'pepsi')->value('id');
        $spriteId = BrandList::where('key', 'sprite')->value('id');

        // Pivot: soda_can ⇄ coke
        $catObj1 = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $sodaCanId,
        ]);
        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj1->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $cokeId,
            'quantity' => 1,
        ]);

        // Pivot: water_bottle ⇄ sprite
        $catObj2 = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $waterBottleId,
        ]);
        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj2->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $spriteId,
            'quantity' => 1,
        ]);

        $group = [
            'category_id' => $categoryId,
            'objects' => [
                ['id' => $sodaCanId, 'key' => 'soda_can', 'quantity' => 1],
                ['id' => $fizzyBottleId, 'key' => 'fizzy_bottle', 'quantity' => 5],
                ['id' => $waterBottleId, 'key' => 'water_bottle', 'quantity' => 2],
            ],
            'brands' => [
                ['id' => $cokeId, 'key' => 'coke', 'quantity' => 1],
                ['id' => $pepsiId, 'key' => 'pepsi', 'quantity' => 5],
                ['id' => $spriteId, 'key' => 'sprite', 'quantity' => 2],
            ],
        ];

        $result = $this->service->resolveBrandObjectLinks(999, $group);

        $this->assertCount(3, $result);

        // coke matched via pivot
        $cokeMatch = collect($result)->firstWhere('brand.key', 'coke');
        $this->assertEquals('soda_can', $cokeMatch['object']['key']);

        // pepsi matched via quantity (no pivot exists)
        $pepsiMatch = collect($result)->firstWhere('brand.key', 'pepsi');
        $this->assertEquals('fizzy_bottle', $pepsiMatch['object']['key']);

        // sprite matched via pivot (has pivot to water_bottle)
        $spriteMatch = collect($result)->firstWhere('brand.key', 'sprite');
        $this->assertEquals('water_bottle', $spriteMatch['object']['key']);
    }

    /** @test */
    public function it_handles_cross_category_scenario_from_real_photo()
    {
        // Real scenario: coffee lid + fizzy bottle + soda can, with only 1 coke brand
        // Coke should NOT match to coffee lid even though quantities align
        $coffeeId = Category::where('key', 'coffee')->value('id');
        $softdrinksId = Category::where('key', 'softdrinks')->value('id');
        $lidId = LitterObject::where('key', 'lid')->value('id');
        $fizzyBottleId = LitterObject::where('key', 'fizzy_bottle')->value('id');
        $sodaCanId = LitterObject::where('key', 'soda_can')->value('id');
        $cokeId = BrandList::where('key', 'coke')->value('id');

        // Pivots exist for all three (coke makes bottles, cans, and lids)
        foreach ([$lidId, $fizzyBottleId, $sodaCanId] as $objectId) {
            $catId = ($objectId === $lidId) ? $coffeeId : $softdrinksId;
            $catObj = CategoryObject::firstOrCreate([
                'category_id' => $catId,
                'litter_object_id' => $objectId,
            ]);
            DB::table('taggables')->insert([
                'category_litter_object_id' => $catObj->id,
                'taggable_type' => BrandList::class,
                'taggable_id' => $cokeId,
                'quantity' => 1,
            ]);
        }

        // Only testing softdrinks group (coffee group processed separately)
        $group = [
            'category_id' => $softdrinksId,
            'objects' => [
                ['id' => $fizzyBottleId, 'key' => 'fizzy_bottle', 'quantity' => 1],
                ['id' => $sodaCanId, 'key' => 'soda_can', 'quantity' => 1],
            ],
            'brands' => [
                ['id' => $cokeId, 'key' => 'coke', 'quantity' => 1],
            ],
        ];

        $result = $this->service->resolveBrandObjectLinks(999, $group);

        // Should match to exactly one object, not both
        $this->assertCount(1, $result);
        $this->assertContains($result[0]['object']['key'], ['fizzy_bottle', 'soda_can']);
    }

    /** @test */
    public function it_prioritizes_pivot_over_quantity_when_both_exist()
    {
        // heineken has pivot to beer_bottle (qty 1)
        // BUT photo has beer_can with qty 1 (quantity match)
        // Should use pivot, not quantity
        $categoryId = Category::where('key', 'alcohol')->value('id');
        $beerCanId = LitterObject::where('key', 'beer_can')->value('id');
        $beerBottleId = LitterObject::where('key', 'beer_bottle')->value('id');
        $heinekenId = BrandList::where('key', 'heineken')->value('id');

        // Pivot: beer_bottle ⇄ heineken
        $catObj = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $beerBottleId,
        ]);
        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $heinekenId,
            'quantity' => 1,
        ]);

        $group = [
            'category_id' => $categoryId,
            'objects' => [
                ['id' => $beerCanId, 'key' => 'beer_can', 'quantity' => 1],
                ['id' => $beerBottleId, 'key' => 'beer_bottle', 'quantity' => 2],
            ],
            'brands' => [
                ['id' => $heinekenId, 'key' => 'heineken', 'quantity' => 1],
            ],
        ];

        $result = $this->service->resolveBrandObjectLinks(999, $group);

        $this->assertCount(1, $result);
        // Should match to beer_bottle (pivot) not beer_can (quantity)
        $this->assertEquals('beer_bottle', $result[0]['object']['key']);
    }

    /** @test */
    public function it_handles_multiple_brands_same_quantity_different_pivots()
    {
        // 3 brands all with qty 1, 3 objects all with qty 1
        // Each brand has pivot to different object
        $categoryId = Category::where('key', 'softdrinks')->value('id');
        $canId = LitterObject::where('key', 'soda_can')->value('id');
        $bottleId = LitterObject::where('key', 'water_bottle')->value('id');
        $sportsId = LitterObject::where('key', 'sports_bottle')->value('id');
        $cokeId = BrandList::where('key', 'coke')->value('id');
        $pepsiId = BrandList::where('key', 'pepsi')->value('id');
        $spriteId = BrandList::where('key', 'sprite')->value('id');

        // Pivot: can ⇄ coke
        $catObj1 = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $canId,
        ]);
        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj1->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $cokeId,
            'quantity' => 1,
        ]);

        // Pivot: bottle ⇄ pepsi
        $catObj2 = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $bottleId,
        ]);
        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj2->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $pepsiId,
            'quantity' => 1,
        ]);

        // Pivot: sports ⇄ sprite
        $catObj3 = CategoryObject::firstOrCreate([
            'category_id' => $categoryId,
            'litter_object_id' => $sportsId,
        ]);
        DB::table('taggables')->insert([
            'category_litter_object_id' => $catObj3->id,
            'taggable_type' => BrandList::class,
            'taggable_id' => $spriteId,
            'quantity' => 1,
        ]);

        $group = [
            'category_id' => $categoryId,
            'objects' => [
                ['id' => $canId, 'key' => 'soda_can', 'quantity' => 1],
                ['id' => $bottleId, 'key' => 'water_bottle', 'quantity' => 1],
                ['id' => $sportsId, 'key' => 'sports_bottle', 'quantity' => 1],
            ],
            'brands' => [
                ['id' => $cokeId, 'key' => 'coke', 'quantity' => 1],
                ['id' => $pepsiId, 'key' => 'pepsi', 'quantity' => 1],
                ['id' => $spriteId, 'key' => 'sprite', 'quantity' => 1],
            ],
        ];

        $result = $this->service->resolveBrandObjectLinks(999, $group);

        $this->assertCount(3, $result);

        $cokeMatch = collect($result)->firstWhere('brand.key', 'coke');
        $this->assertEquals('soda_can', $cokeMatch['object']['key']);

        $pepsiMatch = collect($result)->firstWhere('brand.key', 'pepsi');
        $this->assertEquals('water_bottle', $pepsiMatch['object']['key']);

        $spriteMatch = collect($result)->firstWhere('brand.key', 'sprite');
        $this->assertEquals('sports_bottle', $spriteMatch['object']['key']);
    }

    /** @test */
    public function it_leaves_brands_unmatched_when_no_pivot_and_quantity_ambiguous()
    {
        // 2 brands qty 1, 3 objects qty 1, no pivots
        // Cannot determine which brand goes to which object
        $categoryId = Category::where('key', 'softdrinks')->value('id');
        $can1Id = LitterObject::where('key', 'soda_can')->value('id');
        $can2Id = LitterObject::where('key', 'energy_can')->value('id');
        $bottleId = LitterObject::where('key', 'water_bottle')->value('id');
        $cokeId = BrandList::where('key', 'coke')->value('id');
        $pepsiId = BrandList::where('key', 'pepsi')->value('id');

        // No pivots created

        $group = [
            'category_id' => $categoryId,
            'objects' => [
                ['id' => $can1Id, 'key' => 'soda_can', 'quantity' => 1],
                ['id' => $can2Id, 'key' => 'energy_can', 'quantity' => 1],
                ['id' => $bottleId, 'key' => 'water_bottle', 'quantity' => 1],
            ],
            'brands' => [
                ['id' => $cokeId, 'key' => 'coke', 'quantity' => 1],
                ['id' => $pepsiId, 'key' => 'pepsi', 'quantity' => 1],
            ],
        ];

        $result = $this->service->resolveBrandObjectLinks(999, $group);

        // Should not match anything - quantity is ambiguous and no pivots
        $this->assertCount(0, $result);
    }
}
