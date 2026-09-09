<?php

namespace App\Http\Controllers\Backoffice;

use App\Application\Backoffice\GetDashboardHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\DashboardRequest;
use Illuminate\Http\JsonResponse;

final class GetDashboardController extends Controller
{
    public function __invoke(int $branch_id, DashboardRequest $request, GetDashboardHandler $handler): JsonResponse
    {
        return $this->successResponse($handler->handle($branch_id, $request->validated('date')), 'Dashboard obtenido correctamente.');
    }
}
