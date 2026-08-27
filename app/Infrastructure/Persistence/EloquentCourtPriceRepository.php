<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Pricing\Entities\CourtPrice as DomainCourtPrice;
use App\Domain\Pricing\Entities\CourtPriceRule as DomainCourtPriceRule;
use App\Domain\Pricing\Repositories\CourtPriceRepository;
use App\Models\CourtPrice as EloquentCourtPrice;
use App\Models\CourtPriceRule as EloquentCourtPriceRule;

final class EloquentCourtPriceRepository implements CourtPriceRepository
{
    /*
    |--------------------------------------------------------------------------
    | CourtPrice
    |--------------------------------------------------------------------------
    */

    public function findById(int $id): ?DomainCourtPrice
    {
        $price = EloquentCourtPrice::find($id);

        return $price
            ? $this->toDomainPrice($price)
            : null;
    }

    public function findForCourt(
        int $branchId,
        int $tipoCourtId
    ): ?DomainCourtPrice {
        $price = EloquentCourtPrice::query()
            ->where('branch_id', $branchId)
            ->where('tipo_court_id', $tipoCourtId)
            ->first();

        return $price
            ? $this->toDomainPrice($price)
            : null;
    }

    public function findByBranchId(int $branchId): array
    {
        return EloquentCourtPrice::query()
            ->where('branch_id', $branchId)
            ->get()
            ->map(
                fn(EloquentCourtPrice $price) =>
                $this->toDomainPrice($price)
            )
            ->all();
    }

    public function save(
        DomainCourtPrice $price
    ): DomainCourtPrice {
        $eloquentPrice = EloquentCourtPrice::create([
            'branch_id' => $price->getBranchId(),
            'tipo_court_id' => $price->getTipoCourtId(),
            'price' => $price->getPrice(),
            'active' => $price->isActive(),
        ]);

        return $this->toDomainPrice(
            $eloquentPrice
        );
    }

    public function update(
        DomainCourtPrice $price
    ): DomainCourtPrice {
        $eloquentPrice = EloquentCourtPrice::find(
            $price->getId()
        );

        if ($eloquentPrice === null) {
            throw new \RuntimeException(
                'No se encontró el precio para actualizar.'
            );
        }

        $eloquentPrice->update([
            'branch_id' => $price->getBranchId(),
            'tipo_court_id' => $price->getTipoCourtId(),
            'price' => $price->getPrice(),
            'active' => $price->isActive(),
        ]);

        return $this->toDomainPrice(
            $eloquentPrice->refresh()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CourtPriceRule / Promotions
    |--------------------------------------------------------------------------
    */

    public function findRuleById(
        int $id
    ): ?DomainCourtPriceRule {
        $rule = EloquentCourtPriceRule::find(
            $id
        );

        return $rule
            ? $this->toDomainRule($rule)
            : null;
    }

    public function findRulesByPriceId(
        int $courtPriceId
    ): array {
        return EloquentCourtPriceRule::query()
            ->where(
                'court_price_id',
                $courtPriceId
            )
            ->get()
            ->map(
                fn(EloquentCourtPriceRule $rule) =>
                $this->toDomainRule($rule)
            )
            ->all();
    }

    public function findActiveRules(
        int $courtPriceId
    ): array {
        return EloquentCourtPriceRule::query()
            ->where(
                'court_price_id',
                $courtPriceId
            )
            ->where('active', true)
            ->get()
            ->map(
                fn(EloquentCourtPriceRule $rule) =>
                $this->toDomainRule($rule)
            )
            ->all();
    }

    public function saveRule(
        DomainCourtPriceRule $rule
    ): DomainCourtPriceRule {
        $eloquentRule = EloquentCourtPriceRule::create([
            'court_price_id' => $rule->getCourtPriceId(),
            'name' => $rule->getName(),
            'price' => $rule->getPrice(),
            'day_of_week' => $rule->getDayOfWeek(),
            'specific_date' => $rule->getSpecificDate(),
            'start_time' => $rule->getStartTime(),
            'end_time' => $rule->getEndTime(),
            'priority' => $rule->getPriority(),
            'starts_at' => $rule->getStartsAt(),
            'ends_at' => $rule->getEndsAt(),
            'active' => $rule->isActive(),
        ]);

        return $this->toDomainRule(
            $eloquentRule
        );
    }

    public function updateRule(
        DomainCourtPriceRule $rule
    ): DomainCourtPriceRule {
        $eloquentRule = EloquentCourtPriceRule::find(
            $rule->getId()
        );

        if ($eloquentRule === null) {
            throw new \RuntimeException(
                'No se encontró la promoción para actualizar.'
            );
        }

        $eloquentRule->update([
            'court_price_id' => $rule->getCourtPriceId(),
            'name' => $rule->getName(),
            'price' => $rule->getPrice(),
            'day_of_week' => $rule->getDayOfWeek(),
            'specific_date' => $rule->getSpecificDate(),
            'start_time' => $rule->getStartTime(),
            'end_time' => $rule->getEndTime(),
            'priority' => $rule->getPriority(),
            'starts_at' => $rule->getStartsAt(),
            'ends_at' => $rule->getEndsAt(),
            'active' => $rule->isActive(),
        ]);

        return $this->toDomainRule(
            $eloquentRule->refresh()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Mappers
    |--------------------------------------------------------------------------
    */

    private function toDomainPrice(
        EloquentCourtPrice $price
    ): DomainCourtPrice {
        return new DomainCourtPrice(
            id: $price->id,
            branchId: $price->branch_id,
            tipoCourtId: $price->tipo_court_id,
            price: $price->price,
            active: $price->active,
        );
    }

    private function toDomainRule(
        EloquentCourtPriceRule $rule
    ): DomainCourtPriceRule {
        return new DomainCourtPriceRule(
            id: $rule->id,
            courtPriceId: $rule->court_price_id,
            name: $rule->name,
            price: $rule->price,
            dayOfWeek: $rule->day_of_week,

            specificDate: $rule->specific_date?->format(
                'Y-m-d'
            ),

            startTime: $rule->start_time,

            endTime: $rule->end_time,

            priority: $rule->priority,

            startsAt: $rule->starts_at?->format(
                'Y-m-d H:i:s'
            ),

            endsAt: $rule->ends_at?->format(
                'Y-m-d H:i:s'
            ),

            active: $rule->active,
        );
    }
}
