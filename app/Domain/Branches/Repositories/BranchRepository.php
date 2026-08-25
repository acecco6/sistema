<?php

namespace App\Domain\Branches\Repositories;

use App\Domain\Branches\Entities\Branch;

interface BranchRepository
{
    /**
     * @return Branch[]
     */
    public function findAllByClubId(int $clubId): array;

    public function create(Branch $branch): Branch;

    public function findById(int $id): ?Branch;

    public function update(Branch $branch): Branch;

    public function delete(int $id): void;

    public function findByClub(int $clubId): array;

    public function findByClubAndBranchIds(int $clubId, array $branchIds): array;

    public function findByClubAndScope(int $clubId, ?int $branchId = null,): array;
}
