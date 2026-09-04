<?php

namespace App\Http\Controllers\Payments;

use App\Application\Payments\Refunds\CreateRefund\CreateRefundCommand;
use App\Application\Payments\Refunds\CreateRefund\CreateRefundHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\CreateRefundRequest;
use Illuminate\Http\JsonResponse;

final class CreateRefundController extends Controller
{
    public function __invoke(
        int $id,
        CreateRefundRequest $request,
        CreateRefundHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();

        $refund = $handler->handle(
            new CreateRefundCommand(
                reservationId: $id,
                amount: (string) $validated['amount'],
                reason: $validated['reason'] ?? null,
                createdByUserId: (int) $request->user()->id,
            )
        );

        return $this->successResponse(
            data: $refund->toArray(),
            message: 'Devolución creada correctamente.',
            code: 201,
        );
    }
}
