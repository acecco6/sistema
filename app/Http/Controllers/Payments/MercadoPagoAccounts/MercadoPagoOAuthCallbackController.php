<?php

namespace App\Http\Controllers\Payments\MercadoPagoAccounts;

use App\Application\Payments\MercadoPagoAccounts\Callback\ProcessMercadoPagoCallbackCommand;
use App\Application\Payments\MercadoPagoAccounts\Callback\ProcessMercadoPagoCallbackHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MercadoPagoOAuthCallbackController extends Controller
{

    public function __invoke(
        Request $request,
        ProcessMercadoPagoCallbackHandler $handler,
    ): JsonResponse {

        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $result = $handler->handle(
            new ProcessMercadoPagoCallbackCommand(
                code: $validated['code'],
                state: $validated['state'],
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Mercado Pago conectado correctamente.',
        );
    }
}
