<?php

namespace Tests\Feature\Achievements;

use App\Models\Achievements\Achievement;
use App\Services\Achievements\Checkers\TypesChecker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TypesCheckerTest extends TestCase
{
    private TypesChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('achievements')->delete();
        $this->checker = app(TypesChecker::class);
    }

    public function test_it_unlocks_dimension_wide_achievement_at_threshold(): void
    {
        $achievement = Achievement::create([
            'type' => 'types',
            'tag_id' => null,
            'threshold' => 5,
        ]);

        $counts = ['types' => ['beer' => 3, 'wine' => 3]];
        $definitions = Achievement::all();

        $unlocked = $this->checker->check($counts, $definitions, []);

        $this->assertContains($achievement->id, $unlocked);
    }

    public function test_it_does_not_unlock_below_threshold(): void
    {
        Achievement::create([
            'type' => 'types',
            'tag_id' => null,
            'threshold' => 100,
        ]);

        $counts = ['types' => ['beer' => 2]];
        $definitions = Achievement::all();

        $unlocked = $this->checker->check($counts, $definitions, []);

        $this->assertEmpty($unlocked);
    }

    public function test_it_skips_already_unlocked_achievements(): void
    {
        $achievement = Achievement::create([
            'type' => 'types',
            'tag_id' => null,
            'threshold' => 1,
        ]);

        $counts = ['types' => ['beer' => 5]];
        $definitions = Achievement::all();

        $unlocked = $this->checker->check($counts, $definitions, [$achievement->id]);

        $this->assertEmpty($unlocked);
    }

    public function test_it_returns_empty_when_no_types_in_counts(): void
    {
        Achievement::create([
            'type' => 'types',
            'tag_id' => null,
            'threshold' => 1,
        ]);

        $counts = ['objects' => ['butts' => 5]];
        $definitions = Achievement::all();

        $unlocked = $this->checker->check($counts, $definitions, []);

        $this->assertEmpty($unlocked);
    }

    public function test_it_unlocks_per_type_achievement(): void
    {
        // Get a real type from the DB
        $typeId = DB::table('litter_object_types')->first()?->id;

        if (! $typeId) {
            $this->markTestSkipped('No litter_object_types in test DB');
        }

        $typeKey = DB::table('litter_object_types')->where('id', $typeId)->value('key');

        $achievement = Achievement::create([
            'type' => 'type',
            'tag_id' => $typeId,
            'threshold' => 3,
        ]);

        $counts = ['types' => [$typeKey => 5]];
        $definitions = Achievement::all();

        $unlocked = $this->checker->check($counts, $definitions, []);

        $this->assertContains($achievement->id, $unlocked);
    }
}
