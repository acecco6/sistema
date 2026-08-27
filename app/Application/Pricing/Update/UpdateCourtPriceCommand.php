<?php

namespace App\Application\Pricing\Update;

final readonly class UpdateCourtPriceCommand
{
    public function __construct(
        public int $id,
        public string $price,
    ) {}
}
