<?php

namespace App\Application\Branches\Show;

use App\Application\Branches\DTOs\BranchDto;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;

final class ShowHandler
{
    public function __construct(private BranchRepository $branches) {}

    public function handle(ShowCommand $command): array
    {
        $branch = $this->branches->findById($command->id);
        
        if (!$branch) {
            throw new BranchNotFoundException($command->id);
        }
        
        return BranchDto::fromDomain($branch)->toArray();
    }
}
