<?php

namespace App\Http\Controllers\Backoffice;

use App\Application\Backoffice\RoleQueriesHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ListRolesController extends Controller
{
    public function __invoke(RoleQueriesHandler $handler): JsonResponse
    {
        return $this->successResponse($handler->all(), 'Roles obtenidos correctamente.');
    }
}
