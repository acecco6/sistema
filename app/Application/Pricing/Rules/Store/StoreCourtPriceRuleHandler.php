<?php

namespace App\Application\Pricing\Rules\Store;

use App\Application\Pricing\DTOs\CourtPriceRuleDto;
use App\Domain\Pricing\Entities\CourtPriceRule;
use App\Domain\Pricing\Exceptions\CourtPriceNotFoundException;
use App\Domain\Pricing\Repositories\CourtPriceRepository;

final class StoreCourtPriceRuleHandler
{
    public function __construct(
        private CourtPriceRepository $prices,
    ) {}

    public function handle(
        StoreCourtPriceRuleCommand $command
    ): CourtPriceRuleDto {

        $price = $this->prices->findById(
            $command->courtPriceId
        );

        if ($price === null) {
            throw new CourtPriceNotFoundException();
        }

        $rule = new CourtPriceRule(
            id: null,
            courtPriceId: $command->courtPriceId,
            name: $command->name,
            price: $command->price,
            dayOfWeek: $command->dayOfWeek,
            specificDate: $command->specificDate,
            startTime: $command->startTime,
            endTime: $command->endTime,
            priority: $command->priority,
            startsAt: $command->startsAt,
            endsAt: $command->endsAt,
            active: true,
        );

        return CourtPriceRuleDto::fromDomain(
            $this->prices->saveRule($rule)
        );
    }
}
