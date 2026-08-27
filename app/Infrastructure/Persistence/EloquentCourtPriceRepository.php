<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Pricing\Entities\CourtPrice as DomainCourtPrice;
use App\Domain\Pricing\Entities\CourtPriceRule as DomainCourtPriceRule;
use App\Domain\Pricing\Repositories\CourtPriceRepository;
use App\Models\CourtPrice as EloquentCourtPrice;
use App\Models\CourtPriceRule as EloquentCourtPriceRule;

final class EloquentCourtPriceRepository implements CourtPriceRepository
{
    public function findForCourt(int $branchId, int $tipoCourtId): ?DomainCourtPrice
    {
        $price = EloquentCourtPrice::query()
            ->where('branch_id', $branchId)
            ->where('tipo_court_id', $tipoCourtId)
            ->first();

        return $price
            ? $this->toDomainPrice($price)
            : null;
    }

    public function findActiveRules(int $courtPriceId): array
    {
        return EloquentCourtPriceRule::query()
            ->where('court_price_id', $courtPriceId)
            ->where('active', true)
            ->get()
            ->map(
                fn(EloquentCourtPriceRule $rule) =>
                $this->toDomainRule($rule)
            )
            ->all();
    }

    private function toDomainPrice(EloquentCourtPrice $price): DomainCourtPrice
    {
        return new DomainCourtPrice(
            id: $price->id,
            branchId: $price->branch_id,
            tipoCourtId: $price->tipo_court_id,
            price: $price->price,
            active: $price->active,
        );
    }

    private function toDomainRule(EloquentCourtPriceRule $rule): DomainCourtPriceRule
    {
        return new DomainCourtPriceRule(
            id: $rule->id,
            courtPriceId: $rule->court_price_id,
            name: $rule->name,
            price: $rule->price,
            dayOfWeek: $rule->day_of_week,
            specificDate: $rule->specific_date?->format('Y-m-d'),
            startTime: $rule->start_time,
            endTime: $rule->end_time,
            priority: $rule->priority,
            startsAt: $rule->starts_at?->format('Y-m-d H:i:s'),
            endsAt: $rule->ends_at?->format('Y-m-d H:i:s'),
            active: $rule->active,
        );
    }
}
