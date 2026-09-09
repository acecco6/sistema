<?php

namespace App\Http\Controllers\Backoffice;

use App\Application\Backoffice\CourtIntervalHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetCourtIntervalController extends Controller
{
    public function __invoke(int $branch_id, int $court_type_id, CourtIntervalHandler $handler): JsonResponse
    {
        return $this->successResponse($handler->get($branch_id, $court_type_id), 'Intervalo obtenido correctamente.');
    }
}
