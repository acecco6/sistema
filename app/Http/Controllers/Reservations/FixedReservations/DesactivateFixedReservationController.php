<?php

namespace App\Http\Controllers\Reservations\FixedReservations;

use App\Application\Reservations\FixedReservations\Deactivate\DeactivateFixedReservationCommand;
use App\Application\Reservations\FixedReservations\Deactivate\DeactivateFixedReservationHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class DesactivateFixedReservationController extends Controller
{
    public function __invoke(
        int $id,
        DeactivateFixedReservationHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(
            new DeactivateFixedReservationCommand(
                id: $id
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Reserva fija desactivada correctamente.',
        );
    }
}
