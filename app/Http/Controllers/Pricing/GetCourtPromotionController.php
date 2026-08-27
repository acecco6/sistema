<?php

namespace App\Http\Controllers\Pricing;

use App\Application\Pricing\Rules\Get\GetCourtPriceRulesHandler;
use App\Application\Pricing\Rules\Get\GetCourtPriceRulesQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetCourtPromotionController extends Controller
{
    public function __invoke(
        int $court_price_id,
        GetCourtPriceRulesHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(
            new GetCourtPriceRulesQuery(
                courtPriceId: $court_price_id,
            )
        );

        return $this->successResponse(
            data: array_map(
                fn($dto) => $dto->toArray(),
                $result
            )
        );
    }
}
