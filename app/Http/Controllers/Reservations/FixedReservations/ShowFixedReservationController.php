<?php

namespace App\Http\Controllers\Reservations\FixedReservations;

use App\Application\Reservations\FixedReservations\Show\ShowFixedReservationHandler;
use App\Application\Reservations\FixedReservations\Show\ShowFixedReservationQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ShowFixedReservationController extends Controller
{
    public function __invoke(
        int $id,
        ShowFixedReservationHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(
            new ShowFixedReservationQuery(
                id: $id
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Reserva fija obtenida correctamente.',
        );
    }
}
