<?php

namespace App\Application\Pricing\Rules\Update;

final readonly class UpdateCourtPriceRuleCommand
{
    public function __construct(
        public int $id,
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
