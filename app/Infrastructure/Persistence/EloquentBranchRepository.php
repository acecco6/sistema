<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Branches\Entities\Branch as DomainBranch;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Models\Branch as EloquentBranch;

final class EloquentBranchRepository implements BranchRepository
{
    public function findAllByClubId(int $clubId): array
    {
        $eloquentBranches = EloquentBranch::where('club_id', $clubId)->get();

        return $eloquentBranches->map(function (EloquentBranch $branch) {
            return $this->toDomain($branch);
        })->toArray();
    }

    public function create(DomainBranch $branch): DomainBranch
    {
        $eloquentBranch = EloquentBranch::create([
            'club_id' => $branch->getClubId(),
            'name' => $branch->getName(),
            'address' => $branch->getAddress(),
            'opening_time' => $branch->getOpeningTime(),
            'closing_time' => $branch->getClosingTime(),
            'active' => $branch->isActive(),
        ]);

        return $this->toDomain($eloquentBranch);
    }

    public function findById(int $id): ?DomainBranch
    {
        $eloquentBranch = EloquentBranch::find($id);

        if (!$eloquentBranch) {
            return null;
        }

        return $this->toDomain($eloquentBranch);
    }

    public function update(DomainBranch $branch): DomainBranch
    {
        $eloquentBranch = EloquentBranch::find($branch->getId());

        if (!$eloquentBranch) {
            throw new \Exception('Sucursal no encontrada');
        }

        $eloquentBranch->update([
            'name' => $branch->getName(),
            'address' => $branch->getAddress(),
            'opening_time' => $branch->getOpeningTime(),
            'closing_time' => $branch->getClosingTime(),
            'active' => $branch->isActive(),
        ]);

        return $this->toDomain($eloquentBranch);
    }

    public function delete(int $id): void
    {
        $eloquentBranch = EloquentBranch::find($id);

        if (!$eloquentBranch) {
            throw new \Exception('Sucursal no encontrada');
        }

        $eloquentBranch->update([
            'active' => false,
        ]);
    }

    public function findByClubAndScope(int $clubId, ?int $branchId = null): array
    {
        $query = EloquentBranch::query()
            ->where('club_id', $clubId);

        if ($branchId !== null) {
            $query->where('id', $branchId);
        }

        return $query
            ->get()
            ->map(
                fn(EloquentBranch $branch) =>
                $this->toDomain($branch)
            )
            ->all();
    }

    public function findByClub(int $clubId): array
    {
        return EloquentBranch::query()
            ->where('club_id', $clubId)
            ->get()
            ->map(fn(EloquentBranch $branch) => $this->toDomain($branch))
            ->all();
    }

    public function findByClubAndBranchIds(int $clubId, array $branchIds): array
    {
        return EloquentBranch::query()
            ->where('club_id', $clubId)
            ->whereIn('id', $branchIds)
            ->get()
            ->map(fn(EloquentBranch $branch) => $this->toDomain($branch))
            ->all();
    }

    protected function toDomain(EloquentBranch $eloquentBranch): DomainBranch
    {
        return new DomainBranch(
            id: $eloquentBranch->id,
            clubId: $eloquentBranch->club_id,
            name: $eloquentBranch->name,
            address: $eloquentBranch->address,
            openingTime: $eloquentBranch->opening_time,
            closingTime: $eloquentBranch->closing_time,
            active: $eloquentBranch->active
        );
    }
}
