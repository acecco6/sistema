<?php

namespace App\Application\Pricing\Resolver;

use DateTimeImmutable;

final readonly class PriceSegment
{
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $hourlyPrice,
        public string $subtotal,
        public ?int $ruleId = null,
        public ?string $ruleName = null,
    ) {}

    public function minutes(): int
    {
        return (int) (
            ($this->endsAt->getTimestamp()
                - $this->startsAt->getTimestamp())
            / 60
        );
    }
}
