<?php

namespace App\Application\Branches\Store;

use App\Application\Branches\DTOs\BranchDto;
use App\Domain\Branches\Entities\Branch;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Clubs\Repositories\ClubRepository;
use App\Domain\Clubs\Exceptions\ClubNotFoundException;

final class StoreHandler
{
    public function __construct(
        private BranchRepository $branches,
        private ClubRepository $clubs
    ) {}

    public function handle(StoreCommand $command): array
    {
        $club = $this->clubs->findById($command->clubId);
        if (!$club) {
            throw new ClubNotFoundException($command->clubId);
        }

        $branch = new Branch(
            id: null,
            clubId: $command->clubId,
            name: $command->name,
            address: $command->address,
            openingTime: $command->openingTime,
            closingTime: $command->closingTime,
            active: true
        );

        $branch = $this->branches->create($branch);
        
        return BranchDto::fromDomain($branch)->toArray();
    }
}
