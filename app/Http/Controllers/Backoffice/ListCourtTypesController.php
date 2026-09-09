<?php

namespace App\Http\Controllers\Backoffice;

use App\Application\Backoffice\CourtTypeQueriesHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ListCourtTypesController extends Controller
{
    public function __invoke(CourtTypeQueriesHandler $handler): JsonResponse
    {
        return $this->successResponse($handler->all(), 'Tipos de cancha obtenidos correctamente.');
    }
}
