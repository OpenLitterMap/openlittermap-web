<?php

namespace Tests\Unit\Services;

use App\Services\LevelService;
use Tests\TestCase;

class LevelServiceTest extends TestCase
{
    /** @test */
    public function zero_xp_returns_level_1_complete_noob(): void
    {
        $result = LevelService::getUserLevel(0);

        $this->assertEquals(1, $result['level']);
        $this->assertEquals('Complete Noob', $result['title']);
        $this->assertEquals(0, $result['xp']);
        $this->assertEquals(0, $result['xp_into_level']);
        $this->assertEquals(100, $result['xp_for_next']);
        $this->assertEquals(100, $result['xp_remaining']);
        $this->assertEquals(0, $result['progress_percent']);
    }

    /** @test */
    public function exactly_100_xp_reaches_still_a_noob(): void
    {
        $result = LevelService::getUserLevel(100);

        $this->assertEquals(2, $result['level']);
        $this->assertEquals('Less of a Noob', $result['title']);
        $this->assertEquals(0, $result['xp_into_level']);
        // Next threshold is 500, so xp_for_next = 500 - 100 = 400
        $this->assertEquals(400, $result['xp_for_next']);
    }

    /** @test */
    public function post_noob_at_500_xp(): void
    {
        $result = LevelService::getUserLevel(500);

        $this->assertEquals(3, $result['level']);
        $this->assertEquals('Post-Noob', $result['title']);
        $this->assertEquals(0, $result['xp_into_level']);
    }

    /** @test */
    public function partial_progress_within_a_level(): void
    {
        // 50 XP is halfway through level 1 (0–100)
        $result = LevelService::getUserLevel(50);

        $this->assertEquals(1, $result['level']);
        $this->assertEquals(50, $result['xp_into_level']);
        $this->assertEquals(50, $result['xp_remaining']);
        $this->assertEquals(50, $result['progress_percent']);
    }

    /** @test */
    public function litter_wizard_at_1000_xp(): void
    {
        $result = LevelService::getUserLevel(1000);

        $this->assertEquals(4, $result['level']);
        $this->assertEquals('Litter Wizard', $result['title']);
        $this->assertEquals(0, $result['xp_into_level']);
        // Next threshold is 5000, so xp_for_next = 4000
        $this->assertEquals(4000, $result['xp_for_next']);
    }

    /** @test */
    public function max_level_caps_at_superintelligent_littermaster(): void
    {
        $result = LevelService::getUserLevel(5000000);

        $this->assertEquals(12, $result['level']);
        $this->assertEquals('SuperIntelligent LitterMaster', $result['title']);
        $this->assertEquals(100, $result['progress_percent']);
        $this->assertEquals(0, $result['xp_remaining']);
    }

    /** @test */
    public function all_required_keys_are_present(): void
    {
        $result = LevelService::getUserLevel(42);

        $this->assertArrayHasKey('level', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('xp', $result);
        $this->assertArrayHasKey('xp_into_level', $result);
        $this->assertArrayHasKey('xp_for_next', $result);
        $this->assertArrayHasKey('xp_remaining', $result);
        $this->assertArrayHasKey('progress_percent', $result);
    }
}
