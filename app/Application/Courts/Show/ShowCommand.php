<?php

namespace App\Application\Courts\Show;

final readonly class ShowCommand
{
    public function __construct(
        public int $id,
    ) {}
}
