<?php

namespace App\Http\Controllers\Pricing;

use App\Application\Pricing\ChangeStatus\ChangeCourtPriceStatusCommand;
use App\Application\Pricing\ChangeStatus\ChangeCourtPriceStatusHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\ChangeCourtPriceStatusRequest;
use Illuminate\Http\JsonResponse;

final class ChangeCourtPriceStatusController extends Controller
{
    public function __invoke(
        int $id,
        ChangeCourtPriceStatusRequest $request,
        ChangeCourtPriceStatusHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();

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
