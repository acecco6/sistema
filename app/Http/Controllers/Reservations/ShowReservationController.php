<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Show\ShowReservationHandler;
use App\Application\Reservations\Show\ShowReservationQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ShowReservationController extends Controller
{
    public function __invoke(int $id, ShowReservationHandler $handler): JsonResponse
    {
        $result = $handler->handle(new ShowReservationQuery($id));
        return $this->successResponse(data: $result->toArray());
    }
}
