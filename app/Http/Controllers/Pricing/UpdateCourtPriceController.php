<?php

namespace App\Http\Controllers\Pricing;

use App\Application\Pricing\Update\UpdateCourtPriceCommand;
use App\Application\Pricing\Update\UpdateCourtPriceHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\UpdateCourtPriceRequest;
use Illuminate\Http\JsonResponse;

final class UpdateCourtPriceController extends Controller
{
    public function __invoke(
        int $id,
        UpdateCourtPriceRequest $request,
        UpdateCourtPriceHandler $handler,
    ): JsonResponse {

        $validated = $request->validated();

        $result = $handler->handle(
            new UpdateCourtPriceCommand(
                id: $id,
                price: (string) $validated['price'],
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Precio actualizado correctamente.'
        );
    }
}
