<?php

namespace App\Application\Courts\Update;

use App\Application\Courts\DTOs\CourtDto;
use App\Domain\Courts\Exceptions\CourtNotFoundException;
use App\Domain\Courts\Exceptions\TipoCourtNotFoundException;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Domain\Courts\Repositories\TipoCourtRepository;

final class UpdateHandler
{
    public function __construct(
        private CourtRepository $courts,
        private TipoCourtRepository $tiposCourt,
    ) {}

    public function handle(UpdateCommand $command): array
    {
        $court = $this->courts->findById($command->id);

        if (!$court) {
            throw new CourtNotFoundException($command->id);
        }

        $tipoCourt = $this->tiposCourt->findById($command->tipoCourtId);

        if (!$tipoCourt) {
            throw new TipoCourtNotFoundException();
        }

        $court->setName($command->name);
        $court->setTipoCourtId($command->tipoCourtId);

        $court = $this->courts->update($court);

        return CourtDto::fromDomain($court)->toArray();
    }
}
