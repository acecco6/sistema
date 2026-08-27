<?php

namespace App\Application\Pricing\Store;

use App\Application\Pricing\DTOs\CourtPriceDto;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Pricing\Entities\CourtPrice;
use App\Domain\Pricing\Exceptions\CourtPriceAlreadyExistsException;
use App\Domain\Pricing\Repositories\CourtPriceRepository;

final class StoreCourtPriceHandler
{
    public function __construct(
        private CourtPriceRepository $prices,
        private BranchRepository $branches,
    ) {}

    public function handle(
        StoreCourtPriceCommand $command
    ): CourtPriceDto {

        $branch = $this->branches->findById(
            $command->branchId
        );

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        $existing = $this->prices->findForCourt(
            branchId: $command->branchId,
            tipoCourtId: $command->tipoCourtId,
        );

        if ($existing !== null) {
            throw new CourtPriceAlreadyExistsException();
        }

        $price = new CourtPrice(
            id: null,
            branchId: $command->branchId,
            tipoCourtId: $command->tipoCourtId,
            price: $command->price,
            active: true,
        );

        return CourtPriceDto::fromDomain(
            $this->prices->save($price)
        );
    }
}
