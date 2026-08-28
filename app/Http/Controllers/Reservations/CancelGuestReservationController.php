<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Guest\CancelGuestReservationCommand;
use App\Application\Reservations\Guest\CancelGuestReservationHandler;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class CancelGuestReservationController extends Controller
{
    public function __invoke(string $token, CancelGuestReservationHandler $handler): JsonResponse
    {
        $result = $handler->handle(new CancelGuestReservationCommand(publicToken: $token));
        return $this->successResponse(data: $result->toArray(), message: 'Reserva cancelada correctamente.');
    }
}
