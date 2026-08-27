<?php

namespace App\Application\Pricing\Rules\Update;

use App\Application\Pricing\DTOs\CourtPriceRuleDto;
use App\Domain\Pricing\Exceptions\CourtPriceRuleNotFoundException;
use App\Domain\Pricing\Repositories\CourtPriceRepository;

final class UpdateCourtPriceRuleHandler
{
    public function __construct(
        private CourtPriceRepository $prices,
    ) {}

    public function handle(
        UpdateCourtPriceRuleCommand $command
    ): CourtPriceRuleDto {

        $rule = $this->prices->findRuleById(
            $command->id
        );

        if ($rule === null) {
            throw new CourtPriceRuleNotFoundException();
        }

        $rule->update(
            name: $command->name,
            price: $command->price,
            dayOfWeek: $command->dayOfWeek,
            specificDate: $command->specificDate,
            startTime: $command->startTime,
            endTime: $command->endTime,
            priority: $command->priority,
            startsAt: $command->startsAt,
            endsAt: $command->endsAt,
        );

        return CourtPriceRuleDto::fromDomain(
            $this->prices->updateRule($rule)
        );
    }
}
