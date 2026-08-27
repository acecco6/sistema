<?php

namespace App\Http\Controllers\Pricing;

use App\Application\Pricing\ChangeStatus\ChangeCourtPriceStatusCommand;
use App\Application\Pricing\ChangeStatus\ChangeCourtPriceStatusHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChangeCourtPriceStatusController extends Controller
{
    public function __invoke(
        int $id,
        Request $request,
        ChangeCourtPriceStatusHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'active' => [
                'required',
                'boolean',
            ],
        ]);

        $result = $handler->handle(
            new ChangeCourtPriceStatusCommand(
                id: $id,
                active: $validated['active'],
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: $validated['active']
                ? 'Precio activado correctamente.'
                : 'Precio desactivado correctamente.'
        );
    }
}
