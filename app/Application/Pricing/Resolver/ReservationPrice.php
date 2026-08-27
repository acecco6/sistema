<?php

namespace App\Application\Pricing\Resolver;

final readonly class ReservationPrice
{
    /**
     * @param PriceSegment[] $segments
     */
    public function __construct(
        public string $total,
        public array $segments,
    ) {}
}
