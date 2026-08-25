<?php

namespace App\Application\Branches\Get;

final readonly class GetBranchesQuery
{
    public function __construct(
        public int $userId,
        public int $clubId,
    ) {}
}
