<?php

namespace App\Application\Courts\Store;

final readonly class StoreCommand
{
    public function __construct(
        public int $branchId,
        public int $tipoCourtId,
        public string $name,
    ) {}
}
