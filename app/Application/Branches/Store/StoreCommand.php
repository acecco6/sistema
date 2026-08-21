<?php

namespace App\Application\Branches\Store;

final readonly class StoreCommand
{
    public function __construct(
        public int $clubId,
        public string $name,
        public ?string $address,
        public ?string $openingTime,
        public ?string $closingTime,
    ) {}
}
