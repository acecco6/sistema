<?php

namespace App\Domain\Pricing\Repositories;

use App\Domain\Pricing\Entities\CourtPrice;
use App\Domain\Pricing\Entities\CourtPriceRule;

interface CourtPriceRepository
{
    public function findForCourt(int $branchId, int $tipoCourtId): ?CourtPrice;

    /**
     * @return CourtPriceRule[]
     */
    public function findActiveRules(int $courtPriceId): array;
}
