<?php

namespace App\Application\Pricing\Rules\Get;

use App\Application\Pricing\DTOs\CourtPriceRuleDto;
use App\Domain\Pricing\Repositories\CourtPriceRepository;

final class GetCourtPriceRulesHandler
{
    public function __construct(
        private CourtPriceRepository $prices,
    ) {}

    public function handle(
        GetCourtPriceRulesQuery $query
    ): array {

        $rules = $this->prices->findRulesByPriceId(
            $query->courtPriceId
        );

        return array_map(
            CourtPriceRuleDto::fromDomain(...),
            $rules
        );
    }
}
