<?php

namespace Tests\Unit\Enums;

use App\Enums\XpScore;
use PHPUnit\Framework\TestCase;

class XpScoreObjectXpTest extends TestCase
{
    /** @test */
    public function bags_litter_returns_10_xp(): void
    {
        $this->assertSame(10, XpScore::getObjectXp('bags_litter'));
    }

    /** @test */
    public function dumping_small_returns_10_xp(): void
    {
        $this->assertSame(10, XpScore::getObjectXp('dumping_small'));
    }

    /** @test */
    public function dumping_medium_returns_25_xp(): void
    {
        $this->assertSame(25, XpScore::getObjectXp('dumping_medium'));
    }

    /** @test */
    public function dumping_large_returns_50_xp(): void
    {
        $this->assertSame(50, XpScore::getObjectXp('dumping_large'));
    }

    /** @test */
    public function unknown_object_returns_default_1_xp(): void
    {
        $this->assertSame(1, XpScore::getObjectXp('butts'));
        $this->assertSame(1, XpScore::getObjectXp('wrapper'));
        $this->assertSame(1, XpScore::getObjectXp('bottle'));
    }

    /** @test */
    public function old_keys_no_longer_match_special_xp(): void
    {
        // These old keys should fall through to default (1 XP)
        $this->assertSame(1, XpScore::getObjectXp('small'));
        $this->assertSame(1, XpScore::getObjectXp('medium'));
        $this->assertSame(1, XpScore::getObjectXp('large'));
        $this->assertSame(1, XpScore::getObjectXp('bagsLitter'));
    }

    /** @test */
    public function dumping_with_type_returns_correct_xp(): void
    {
        $this->assertSame(10, XpScore::getObjectXp('dumping', 'small'));
        $this->assertSame(25, XpScore::getObjectXp('dumping', 'medium'));
        $this->assertSame(50, XpScore::getObjectXp('dumping', 'large'));
    }

    /** @test */
    public function dumping_without_type_returns_default_xp(): void
    {
        $this->assertSame(1, XpScore::getObjectXp('dumping'));
        $this->assertSame(1, XpScore::getObjectXp('dumping', null));
    }
}
