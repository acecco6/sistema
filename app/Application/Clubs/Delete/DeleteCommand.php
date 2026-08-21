<?php

namespace App\Application\Clubs\Delete;

final readonly class DeleteCommand
{
    public function __construct(public int $id) {}
}
