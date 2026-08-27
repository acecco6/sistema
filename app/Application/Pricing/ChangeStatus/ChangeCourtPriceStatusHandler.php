<?php

namespace App\Application\Pricing\ChangeStatus;

use App\Application\Pricing\DTOs\CourtPriceDto;
use App\Domain\Pricing\Exceptions\CourtPriceNotFoundException;
use App\Domain\Pricing\Repositories\CourtPriceRepository;

final class ChangeCourtPriceStatusHandler
{
    public function __construct(
        private CourtPriceRepository $prices,
    ) {}

    public function handle(
        ChangeCourtPriceStatusCommand $command
    ): CourtPriceDto {

        $price = $this->prices->findById(
            $command->id
        );

        if ($price === null) {
            throw new CourtPriceNotFoundException();
        }

        if ($command->active) {
            $price->activate();
        } else {
            $price->deactivate();
        }

        return CourtPriceDto::fromDomain(
            $this->prices->update($price)
        );
    }
}
