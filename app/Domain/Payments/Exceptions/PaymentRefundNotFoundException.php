<?php

namespace App\Domain\Payments\Exceptions;

use App\Shared\Exceptions\DomainException;

final class PaymentRefundNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'La devolución no existe.',
            code: 404,
        );
    }
}
