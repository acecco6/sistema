<?php

namespace App\Application\Customers\Contracts;

interface CustomerRepository
{
    public function paginateForClub(int $clubId, array $filters, int $page, int $perPage): array;
    public function findClubCustomer(int $clubId, int $clubCustomerId): ?array;
    public function createOrAttach(int $clubId, array $data): array;
    public function update(int $clubId, int $clubCustomerId, array $data): ?array;
    public function changeStatus(int $clubId, int $clubCustomerId, bool $active): ?array;
    public function ensureForVerifiedUser(int $clubId, int $userId): array;
    public function linkVerifiedUser(int $userId): void;
    public function belongsToUser(int $clubCustomerId, int $userId): bool;
    public function recordReservation(int $clubCustomerId, string $reservedAt): void;
}
