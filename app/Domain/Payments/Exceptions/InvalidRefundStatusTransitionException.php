<?php

namespace App\Domain\Payments\Exceptions;

use App\Domain\Payments\Enums\RefundStatus;
use App\Shared\Exceptions\DomainException;

final class InvalidRefundStatusTransitionException extends DomainException
{
    public function __construct(
        RefundStatus $currentStatus,
        RefundStatus $targetStatus,
    ) {
        parent::__construct(
            message: sprintf(
                'No se puede cambiar una devolución de %s a %s.',
                $currentStatus->value,
                $targetStatus->value,
            ),
            code: 422,
        );
    }
}
