<?php

namespace App\Domain\Courts\Repositories;

use App\Domain\Courts\Entities\TipoCourt;

interface TipoCourtRepository
{
    public function findById(int $id): ?TipoCourt;
}
