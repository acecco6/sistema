<?php

namespace App\Http\Controllers\Pricing;

use App\Application\Pricing\Rules\ChangeStatus\ChangeCourtPriceRuleStatusCommand;
use App\Application\Pricing\Rules\ChangeStatus\ChangeCourtPriceRuleStatusHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\ChangeCourtPromotionStatusRequest;
use Illuminate\Http\JsonResponse;

final class ChangeCourtPromotionStatusController extends Controller
{
    public function __invoke(
        int $id,
        ChangeCourtPromotionStatusRequest $request,
        ChangeCourtPriceRuleStatusHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $handler->handle(
            new ChangeCourtPriceRuleStatusCommand(
                id: $id,
                active: $validated['active'],
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: $validated['active']
                ? 'Promoción activada correctamente.'
                : 'Promoción desactivada correctamente.'
        );
    }
}
