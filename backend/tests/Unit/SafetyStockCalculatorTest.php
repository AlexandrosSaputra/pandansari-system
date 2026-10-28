<?php

namespace Tests\Unit;

use App\Services\SafetyStockCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class SafetyStockCalculatorTest extends TestCase
{
    public function test_it_calibrates_safety_stock_from_error_lead_time_and_service_level(): void
    {
        $calculator = app(SafetyStockCalculator::class);

        $this->assertSame(17, $calculator->calculate(10, 7, 95));
        $this->assertSame(24, $calculator->calculate(10, 14, 95));
        $this->assertSame(24, $calculator->calculate(10, 7, 99));
    }

    public function test_it_uses_a_minimum_one_week_review_period(): void
    {
        $this->assertSame(17, app(SafetyStockCalculator::class)->calculate(10, 0, 95));
    }

    public function test_it_rejects_an_unsupported_service_level(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SafetyStockCalculator::class)->calculate(10, 7, 92);
    }
}
