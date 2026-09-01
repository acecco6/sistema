<?php

namespace App\Domain\Payments\Enums;

enum PaymentMethod: string
{
    case CASH = 'CASH';
    case TRANSFER = 'TRANSFER';
    case MERCADO_PAGO = 'MERCADO_PAGO';
    case CARD = 'CARD';
    case OTHER = 'OTHER';
}
