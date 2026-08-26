<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Courts\Entities\TipoCourt;
use App\Domain\Courts\Repositories\TipoCourtRepository;
use App\Models\TipoCourt as EloquentTipoCourt;

class EloquentTipoCourtRepository implements TipoCourtRepository
{
    public function findById(int $id): ?TipoCourt
    {
        $tipoCourt = EloquentTipoCourt::find($id);

        if (!$tipoCourt) {
            return null;
        }

        return $this->toDomain($tipoCourt);
    }

    private function toDomain(EloquentTipoCourt $eloquentTipoCourt): TipoCourt
    {
        return new TipoCourt(
            id:          $eloquentTipoCourt->id,
            name:        $eloquentTipoCourt->name,
            description: $eloquentTipoCourt->description,
        );
    }
}
