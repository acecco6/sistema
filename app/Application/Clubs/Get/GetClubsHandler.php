<?php

namespace App\Application\Clubs\Get;

use App\Application\Clubs\DTOs\ClubDto;
use App\Domain\Clubs\Repositories\ClubRepository;

final class GetClubsHandler
{
    public function __construct(
        private ClubRepository $clubs
    ) {}

    public function handle(): array
    {
        $clubs = $this->clubs->findAll();
        return array_map(fn($club) => ClubDto::fromDomain($club), $clubs);
    }
}
