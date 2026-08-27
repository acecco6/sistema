<?php

namespace App\Http\Controllers\Pricing;

use App\Application\Pricing\Rules\Update\UpdateCourtPriceRuleCommand;
use App\Application\Pricing\Rules\Update\UpdateCourtPriceRuleHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\UpdateCourtPromotionRequest;
use Illuminate\Http\JsonResponse;

final class UpdateCourtPromotionController extends Controller
{
    public function __invoke(
        int $id,
        UpdateCourtPromotionRequest $request,
        UpdateCourtPriceRuleHandler $handler,
    ): JsonResponse {
        $data = $request->validated();

        $result = $handler->handle(
            new UpdateCourtPriceRuleCommand(
                id: $id,
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
            message: 'Promoción actualizada correctamente.'
        );
    }
}
