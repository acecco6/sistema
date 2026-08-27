<?php

namespace App\Application\Pricing\Update;

use App\Application\Pricing\DTOs\CourtPriceDto;
use App\Domain\Pricing\Exceptions\CourtPriceNotFoundException;
use App\Domain\Pricing\Repositories\CourtPriceRepository;

final class UpdateCourtPriceHandler
{
    public function __construct(
        private CourtPriceRepository $prices,
    ) {}

    public function handle(
        UpdateCourtPriceCommand $command
    ): CourtPriceDto {

        $price = $this->prices->findById(
            $command->id
        );

        if ($price === null) {
            throw new CourtPriceNotFoundException();
        }

        $price->changePrice(
            $command->price
        );

        return CourtPriceDto::fromDomain(
            $this->prices->update($price)
        );
    }
}
