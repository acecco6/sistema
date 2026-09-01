<?php

namespace App\Http\Controllers\Payments;

use App\Application\Payments\Refunds\ListRefunds\ListRefundsHandler;
use App\Application\Payments\Refunds\ListRefunds\ListRefundsQuery;
use App\Domain\Payments\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payments\ListRefundsRequest;
use Illuminate\Http\JsonResponse;

final class ListRefundsController extends Controller
{
    public function __invoke(
        int $branch_id,
        ListRefundsRequest $request,
        ListRefundsHandler $handler,
    ): JsonResponse {
        $status = $request->validated('status');

        $refunds = $handler->handle(
            new ListRefundsQuery(
                branchId: $branch_id,
                status: $status !== null
                    ? RefundStatus::from($status)
                    : null,
            )
        );

        return $this->successResponse(
            data: array_map(
                static fn($refund) => $refund->toArray(),
                $refunds
            ),
        );
    }
}
