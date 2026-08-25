<?php

namespace App\Domain\Courts\Repositories;

use App\Domain\Courts\Entities\Court;

interface CourtRepository
{
    public function findById(int $id): ?Court;

    public function findByBranchId(int $branchId): array;

    public function save(Court $court): Court;

    public function update(Court $court): Court;
}
