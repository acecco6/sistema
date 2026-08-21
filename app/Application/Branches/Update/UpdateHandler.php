<?php

namespace App\Application\Branches\Update;

use App\Application\Branches\DTOs\BranchDto;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;

final class UpdateHandler
{
    public function __construct(private BranchRepository $branches) {}

    public function handle(UpdateCommand $command): array
    {
        $branch = $this->branches->findById($command->id);
        
        if (!$branch) {
            throw new BranchNotFoundException($command->id);
        }

        $branch->updateDetails(
            $command->name,
            $command->address,
            $command->openingTime,
            $command->closingTime,
            $command->active ?? $branch->isActive()
        );

        $branch = $this->branches->update($branch);
        
        return BranchDto::fromDomain($branch)->toArray();
    }
}
