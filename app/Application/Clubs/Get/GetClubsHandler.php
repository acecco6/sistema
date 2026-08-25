<?php

namespace App\Application\Clubs\Get;

use App\Application\Clubs\DTOs\ClubDto;
use App\Domain\Clubs\Repositories\ClubRepository;

final class GetClubsHandler
{
    public function __construct(
        private ClubRepository $clubs
    ) {}

    public function handle(GetClubsQuery $query): array
    {
        $clubs = $this->clubs->findByUserMemberships($query->userId);
        return array_map(ClubDto::fromDomain(...), $clubs);
    }
}
