<?php

namespace App\Application\Courts\Get;

final readonly class GetCommand
{
    public function __construct(
        public int $branchId,
    ) {}
}
