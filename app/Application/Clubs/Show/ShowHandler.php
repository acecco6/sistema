<?php

namespace App\Application\Clubs\Show;

use App\Application\Clubs\DTOs\ClubDto;
use App\Domain\Clubs\Exceptions\ClubNotFoundException;
use App\Domain\Clubs\Repositories\ClubRepository;

final class ShowHandler
{
    public function __construct(
        private ClubRepository $clubs,
    ) {}

    public function handle(ShowCommand $command): array
    {
        $club = $this->clubs->findById($command->id);

        if (!$club) {
            throw new ClubNotFoundException($command->id);
        }

        return ClubDto::fromDomain($club)->toArray();
    }
}
