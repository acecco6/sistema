<?php

namespace App\Application\Clubs\Store;

final readonly class StoreCommand
{
    public function __construct(
        public string $name
    ) {}
}
