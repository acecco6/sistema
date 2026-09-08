<?php

namespace App\Http\Controllers\Payments\MercadoPagoAccounts;

use App\Application\Payments\MercadoPagoAccounts\Connect\ConnectMercadoPagoCommand;
use App\Application\Payments\MercadoPagoAccounts\Connect\ConnectMercadoPagoHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ConnectMercadoPagoController extends Controller
{

    public function __invoke(
        Request $request,
        int $club_id,
        ConnectMercadoPagoHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(
            new ConnectMercadoPagoCommand(
                clubId: $club_id,
                userId: $request->user()->id,
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'URL de conexión con Mercado Pago generada correctamente.',
        );
    }
}
