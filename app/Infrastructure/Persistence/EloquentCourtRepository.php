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
