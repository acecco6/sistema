<?php

namespace App\Application\Pricing\Rules\Get;

final readonly class GetCourtPriceRulesQuery
{
    public function __construct(
        public int $courtPriceId,
    ) {}
}
