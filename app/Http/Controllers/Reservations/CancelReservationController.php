<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Cancel\CancelReservationCommand;
use App\Application\Reservations\Cancel\CancelReservationHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class CancelReservationController extends Controller
{
    public function __invoke(int $id, CancelReservationHandler $handler): JsonResponse
    {
        $result = $handler->handle(new CancelReservationCommand($id));
        return $this->successResponse(data: $result->toArray(), message: 'Reserva cancelada correctamente.');
    }
}
