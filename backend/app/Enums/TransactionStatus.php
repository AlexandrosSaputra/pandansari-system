<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Cart = 'CART';
    case Paid = 'PAID';
}
