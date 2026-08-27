<?php

namespace App\Http\Controllers\Pricing;

use App\Application\Pricing\Rules\Store\StoreCourtPriceRuleCommand;
use App\Application\Pricing\Rules\Store\StoreCourtPriceRuleHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\StoreCourtPromotionRequest;
use Illuminate\Http\JsonResponse;

final class CreateCourtPromotionController extends Controller
{
    public function __invoke(
        int $court_price_id,
        StoreCourtPromotionRequest $request,
        StoreCourtPriceRuleHandler $handler,
    ): JsonResponse {

        $data = $request->validated();

        $result = $handler->handle(
            new StoreCourtPriceRuleCommand(
                courtPriceId: $court_price_id,
                name: $data['name'],
                price: (string) $data['price'],
                dayOfWeek: $data['day_of_week'] ?? null,
                specificDate: $data['specific_date'] ?? null,
                startTime: $data['start_time'] ?? null,
                endTime: $data['end_time'] ?? null,
                priority: $data['priority'],
                startsAt: $data['starts_at'] ?? null,
                endsAt: $data['ends_at'] ?? null,
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Promoción creada correctamente.',
            code: 201
        );
    }
}
