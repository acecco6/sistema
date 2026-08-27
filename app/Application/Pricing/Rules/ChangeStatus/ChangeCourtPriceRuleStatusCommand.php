<?php

namespace App\Application\Pricing\Rules\ChangeStatus;

final readonly class ChangeCourtPriceRuleStatusCommand
{
    public function __construct(
        public int $id,
        public bool $active,
    ) {}
}
