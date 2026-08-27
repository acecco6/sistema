<?php

namespace App\Http\Controllers\Pricing;

use App\Application\Pricing\Show\ShowCourtPriceHandler;
use App\Application\Pricing\Show\ShowCourtPriceQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ShowCourtPriceController extends Controller
{
    public function __invoke(
        int $id,
        ShowCourtPriceHandler $handler,
    ): JsonResponse {

        $result = $handler->handle(
            new ShowCourtPriceQuery($id)
        );

        return $this->successResponse(
            data: $result->toArray()
        );
    }
}
