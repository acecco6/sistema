<?php

namespace App\Application\Pricing\Get;

final readonly class GetCourtPricesQuery
{
    public function __construct(
        public int $branchId,
    ) {}
}
