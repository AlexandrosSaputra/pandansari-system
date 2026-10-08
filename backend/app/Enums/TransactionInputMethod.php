<?php

namespace App\Enums;

enum TransactionInputMethod: string
{
    case Scanner = 'SCANNER';
    case Manual = 'MANUAL';
}
