<?php

namespace App\Application\Branches\Desactivate;

final readonly class DesactivateCommand
{
    public function __construct(public int $id) {}
}
