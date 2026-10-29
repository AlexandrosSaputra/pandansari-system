<?php

namespace App\Enums;

enum ForecastDecisionStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
}
