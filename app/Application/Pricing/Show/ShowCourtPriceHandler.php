<?php

namespace App\Application\Pricing\Show;

use App\Application\Pricing\DTOs\CourtPriceDto;
use App\Domain\Pricing\Exceptions\CourtPriceNotFoundException;
use App\Domain\Pricing\Repositories\CourtPriceRepository;

final class ShowCourtPriceHandler
{
    public function __construct(
        private CourtPriceRepository $prices,
    ) {}

    public function handle(
        ShowCourtPriceQuery $query
    ): CourtPriceDto {

        $price = $this->prices->findById(
            $query->id
        );

        if ($price === null) {
            throw new CourtPriceNotFoundException();
        }

        return CourtPriceDto::fromDomain($price);
    }
}
