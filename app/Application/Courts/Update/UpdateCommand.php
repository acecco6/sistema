<?php

namespace App\Application\Courts\Update;

final readonly class UpdateCommand
{
    public function __construct(
        public int $id,
        public int $tipoCourtId,
        public string $name,
    ) {}
}
