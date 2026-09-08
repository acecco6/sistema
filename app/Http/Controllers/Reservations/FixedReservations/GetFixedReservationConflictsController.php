<?php

namespace App\Http\Controllers\Reservations\FixedReservations;

use App\Application\Reservations\FixedReservations\Conflicts\Get\GetFixedReservationConflictsHandler;
use App\Application\Reservations\FixedReservations\Conflicts\Get\GetFixedReservationConflictsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetFixedReservationConflictsController extends Controller
{
    public function __invoke(
        int $club_id,
        Request $request,
        GetFixedReservationConflictsHandler $handler,
    ): JsonResponse {
        $resolved = null;

        if ($request->has('resolved')) {
            $resolved = $request->boolean(
                'resolved'
            );
        }

        $results = $handler->handle(
            new GetFixedReservationConflictsQuery(
                clubId: $club_id,
                resolved: $resolved,
            )
        );

        return $this->successResponse(
            data: array_map(
                fn($dto) => $dto->toArray(),
                $results
            ),
            message: 'Conflictos de reservas fijas obtenidos correctamente.',
        );
    }
}
