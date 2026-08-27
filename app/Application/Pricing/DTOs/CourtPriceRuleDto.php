<?php

namespace App\Application\Pricing\DTOs;

use App\Domain\Pricing\Entities\CourtPriceRule;

final readonly class CourtPriceRuleDto
{
    public function __construct(
        public ?int $id,
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
        public bool $active,
    ) {}

    public static function fromDomain(
        CourtPriceRule $rule
    ): self {
        return new self(
            id: $rule->getId(),
            courtPriceId: $rule->getCourtPriceId(),
            name: $rule->getName(),
            price: $rule->getPrice(),
            dayOfWeek: $rule->getDayOfWeek(),
            specificDate: $rule->getSpecificDate(),
            startTime: $rule->getStartTime(),
            endTime: $rule->getEndTime(),
            priority: $rule->getPriority(),
            startsAt: $rule->getStartsAt(),
            endsAt: $rule->getEndsAt(),
            active: $rule->isActive(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'court_price_id' => $this->courtPriceId,
            'name' => $this->name,
            'price' => $this->price,
            'day_of_week' => $this->dayOfWeek,
            'specific_date' => $this->specificDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'priority' => $this->priority,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'active' => $this->active,
        ];
    }
}
