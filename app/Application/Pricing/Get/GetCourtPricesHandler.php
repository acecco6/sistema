<?php

namespace App\Application\Pricing\Get;

use App\Application\Pricing\DTOs\CourtPriceDto;
use App\Domain\Pricing\Repositories\CourtPriceRepository;

final class GetCourtPricesHandler
{
    public function __construct(
        private CourtPriceRepository $prices,
    ) {}

    public function handle(
        GetCourtPricesQuery $query
    ): array {

        $prices = $this->prices->findByBranchId(
            $query->branchId
        );

        return array_map(
            CourtPriceDto::fromDomain(...),
            $prices
        );
    }
}
