<?php

namespace Tests\Unit\Achievements;

use App\Services\Achievements\DslHelpers;
use App\Services\Achievements\Stats;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Tests\TestCase;

class DslHelpersTest extends TestCase
{
    private ExpressionLanguage $el;
    private Stats $stats;

    protected function setUp(): void
    {
        parent::setUp();
        $this->el = new ExpressionLanguage();
        DslHelpers::register($this->el);

        // dummy Stats: streak=0, objects, uploads, no tags
        $this->stats = new Stats(
            userId: 1,
            level: 0,
            xp: 0,
            photosTotal: 5,
            currentStreak: 2,
            localObjects: ['o1' => 3],
            cumulativeObjects: ['o1' => 10],
            summary: ['tags' => [], 'totals' => ['brands' => 0, 'materials' => 0, 'custom_tags' => 0, 'by_category' => []]],
            tod: 'morning',
            dow: 2
        );
    }

    /** @test */
    public function has_object_positive()
    {
        $expr = "hasObject('o1', 2)";
        $result = $this->el->evaluate(
            $this->el->parse($expr, ['stats']),
            ['stats' => $this->stats]
        );
        $this->assertTrue($result);
    }

    /** @test */
    public function has_object_negative()
    {
        $expr = "hasObject('o1', 5)";
        $result = $this->el->evaluate(
            $this->el->parse($expr, ['stats']),
            ['stats' => $this->stats]
        );
        $this->assertFalse($result);
    }

    /** @test */
    public function object_qty_combined()
    {
        $expr = "objectQty('o1')";
        $result = $this->el->evaluate(
            $this->el->parse($expr, ['stats']),
            ['stats' => $this->stats]
        );
        $this->assertEquals(10, $result);
    }

    /** @test */
    public function time_helpers()
    {
        $this->assertEquals(
            'morning',
            $this->el->evaluate(
                $this->el->parse("timeOfDay()", []),
                ['stats' => $this->stats]
            )
        );

        $this->assertFalse(
            $this->el->evaluate(
                $this->el->parse("isWeekend()", []),
                ['stats' => $this->stats]
            )
        );
    }

    /** @test */
    public function stat_count_uploads_and_objects()
    {
        // when localObjects > 0, uploads == sum of cumulative objects
        $expr1 = "statCount('uploads')";
        $expr2 = "statCount('objects')";

        $this->assertEquals(
            10,
            $this->el->evaluate(
                $this->el->parse($expr1, ['stats']),
                ['stats' => $this->stats]
            )
        );
        $this->assertEquals(
            10,
            $this->el->evaluate(
                $this->el->parse($expr2, ['stats']),
                ['stats' => $this->stats]
            )
        );
    }
}
