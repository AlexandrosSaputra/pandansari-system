<?php

namespace App\Support;

use LogicException;

class DecimalMoney
{
    private const MAX_MINOR_UNITS = 999999999999999;

    public function multiply(string $amount, int $quantity): string
    {
        $minorUnits = $this->toMinorUnits($amount);
        abort_if(
            $quantity < 0 || ($minorUnits > 0 && $quantity > intdiv(self::MAX_MINOR_UNITS, $minorUnits)),
            409,
            'Transaction amount exceeds the supported range.',
        );

        return $this->fromMinorUnits($minorUnits * $quantity);
    }

    /**
     * @param  iterable<string>  $amounts
     */
    public function sum(iterable $amounts): string
    {
        $total = 0;

        foreach ($amounts as $amount) {
            $minorUnits = $this->toMinorUnits($amount);
            abort_if($minorUnits > self::MAX_MINOR_UNITS - $total, 409, 'Transaction amount exceeds the supported range.');
            $total += $minorUnits;
        }

        return $this->fromMinorUnits($total);
    }

    private function toMinorUnits(string $amount): int
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw new LogicException('Invalid decimal money value.');
        }

        $whole = (int) $matches[1];
        $fraction = str_pad($matches[2] ?? '', 2, '0');
        abort_if($whole > intdiv(self::MAX_MINOR_UNITS, 100), 409, 'Transaction amount exceeds the supported range.');

        $minorUnits = ($whole * 100) + (int) $fraction;
        abort_if($minorUnits > self::MAX_MINOR_UNITS, 409, 'Transaction amount exceeds the supported range.');

        return $minorUnits;
    }

    private function fromMinorUnits(int $minorUnits): string
    {
        return sprintf('%d.%02d', intdiv($minorUnits, 100), $minorUnits % 100);
    }
}
