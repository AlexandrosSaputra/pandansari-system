<?php

namespace App\Enums;

enum SafetyStockMode: string
{
    case Manual = 'MANUAL';
    case Calculated = 'CALCULATED';
}
