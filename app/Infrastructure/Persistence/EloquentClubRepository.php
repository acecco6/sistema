<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Clubs\Entities\Club as DomainClub;
use App\Domain\Clubs\Repositories\ClubRepository;
use App\Models\Club as EloquentClub;

final class EloquentClubRepository implements ClubRepository
{
    public function findAll(): array
    {
        $eloquentClubs = EloquentClub::all();

        return $eloquentClubs->map(function (EloquentClub $club) {
            return $this->toDomain($club);
        })->toArray();
    }

    public function create(DomainClub $club): DomainClub
    {
        $eloquentClub = EloquentClub::create([
            'name' => $club->getName(),
            'active' => $club->isActive(),
        ]);

        return $this->toDomain($eloquentClub);
    }

    public function findById(int $id): ?DomainClub
    {
        $eloquentClub = EloquentClub::find($id);

        if (!$eloquentClub) {
            return null;
        }

        return $this->toDomain($eloquentClub);
    }

    public function update(DomainClub $club): DomainClub
    {
        $eloquentClub = EloquentClub::find($club->getId());

        if (!$eloquentClub) {
            throw new \Exception('Club no encontrado');
        }

        $eloquentClub->update([
            'name' => $club->getName(),
            'active' => $club->isActive(),
        ]);

        return $this->toDomain($eloquentClub);
    }

    public function delete(int $id): void
    {
        $eloquentClub = EloquentClub::find($id);

        if (!$eloquentClub) {
            throw new \Exception('Club no encontrado');
        }

        $eloquentClub->update([
            'active' => false,
        ]);
    }

    protected function toDomain(EloquentClub $eloquentClub): DomainClub
    {
        return new DomainClub(
            id: $eloquentClub->id,
            name: $eloquentClub->name,
            active: $eloquentClub->active
        );
    }
}
