<?php

namespace App\Application\Courts\Get;

use App\Application\Courts\DTOs\CourtDto;
use App\Domain\Courts\Repositories\CourtRepository;

final class GetHandler
{
    public function __construct(
        private CourtRepository $courts,
    ) {}

    public function handle(GetCommand $command): array
    {
        $courts = $this->courts->findByBranchId($command->branchId);

        return array_map(
            fn($court) => CourtDto::fromDomain($court)->toArray(),
            $courts
        );
    }
}
