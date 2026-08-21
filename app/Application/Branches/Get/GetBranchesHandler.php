<?php

namespace App\Application\Branches\Get;

use App\Application\Branches\DTOs\BranchDto;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Clubs\Repositories\ClubRepository;
use App\Domain\Clubs\Exceptions\ClubNotFoundException;

final class GetBranchesHandler
{
    public function __construct(
        private BranchRepository $branches,
        private ClubRepository $clubs
    ) {}

    public function handle(int $clubId): array
    {
        $club = $this->clubs->findById($clubId);
        if (!$club) {
            throw new ClubNotFoundException($clubId);
        }

        $branches = $this->branches->findAllByClubId($clubId);
        
        return array_map(fn($branch) => BranchDto::fromDomain($branch)->toArray(), $branches);
    }
}
