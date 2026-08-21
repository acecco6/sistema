<?php

namespace App\Domain\Branches\Exceptions;

use App\Shared\Exceptions\DomainException;

final class BranchNotFoundException extends DomainException
{
    public function __construct(?int $branchId = null)
    {
        parent::__construct(
            $branchId !== null
                ? "La sucursal con ID {$branchId} no existe."
                : 'La sucursal no existe.',
            404
        );
    }
}
