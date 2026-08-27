<?php

namespace App\Http\Controllers\Pricing;

use App\Application\Pricing\Rules\ChangeStatus\ChangeCourtPriceRuleStatusCommand;
use App\Application\Pricing\Rules\ChangeStatus\ChangeCourtPriceRuleStatusHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChangeCourtPromotionStatusController extends Controller
{
    public function __invoke(
        int $id,
        Request $request,
        ChangeCourtPriceRuleStatusHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'active' => [
                'required',
                'boolean',
            ],
        ]);

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
