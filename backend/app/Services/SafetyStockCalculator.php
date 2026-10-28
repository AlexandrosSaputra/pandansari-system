<?php

namespace App\Services;

use InvalidArgumentException;

class SafetyStockCalculator
{
    /** @var array<string, float> */
    private const array Z_SCORES = [
        '90.00' => 1.282,
        '95.00' => 1.645,
        '97.50' => 1.960,
        '99.00' => 2.326,
    ];

    public function calculate(float $rmse, int $leadTimeDays, float $targetServiceLevel): int
    {
        $key = number_format($targetServiceLevel, 2, '.', '');
        $zScore = self::Z_SCORES[$key] ?? null;

        if ($zScore === null) {
            throw new InvalidArgumentException("Unsupported target service level: {$targetServiceLevel}");
        }

        $coverageWeeks = max(1.0, $leadTimeDays / 7);

        return max(0, (int) ceil($zScore * max(0, $rmse) * sqrt($coverageWeeks)));
    }
}
