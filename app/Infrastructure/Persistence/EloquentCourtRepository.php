<?php

namespace App\Infrastructure\Persistence;


use App\Domain\Courts\Entities\Court;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Models\Court as EloquentCourt;

class EloquentCourtRepository implements CourtRepository
{
    public function findById(int $id): ?Court
    {
        $court = EloquentCourt::find($id);

        if (!$court) {
            return null;
        }

        return $this->toDomain($court);
    }

    public function findByBranchId(int $branchId): array
    {
        $court = EloquentCourt::where('branch_id', $branchId)->get();

        return $court->map(function (EloquentCourt $court) {
            return $this->toDomain($court);
        })->toArray();
    }

    public function save(Court $court): Court
    {
        $eloquentCourt = EloquentCourt::create($court->toArray());
        return $this->toDomain($eloquentCourt);
    }

    public function update(Court $court): Court
    {
        $eloquentCourt = EloquentCourt::find($court->getId());

        if (!$eloquentCourt) {
            throw new \Exception('Court not found');
        }

        $eloquentCourt->update($court->toArray());

        return $this->toDomain($eloquentCourt);
    }

    public function findActiveByBranchAndTipo(int $branchId, int $tipoCourtId): array
    {
        return EloquentCourt::query()
            ->where('branch_id', $branchId)
            ->where('tipo_court_id', $tipoCourtId)
            ->where('active', true)
            ->get()
            ->map(
                fn(EloquentCourt $court) =>
                $this->toDomain($court)
            )
            ->all();
    }

    public function findByIdForUpdate(int $id): ?Court
    {
        $court = EloquentCourt::query()
            ->where('id', $id)
            ->lockForUpdate()
            ->first();

        return $court
            ? $this->toDomain($court)
            : null;
    }

    protected function toDomain(EloquentCourt $eloquentCourt): Court
    {
        return new Court(
            id: $eloquentCourt->id,
            name: $eloquentCourt->name,
            active: $eloquentCourt->active,
            branchId: $eloquentCourt->branch_id,
            tipoCourtId: $eloquentCourt->tipo_court_id,
        );
    }
}
