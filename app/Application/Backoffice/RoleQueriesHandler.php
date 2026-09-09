<?php

namespace App\Application\Backoffice;

use App\Application\Backoffice\Contracts\BackofficeQueryRepository;
use App\Domain\Roles\Exceptions\RoleNotFoundException;

final readonly class RoleQueriesHandler
{
    public function __construct(private BackofficeQueryRepository $queries) {}

    public function all(): array
    {
        return $this->queries->roles();
    }

    public function permissions(int $roleId): array
    {
        $role = $this->queries->roleWithPermissions($roleId);

        if ($role === null) {
            throw new RoleNotFoundException();
        }

        return $role;
    }
}
