<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Customer\ShowCustomerReservationHandler;
use App\Application\Reservations\Customer\ShowCustomerReservationQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowCustomerReservationController extends Controller
{
    public function __invoke(int $id, Request $request, ShowCustomerReservationHandler $handler): JsonResponse
    {

        $result = $handler->handle(
            new ShowCustomerReservationQuery(
                reservationId: $id,
                customerUserId: (int) $request->user()->id,
            )
        );

        return $this->successResponse(data: $result->toArray());
    }
}
