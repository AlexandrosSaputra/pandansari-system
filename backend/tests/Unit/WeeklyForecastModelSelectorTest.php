<?php

namespace Tests\Unit;

use App\Services\WeeklyForecastModelSelector;
use InvalidArgumentException;
use Tests\TestCase;

class WeeklyForecastModelSelectorTest extends TestCase
{
    public function test_it_evaluates_all_candidates_when_three_years_of_seasonal_history_are_available(): void
    {
        $pattern = array_map(fn (int $week): float => (float) (($week % 13) + 1), range(0, 51));
        $values = [...$pattern, ...$pattern, ...$pattern, ...$pattern];

        $evaluation = app(WeeklyForecastModelSelector::class)->evaluate($values);

        $this->assertSame('seasonal_52', $evaluation['model']);
        $this->assertSame(0.0, $evaluation['mae']);
        $this->assertSame(
            WeeklyForecastModelSelector::MODELS,
            array_keys($evaluation['candidates']),
        );
    }

    public function test_moving_average_projection_uses_previous_predictions_recursively(): void
    {
        $predictions = app(WeeklyForecastModelSelector::class)->project(
            [1.0, 2.0, 3.0, 4.0],
            2,
            'moving_average_4',
        );

        $this->assertEqualsWithDelta(2.5, $predictions[0], 0.0001);
        $this->assertEqualsWithDelta(2.875, $predictions[1], 0.0001);
    }

    public function test_it_rejects_an_unknown_projection_model(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(WeeklyForecastModelSelector::class)->project([1.0], 1, 'unknown');
    }
}
