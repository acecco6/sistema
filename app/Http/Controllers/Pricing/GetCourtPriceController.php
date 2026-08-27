<?php

namespace App\Http\Controllers\Pricing;


use App\Application\Pricing\Get\GetCourtPricesHandler;
use App\Application\Pricing\Get\GetCourtPricesQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetCourtPriceController extends Controller
{
    public function __invoke(
        int $branch_id,
        GetCourtPricesHandler $handler,
    ): JsonResponse {

        $result = $handler->handle(
            new GetCourtPricesQuery(
                branchId: $branch_id
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
