<?php

namespace App\Application\Clubs\Update;

final readonly class UpdateCommand
{
    public function __construct(
        public int $id,
        public string $name,
        public ?bool $active
    ) {}
}
