<?php

namespace App\Application\Clubs\Store;

use App\Application\Clubs\DTOs\ClubDto;
use App\Domain\Clubs\Entities\Club;
use App\Domain\Clubs\Repositories\ClubRepository;

final class StoreHandler
{
    public function __construct(
        private ClubRepository $clubs,
    ) {}

    public function handle(StoreCommand $command): array
    {
        $club = new Club(
            id: null,
            name: $command->name,
            active: true
        );

        $club = $this->clubs->create($club);

        return ClubDto::fromDomain($club)->toArray();
    }
}
