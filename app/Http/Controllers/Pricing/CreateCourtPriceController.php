<?php

namespace App\Http\Controllers\Pricing;

use App\Application\Pricing\Store\StoreCourtPriceCommand;
use App\Application\Pricing\Store\StoreCourtPriceHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StoreCourtPriceRequest;
use Illuminate\Http\JsonResponse;

final class CreateCourtPriceController extends Controller
{
    public function __invoke(
        int $branch_id,
        StoreCourtPriceRequest $request,
        StoreCourtPriceHandler $handler,
    ): JsonResponse {

        $validated = $request->validated();

        $result = $handler->handle(
            new StoreCourtPriceCommand(
                branchId: $branch_id,
                tipoCourtId: $validated['tipo_court_id'],
                price: (string) $validated['price'],
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Precio creado correctamente.',
            code: 201,
        );
    }
}
