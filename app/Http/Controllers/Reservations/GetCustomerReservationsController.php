<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Customer\GetCustomerReservationsHandler;
use App\Application\Reservations\Customer\GetCustomerReservationsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetCustomerReservationsController extends Controller
{
    public function __invoke(Request $request, GetCustomerReservationsHandler $handler): JsonResponse
    {

        $result = $handler->handle(
            new GetCustomerReservationsQuery(
                customerUserId: (int) $request->user()->id,
            )
        );

        return $this->successResponse(data: array_map(fn($dto) => $dto->toArray(), $result));
    }
}
