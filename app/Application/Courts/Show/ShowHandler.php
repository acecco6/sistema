<?php

namespace App\Application\Courts\Show;

use App\Application\Courts\DTOs\CourtDto;
use App\Domain\Courts\Exceptions\CourtNotFoundException;
use App\Domain\Courts\Repositories\CourtRepository;

final class ShowHandler
{
    public function __construct(
        private CourtRepository $courts,
    ) {}

    public function handle(ShowCommand $command): array
    {
        $court = $this->courts->findById($command->id);

        if (!$court) {
            throw new CourtNotFoundException($command->id);
        }

        return CourtDto::fromDomain($court)->toArray();
    }
}
