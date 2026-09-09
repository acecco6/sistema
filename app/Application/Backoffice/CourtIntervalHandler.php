<?php

namespace App\Application\Backoffice;

use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Courts\Exceptions\TipoCourtNotFoundException;
use App\Domain\Courts\Repositories\IntervalTimeTipoCourtRepository;
use App\Domain\Courts\Repositories\TipoCourtRepository;

final readonly class CourtIntervalHandler
{
    public function __construct(
        private BranchRepository $branches,
        private TipoCourtRepository $courtTypes,
        private IntervalTimeTipoCourtRepository $intervals,
    ) {}

    public function get(int $branchId, int $courtTypeId): array
    {
        $this->validateResources($branchId, $courtTypeId);

        return $this->response($branchId, $courtTypeId, $this->intervals->findIntervalMinutes($branchId, $courtTypeId) ?? 30);
    }

    public function update(int $branchId, int $courtTypeId, int $intervalMinutes): array
    {
        $this->validateResources($branchId, $courtTypeId);

        return $this->response($branchId, $courtTypeId, $this->intervals->updateOrCreate($branchId, $courtTypeId, $intervalMinutes));
    }

    private function validateResources(int $branchId, int $courtTypeId): void
    {
        if ($this->branches->findById($branchId) === null) {
            throw new BranchNotFoundException();
        }

        if ($this->courtTypes->findById($courtTypeId) === null) {
            throw new TipoCourtNotFoundException();
        }
    }

    private function response(int $branchId, int $courtTypeId, int $intervalMinutes): array
    {
        return [
            'branch_id' => $branchId,
            'court_type_id' => $courtTypeId,
            'interval_minutes' => $intervalMinutes,
        ];
    }
}
