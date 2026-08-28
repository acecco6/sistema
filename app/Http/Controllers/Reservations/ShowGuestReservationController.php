<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Guest\ShowGuestReservationHandler;
use App\Application\Reservations\Guest\ShowGuestReservationQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ShowGuestReservationController extends Controller
{
    public function __invoke(string $token, ShowGuestReservationHandler $handler): JsonResponse
    {

        $result = $handler->handle(
            new ShowGuestReservationQuery(publicToken: $token)
        );

        return $this->successResponse(data: $result->toArray());
    }
}
