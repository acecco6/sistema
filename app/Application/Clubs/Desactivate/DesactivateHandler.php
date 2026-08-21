<?php

namespace App\Application\Clubs\Desactivate;

use App\Application\Clubs\DTOs\ClubDto;
use App\Domain\Clubs\Exceptions\ClubNotFoundException;
use App\Domain\Clubs\Repositories\ClubRepository;

final class DesactivateHandler
{
    public function __construct(
        private ClubRepository $clubs,
    ) {}

    public function handle(DesactivateCommand $command): array
    {
        $club = $this->clubs->findById($command->id);

        if (!$club) {
            throw new ClubNotFoundException($command->id);
        }

        $club->deactivate();

        $club = $this->clubs->update($club);

        return ClubDto::fromDomain($club)->toArray();
    }
}
