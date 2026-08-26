<?php

namespace App\Application\Courts\Deactivate;

final readonly class DeactivateCommand
{
    public function __construct(
        public int $id,
    ) {}
}
