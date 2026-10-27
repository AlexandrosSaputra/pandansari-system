<?php

namespace App\Enums;

enum StockUrgency: string
{
    case Healthy = 'HEALTHY';
    case NearMinimum = 'NEAR_MINIMUM';
    case BelowMinimum = 'BELOW_MINIMUM';
    case OutOfStock = 'OUT_OF_STOCK';

    public static function forAvailableQuantity(int $availableQuantity, int $minimumStock): self
    {
        return match (true) {
            $availableQuantity <= 0 => self::OutOfStock,
            $availableQuantity <= $minimumStock => self::BelowMinimum,
            $availableQuantity <= $minimumStock * 2 => self::NearMinimum,
            default => self::Healthy,
        };
    }
}
