<?php

namespace Tests\Unit;

use App\Enums\BoostTier;
use PHPUnit\Framework\TestCase;

class BoostTierTest extends TestCase
{
    public function test_prices_match_packages(): void
    {
        $this->assertSame(500, BoostTier::Basic->priceCents());
        $this->assertSame(1500, BoostTier::Intermediate->priceCents());
        $this->assertSame(2500, BoostTier::Advanced->priceCents());
    }

    public function test_weight_orders_advanced_highest(): void
    {
        $this->assertGreaterThan(BoostTier::Intermediate->weight(), BoostTier::Advanced->weight());
        $this->assertGreaterThan(BoostTier::Basic->weight(), BoostTier::Intermediate->weight());
    }

    public function test_only_intermediate_and_advanced_float_in_grid(): void
    {
        $this->assertFalse(BoostTier::Basic->floatsInGrid());
        $this->assertTrue(BoostTier::Intermediate->floatsInGrid());
        $this->assertTrue(BoostTier::Advanced->floatsInGrid());
    }
}
