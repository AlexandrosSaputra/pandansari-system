<?php

namespace App\Console\Commands;

use App\Services\HistoricalSalesSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('forecast:sync-sales {--through= : Date within the final week to synchronize (YYYY-MM-DD)} {--check-only : Detect missing observations without writing data}')]
#[Description('Synchronize paid application transactions into weekly forecasting history')]
class SyncHistoricalSales extends Command
{
    public function handle(HistoricalSalesSyncService $sync): int
    {
        $through = $this->option('through');

        if (is_string($through) && $through !== '') {
            $parsed = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $through,
                config('forecast.timezone'),
            );
            if ($parsed === false || $parsed->format('Y-m-d') !== $through) {
                $this->components->error('The --through option must use YYYY-MM-DD format.');

                return self::INVALID;
            }
            $through = $parsed;
        } else {
            $through = null;
        }

        try {
            $summary = $sync->sync($through, (bool) $this->option('check-only'));
        } catch (Throwable $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }

        if ($summary['start_week'] === null) {
            $this->components->info('No completed application-sales weeks require synchronization.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '%s %d observations across %d series and %d weeks (%s through %s).',
            $summary['check_only'] ? 'Checked' : 'Synchronized',
            $summary['expected_observations'],
            $summary['series'],
            $summary['weeks'],
            $summary['start_week'],
            $summary['through_week'],
        ));

        if ($summary['missing_observations_after'] > 0) {
            $this->components->warn(sprintf(
                'Detected %d missing observations across %d weeks.',
                $summary['missing_observations_after'],
                $summary['missing_weeks_after'],
            ));

            return self::FAILURE;
        }

        if ($summary['missing_observations_before'] > 0) {
            $this->components->info(sprintf(
                'Repaired %d missing observations across %d weeks.',
                $summary['missing_observations_before'],
                $summary['missing_weeks_before'],
            ));
        }

        return self::SUCCESS;
    }
}
