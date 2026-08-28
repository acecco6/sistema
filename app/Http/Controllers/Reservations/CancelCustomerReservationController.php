<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Cancel\CancelCustomerReservationCommand;
use App\Application\Reservations\Cancel\CancelCustomerReservationHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CancelCustomerReservationController extends Controller
{
    public function __invoke(int $id, Request $request, CancelCustomerReservationHandler $handler): JsonResponse
    {
        $result = $handler->handle(
            new CancelCustomerReservationCommand(
                reservationId: $id,
                customerUserId: (int) $request->user()->id,
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Reserva cancelada correctamente.'
        );
    }
}
