<?php

namespace App\Application\Pricing\Rules\ChangeStatus;

use App\Application\Pricing\DTOs\CourtPriceRuleDto;
use App\Domain\Pricing\Exceptions\CourtPriceRuleNotFoundException;
use App\Domain\Pricing\Repositories\CourtPriceRepository;

final class ChangeCourtPriceRuleStatusHandler
{
    public function __construct(
        private CourtPriceRepository $prices,
    ) {}

    public function handle(
        ChangeCourtPriceRuleStatusCommand $command
    ): CourtPriceRuleDto {

        $rule = $this->prices->findRuleById(
            $command->id
        );

        if ($rule === null) {
            throw new CourtPriceRuleNotFoundException();
        }

        if ($command->active) {
            $rule->activate();
        } else {
            $rule->deactivate();
        }

        return CourtPriceRuleDto::fromDomain(
            $this->prices->updateRule($rule)
        );
    }
}
