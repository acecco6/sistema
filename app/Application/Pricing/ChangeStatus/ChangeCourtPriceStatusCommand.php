<?php

namespace App\Application\Pricing\ChangeStatus;

final readonly class ChangeCourtPriceStatusCommand
{
    public function __construct(
        public int $id,
        public bool $active,
    ) {}
}
