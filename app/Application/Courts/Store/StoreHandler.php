<?php

namespace App\Application\Courts\Store;

use App\Application\Courts\DTOs\CourtDto;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Courts\Entities\Court;
use App\Domain\Courts\Exceptions\TipoCourtNotFoundException;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Domain\Courts\Repositories\TipoCourtRepository;

final class StoreHandler
{
    public function __construct(
        private CourtRepository $courts,
        private BranchRepository $branches,
        private TipoCourtRepository $tiposCourt,
    ) {}

    public function handle(StoreCommand $command): array
    {
        $branch = $this->branches->findById($command->branchId);

        if (!$branch) {
            throw new BranchNotFoundException();
        }

        $tipoCourt = $this->tiposCourt->findById($command->tipoCourtId);

        if (!$tipoCourt) {
            throw new TipoCourtNotFoundException();
        }

        $court = new Court(
            id: null,
            branchId: $command->branchId,
            tipoCourtId: $command->tipoCourtId,
            name: $command->name,
            active: true,
        );

        $court = $this->courts->save($court);

        return CourtDto::fromDomain($court)->toArray();
    }
}
