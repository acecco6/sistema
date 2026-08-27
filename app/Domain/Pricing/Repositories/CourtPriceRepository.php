<?php

namespace App\Domain\Pricing\Repositories;

use App\Domain\Pricing\Entities\CourtPrice;
use App\Domain\Pricing\Entities\CourtPriceRule;

interface CourtPriceRepository
{
    public function findById(int $id): ?CourtPrice;

    public function findForCourt(int $branchId, int $tipoCourtId): ?CourtPrice;

    /**
     * @return CourtPrice[]
     */
    public function findByBranchId(int $branchId): array;

    public function save(CourtPrice $price): CourtPrice;

    public function update(CourtPrice $price): CourtPrice;


    // Promotions

    public function findRuleById(int $id): ?CourtPriceRule;

    /**
     * @return CourtPriceRule[]
     */
    public function findRulesByPriceId(int $courtPriceId): array;

    /**
     * @return CourtPriceRule[]
     */
    public function findActiveRules(int $courtPriceId): array;

    public function saveRule(CourtPriceRule $rule): CourtPriceRule;

    public function updateRule(CourtPriceRule $rule): CourtPriceRule;
}
