<?php

namespace App\Application\Clubs\Desactivate;

final readonly class DesactivateCommand
{
    public function __construct(public int $id) {}
}
