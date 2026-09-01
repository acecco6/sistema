<?php

namespace App\Http\Controllers\Payments;

use App\Application\Payments\Refunds\CompleteRefund\CompleteRefundCommand;
use App\Application\Payments\Refunds\CompleteRefund\CompleteRefundHandler;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\CompleteRefundRequest;
use Illuminate\Http\JsonResponse;

final class CompleteRefundController extends Controller
{
    public function __invoke(
        int $id,
        CompleteRefundRequest $request,
        CompleteRefundHandler $handler,
    ): JsonResponse {
        $refund = $handler->handle(
            new CompleteRefundCommand(
                refundId: $id,
                method: PaymentMethod::from(
                    $request->validated('method')
                ),
                completedByUserId: $request->user()->id,
                notes: $request->validated('notes'),
            )
        );

        return $this->successResponse(
            data: $refund->toArray(),
            message: 'Devolución completada correctamente.',
        );
    }
}
