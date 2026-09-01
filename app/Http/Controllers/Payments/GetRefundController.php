<?php

namespace App\Http\Controllers\Payments;

use App\Application\Payments\Refunds\GetRefund\GetRefundHandler;
use App\Application\Payments\Refunds\GetRefund\GetRefundQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetRefundController extends Controller
{
    public function __invoke(
        int $id,
        GetRefundHandler $handler,
    ): JsonResponse {
        $refund = $handler->handle(
            new GetRefundQuery(
                refundId: $id,
            )
        );

        return $this->successResponse(
            data: $refund->toArray(),
        );
    }
}
