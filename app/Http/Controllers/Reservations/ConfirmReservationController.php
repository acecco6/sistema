<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Confirm\ConfirmReservationCommand;
use App\Application\Reservations\Confirm\ConfirmReservationHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ConfirmReservationController extends Controller
{
    public function __invoke(int $id, ConfirmReservationHandler $handler): JsonResponse
    {
        $result = $handler->handle(new ConfirmReservationCommand(id: $id));

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Reserva confirmada correctamente.'
        );
    }
}
