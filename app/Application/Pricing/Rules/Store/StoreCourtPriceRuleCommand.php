<?php

namespace App\Application\Pricing\Rules\Store;

final readonly class StoreCourtPriceRuleCommand
{
    public function __construct(
        public int $courtPriceId,
        public string $name,
        public string $price,
        public ?int $dayOfWeek,
        public ?string $specificDate,
        public ?string $startTime,
        public ?string $endTime,
        public int $priority,
        public ?string $startsAt,
        public ?string $endsAt,
    ) {}
}
