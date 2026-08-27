<?php

namespace App\Domain\Courts\Repositories;

interface IntervalTimeTipoCourtRepository
{
    public function findIntervalMinutes(
        int $branchId,
        int $tipoCourtId,
    ): ?int;
}
