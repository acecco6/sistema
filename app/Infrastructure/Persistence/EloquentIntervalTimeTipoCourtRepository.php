<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Courts\Repositories\IntervalTimeTipoCourtRepository;
use Illuminate\Support\Facades\DB;

final class EloquentIntervalTimeTipoCourtRepository implements IntervalTimeTipoCourtRepository
{
    public function findIntervalMinutes(int $branchId, int $tipoCourtId): ?int
    {
        $interval = DB::table('interval_time_tipo_court')
            ->where('branch_id', $branchId)
            ->where('tipo_court_id', $tipoCourtId)
            ->value('interval_minutes');

        return $interval !== null
            ? (int) $interval
            : null;
    }
}
