<?php

namespace Tests\Unit;

use App\Support\ForecastDataFreshness;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ForecastDataFreshnessTest extends TestCase
{
    /** @return array<string, array{string, string, bool}> */
    public static function freshnessStatuses(): array
    {
        return [
            'fresh through seven days' => ['2026-10-20', 'fresh', false],
            'aging after seven days' => ['2026-10-19', 'aging', true],
            'stale after twenty eight days' => ['2026-09-28', 'stale', true],
        ];
    }

    #[DataProvider('freshnessStatuses')]
    public function test_it_classifies_historical_data_age(
        string $historyThrough,
        string $expectedStatus,
        bool $requiresWarning,
    ): void {
        $freshness = app(ForecastDataFreshness::class)->assess(
            CarbonImmutable::parse($historyThrough),
            CarbonImmutable::parse('2026-11-02'),
            CarbonImmutable::parse('2026-10-27'),
        );

        $this->assertSame($expectedStatus, $freshness['status']);
        $this->assertSame($requiresWarning, $freshness['requires_warning']);
    }

    public function test_it_counts_complete_unobserved_weeks_before_the_target(): void
    {
        $freshness = app(ForecastDataFreshness::class)->assess(
            CarbonImmutable::parse('2026-08-30'),
            CarbonImmutable::parse('2026-11-02'),
            CarbonImmutable::parse('2026-10-27'),
        );

        $this->assertSame(58, $freshness['age_days']);
        $this->assertSame(9, $freshness['gap_weeks']);
    }
}
