<?php

namespace App\Http\Controllers\Reservations\FixedReservations;

use App\Application\Reservations\FixedReservations\Get\GetFixedReservationsHandler;
use App\Application\Reservations\FixedReservations\Get\GetFixedReservationsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetFixedReservationsController extends Controller
{
    public function __invoke(
        int $club_id,
        GetFixedReservationsHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(
            new GetFixedReservationsQuery(
                clubId: $club_id
            )
        );

        return $this->successResponse(
            data: array_map(
                fn($item) => $item->toArray(),
                $result
            ),
            message: 'Reservas fijas obtenidas correctamente.',
        );
    }
}
