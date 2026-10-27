<?php

namespace App\Support;

use App\Models\ForecastRun;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ForecastDataFreshness
{
    /** @return array{status: string, age_days: int, gap_weeks: int, requires_warning: bool} */
    public function forRun(ForecastRun $run): array
    {
        $assessedAt = $run->created_at?->toImmutable()
            ?? CarbonImmutable::now(config('forecast.timezone'));

        return $this->assess($run->history_through, $run->forecast_start, $assessedAt);
    }

    /** @return array{status: string, age_days: int, gap_weeks: int, requires_warning: bool} */
    public function assess(
        CarbonInterface $historyThrough,
        CarbonInterface $forecastStart,
        ?CarbonInterface $assessedAt = null,
    ): array {
        $assessedAt ??= CarbonImmutable::now(config('forecast.timezone'));
        $ageDays = max(0, (int) $historyThrough->startOfDay()->diffInDays($assessedAt->startOfDay(), false));
        $gapDays = max(0, (int) $historyThrough->addDay()->startOfDay()->diffInDays($forecastStart->startOfDay(), false));
        $status = match (true) {
            $ageDays <= 7 => 'fresh',
            $ageDays <= 28 => 'aging',
            default => 'stale',
        };

        return [
            'status' => $status,
            'age_days' => $ageDays,
            'gap_weeks' => intdiv($gapDays, 7),
            'requires_warning' => $status !== 'fresh',
        ];
    }
}
