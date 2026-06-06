<?php

namespace Tests\Unit;

use App\Services\XpService;
use PHPUnit\Framework\TestCase;

class XpServiceTest extends TestCase
{
    public function test_level_thresholds(): void
    {
        $this->assertSame(1, XpService::levelForXp(0));
        $this->assertSame(1, XpService::levelForXp(99));
        $this->assertSame(2, XpService::levelForXp(100));
        $this->assertSame(2, XpService::levelForXp(299));
        $this->assertSame(3, XpService::levelForXp(300));
        $this->assertSame(10, XpService::levelForXp(4500));
        $this->assertSame(10, XpService::levelForXp(999999));
    }

    public function test_fee_discount_by_level(): void
    {
        $this->assertSame(500, XpService::feeBpsForLevel(1, 500)); // 5%
        $this->assertSame(480, XpService::feeBpsForLevel(2, 500)); // 4.8%
        $this->assertSame(320, XpService::feeBpsForLevel(10, 500)); // 3.2%
        $this->assertSame(300, XpService::feeBpsForLevel(50, 500)); // piso 3%
    }

    public function test_progress_shape(): void
    {
        $progress = XpService::progress(150);
        $this->assertSame(2, $progress['level']);
        $this->assertSame(150, $progress['xp']);
        $this->assertSame(100, $progress['level_floor']);
        $this->assertSame(300, $progress['next_level_xp']);
    }
}
