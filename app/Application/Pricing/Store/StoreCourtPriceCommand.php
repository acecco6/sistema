<?php

namespace App\Application\Pricing\Store;

final readonly class StoreCourtPriceCommand
{
    public function __construct(
        public int $branchId,
        public int $tipoCourtId,
        public string $price,
    ) {}
}
