<?php

namespace App\Http\Controllers\Reservations\FixedReservations;

use App\Application\Reservations\FixedReservations\Conflicts\Resolve\ResolveFixedReservationConflictCommand;
use App\Application\Reservations\FixedReservations\Conflicts\Resolve\ResolveFixedReservationConflictHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ResolveFixedReservationConflictController extends Controller
{
    public function __invoke(
        int $id,
        Request $request,
        ResolveFixedReservationConflictHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(
            new ResolveFixedReservationConflictCommand(
                id: $id,
                userId: (int) $request->user()->id,
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Conflicto marcado como resuelto correctamente.',
        );
    }
}
