<?php

namespace App\Http\Controllers\Backoffice;

use App\Application\Backoffice\GetMercadoPagoStatusHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetMercadoPagoStatusController extends Controller
{
    public function __invoke(int $club_id, GetMercadoPagoStatusHandler $handler): JsonResponse
    {
        return $this->successResponse($handler->handle($club_id), 'Estado de Mercado Pago obtenido correctamente.');
    }
}
