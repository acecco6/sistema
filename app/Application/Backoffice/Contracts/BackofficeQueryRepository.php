<?php

namespace App\Application\Backoffice\Contracts;

interface BackofficeQueryRepository
{
    public function sessionContext(int $userId): array;

    public function memberships(int $clubId, array $filters, int $page, int $perPage, bool $listMe = true, ?int $userId): array;

    public function membership(int $id): ?array;

    public function roles(): array;

    public function roleWithPermissions(int $id): ?array;

    public function users(string $search, ?bool $active, int $page, int $perPage): array;

    public function courtTypes(): array;

    public function dashboard(int $branchId, string $date): array;
}
