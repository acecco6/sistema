<?php

namespace App\Domain\Payments\Enums;

enum RefundStatus: string
{
    case PENDING = 'PENDING';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
