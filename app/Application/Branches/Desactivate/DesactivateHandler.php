<?php

namespace App\Application\Branches\Desactivate;

use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;

final class DesactivateHandler
{
    public function __construct(private BranchRepository $branches) {}

    public function handle(DesactivateCommand $command): void
    {
        $branch = $this->branches->findById($command->id);
        
        if (!$branch) {
            throw new BranchNotFoundException($command->id);
        }

        $branch->deactivate();
        
        $this->branches->update($branch);
    }
}
