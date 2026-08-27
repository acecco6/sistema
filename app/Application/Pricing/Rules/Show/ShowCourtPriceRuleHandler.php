<?php

namespace App\Application\Pricing\Rules\Show;

use App\Application\Pricing\DTOs\CourtPriceRuleDto;
use App\Domain\Pricing\Exceptions\CourtPriceRuleNotFoundException;
use App\Domain\Pricing\Repositories\CourtPriceRepository;

final class ShowCourtPriceRuleHandler
{
    public function __construct(
        private CourtPriceRepository $prices,
    ) {}

    public function handle(
        ShowCourtPriceRuleQuery $query
    ): CourtPriceRuleDto {

        $rule = $this->prices->findRuleById(
            $query->id
        );

        if ($rule === null) {
            throw new CourtPriceRuleNotFoundException();
        }

        return CourtPriceRuleDto::fromDomain($rule);
    }
}
