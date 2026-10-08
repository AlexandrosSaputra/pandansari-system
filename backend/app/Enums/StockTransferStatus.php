<?php

namespace App\Enums;

enum StockTransferStatus: string
{
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Rejected = 'REJECTED';
}
