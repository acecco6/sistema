<?php

namespace App\Application\Clubs\Get;

final readonly class GetClubsQuery
{
    public function __construct(
        public int $userId,
    ) {}
}
