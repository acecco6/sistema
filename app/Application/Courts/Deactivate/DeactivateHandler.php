<?php

namespace App\Application\Courts\Deactivate;

use App\Domain\Courts\Exceptions\CourtAlreadyInactiveException;
use App\Domain\Courts\Exceptions\CourtNotFoundException;
use App\Domain\Courts\Repositories\CourtRepository;

final class DeactivateHandler
{
    public function __construct(
        private CourtRepository $courts,
    ) {}

    public function handle(DeactivateCommand $command): void
    {
        $court = $this->courts->findById($command->id);

        if (!$court) {
            throw new CourtNotFoundException($command->id);
        }

        if (!$court->isActive()) {
            throw new CourtAlreadyInactiveException($command->id);
        }

        $court->deactivate();

        $this->courts->update($court);
    }
}
