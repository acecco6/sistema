<?php

namespace App\Application\Branches\Update;

final readonly class UpdateCommand
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $address,
        public ?string $openingTime,
        public ?string $closingTime,
        public ?bool $active
    ) {}
}
