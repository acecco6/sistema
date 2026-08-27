<?php

namespace App\Http\Controllers\Pricing;

use App\Application\Pricing\Rules\Show\ShowCourtPriceRuleHandler;
use App\Application\Pricing\Rules\Show\ShowCourtPriceRuleQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ShowCourtPromotionController extends Controller
{
    public function __invoke(
        int $id,
        ShowCourtPriceRuleHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(
            new ShowCourtPriceRuleQuery(
                id: $id,
            )
        );

        return $this->successResponse(
            data: $result->toArray()
        );
    }
}
