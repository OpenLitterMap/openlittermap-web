<?php

namespace Tests\Unit\Services;

use App\Services\LevelService;
use Tests\TestCase;

class LevelServiceTest extends TestCase
{
    /** @test */
    public function zero_xp_returns_level_1_beginner(): void
    {
        $result = LevelService::getUserLevel(0);

        $this->assertEquals(1, $result['level']);
        $this->assertEquals('Beginner', $result['title']);
        $this->assertEquals(0, $result['xp']);
        $this->assertEquals(0, $result['xp_into_level']);
        $this->assertEquals(100, $result['xp_for_next']);
        $this->assertEquals(100, $result['xp_remaining']);
        $this->assertEquals(0, $result['progress_percent']);
    }

    /** @test */
    public function exactly_100_xp_reaches_level_2(): void
    {
        // Level 1 requires 100 XP. At 100 XP, user is now working on level 2.
        $result = LevelService::getUserLevel(100);

        $this->assertEquals(2, $result['level']);
        $this->assertEquals('Observer', $result['title']);
        $this->assertEquals(0, $result['xp_into_level']);
        // Level 2 requires round(100 * 1.5^1) = 150
        $this->assertEquals(150, $result['xp_for_next']);
    }

    /** @test */
    public function level_3_at_250_xp(): void
    {
        // Level 1: 100, Level 2: 150. Cumulative = 250. At 250 → level 3.
        $result = LevelService::getUserLevel(250);

        $this->assertEquals(3, $result['level']);
        $this->assertEquals('Field Observer', $result['title']);
        $this->assertEquals(0, $result['xp_into_level']);
    }

    /** @test */
    public function partial_progress_within_a_level(): void
    {
        // 50 XP is halfway through level 1 (which requires 100)
        $result = LevelService::getUserLevel(50);

        $this->assertEquals(1, $result['level']);
        $this->assertEquals(50, $result['xp_into_level']);
        $this->assertEquals(50, $result['xp_remaining']);
        $this->assertEquals(50, $result['progress_percent']);
    }

    /** @test */
    public function high_xp_returns_correct_level(): void
    {
        // Test with a substantial XP value
        // Level thresholds: 100, 150, 225, 338, 506, 759, ...
        // Cumulative: 100, 250, 475, 813, 1319, 2078, ...
        $result = LevelService::getUserLevel(1000);

        // 1000 is between cumulative 813 (level 4 complete) and 1319 (level 5 complete)
        $this->assertEquals(5, $result['level']);
        $this->assertEquals('Field Recorder', $result['title']);
        $this->assertEquals(1000 - 813, $result['xp_into_level']); // 187
    }

    /** @test */
    public function max_level_caps_at_50(): void
    {
        // Cumulative XP for all 50 levels is ~127.5 billion
        $result = LevelService::getUserLevel(200000000000);

        $this->assertEquals(50, $result['level']);
        $this->assertEquals('Founder', $result['title']);
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
