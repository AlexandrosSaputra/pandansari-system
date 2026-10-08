<?php

namespace App\Enums;

enum StockMovementType: string
{
    case StockIn = 'STOCK_IN';
    case StockOut = 'STOCK_OUT';
    case TransferIn = 'TRANSFER_IN';
    case TransferOut = 'TRANSFER_OUT';
    case Sale = 'SALE';
}
