<?php

namespace App\Http\Controllers\Backoffice;

use App\Application\Backoffice\RoleQueriesHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetRolePermissionsController extends Controller
{
    public function __invoke(int $id, RoleQueriesHandler $handler): JsonResponse
    {
        return $this->successResponse($handler->permissions($id), 'Permisos del rol obtenidos correctamente.');
    }
}
