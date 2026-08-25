<?php

namespace App\Domain\Clubs\Repositories;

use App\Domain\Clubs\Entities\Club;

interface ClubRepository
{
    /**
     * @return Club[]
     */
    public function findAll(): array;

    public function create(Club $club): Club;

    public function findById(int $id): ?Club;

    public function update(Club $club): Club;

    public function delete(int $id): void;

    public function findByUserMemberships(int $userId): array;
}
