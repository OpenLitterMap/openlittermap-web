<?php

namespace Tests\Unit\Achievements;

use App\Services\Achievements\Checkers\{
    UploadsChecker,
    ObjectsChecker,
    CategoriesChecker,
    MaterialsChecker,
    BrandsChecker
};
use App\Services\Achievements\Tags\TagKeyCache;
use Tests\TestCase;

class CheckerUnitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TagKeyCache::warmCache();
    }

    /** @test */
    public function uploads_checker_filters_correctly(): void
    {
        $checker = new UploadsChecker();

        $definitions = collect([
            (object)['id' => 1, 'type' => 'uploads', 'tag_id' => null, 'threshold' => 1],
            (object)['id' => 2, 'type' => 'uploads', 'tag_id' => null, 'threshold' => 42],
            (object)['id' => 3, 'type' => 'objects', 'tag_id' => null, 'threshold' => 1], // Wrong type
            (object)['id' => 4, 'type' => 'uploads', 'tag_id' => 123, 'threshold' => 1], // Has tag_id
        ]);

        $counts = ['uploads' => 42];
        $alreadyUnlocked = [];

        $toUnlock = $checker->check($counts, $definitions, $alreadyUnlocked);

        $this->assertEquals([1, 2], $toUnlock);
    }

    /** @test */
    public function categories_checker_counts_unique_categories(): void
    {
        $checker = new CategoriesChecker();

        $definitions = collect([
            (object)['id' => 1, 'type' => 'categories', 'tag_id' => null, 'threshold' => 1],
            (object)['id' => 2, 'type' => 'categories', 'tag_id' => null, 'threshold' => 3],
            (object)['id' => 3, 'type' => 'categories', 'tag_id' => null, 'threshold' => 5],
        ]);

        // 3 unique categories with different counts
        $counts = [
            'categories' => [
                'food' => 100,
                'softdrinks' => 50,
                'alcohol' => 25,
            ]
        ];

        $toUnlock = $checker->check($counts, $definitions, []);

        // Should unlock 1 and 3 (count of unique categories = 3)
        $this->assertEquals([1, 2], $toUnlock);
    }

    /** @test */
    public function objects_checker_handles_missing_tag_ids(): void
    {
        $checker = new ObjectsChecker();

        $definitions = collect([
            (object)['id' => 1, 'type' => 'objects', 'tag_id' => null, 'threshold' => 10],
            (object)['id' => 2, 'type' => 'object', 'tag_id' => 999, 'threshold' => 5],
        ]);

        $counts = [
            'objects' => [
                'unknown_object' => 15, // No tag ID in database
                'water_bottle' => 10,
            ]
        ];

        $toUnlock = $checker->check($counts, $definitions, []);

        // Should unlock dimension-wide (total = 25)
        $this->assertContains(1, $toUnlock);
        // Should not unlock per-object for unknown
        $this->assertNotContains(2, $toUnlock);
    }

    /** @test */
    public function checker_respects_already_unlocked(): void
    {
        $checker = new UploadsChecker();

        $definitions = collect([
            (object)['id' => 1, 'type' => 'uploads', 'tag_id' => null, 'threshold' => 1],
            (object)['id' => 2, 'type' => 'uploads', 'tag_id' => null, 'threshold' => 42],
        ]);

        $counts = ['uploads' => 100];
        $alreadyUnlocked = [1]; // Already has first achievement

        $toUnlock = $checker->check($counts, $definitions, $alreadyUnlocked);

        $this->assertEquals([2], $toUnlock); // Only unlocks the second
    }

    /** @test */
//    public function optimized_index_building_works(): void
//    {
//        $checker = new ObjectsChecker();
//
//        $definitions = collect([
//            (object)['id' => 1, 'type' => 'objects', 'tag_id' => null, 'threshold' => 10],
//            (object)['id' => 2, 'type' => 'objects', 'tag_id' => null, 'threshold' => 5],
//            (object)['id' => 3, 'type' => 'object', 'tag_id' => 123, 'threshold' => 20],
//            (object)['id' => 4, 'type' => 'object', 'tag_id' => 123, 'threshold' => 10],
//            (object)['id' => 5, 'type' => 'uploads', 'tag_id' => null, 'threshold' => 1], // Wrong type
//        ]);
//
//        $reflection = new \ReflectionClass($checker);
//        $method = $reflection->getMethod('buildOptimizedIndex');
//        $method->setAccessible(true);
//
//        $index = $method->invoke($checker, $definitions, [1]); // 1 is already unlocked
//
//        // Should have two keys
//        $this->assertArrayHasKey('objects:null', $index);
//        $this->assertArrayHasKey('object:123', $index);
//
//        // Should be sorted by threshold
//        $this->assertEquals(5, $index['objects:null'][0]['threshold']);
//        $this->assertEquals(10, $index['object:123'][0]['threshold']);
//        $this->assertEquals(20, $index['object:123'][1]['threshold']);
//
//        // Should not include unlocked or wrong type
//        $this->assertCount(1, $index['objects:null']); // Only id=2, not id=1
//        $this->assertCount(2, $index['object:123']);
//    }
}
