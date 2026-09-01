<?php

namespace App\Domain\Payments\Enums;

enum FinancialStatus: string
{
    case UNPAID = 'impago';

    case PARTIALLY_PAID = 'parcialmente_pagado';

    case DEPOSIT_PAID = 'pago_senia';

    case PAID = 'pagado';

    case OVERPAID = 'pagado_excedido';
}
