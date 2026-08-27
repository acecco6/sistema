<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Collection\GetCourtReservationsHandler;
use App\Application\Reservations\Collection\GetCourtReservationsQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class GetCourtReservationsController extends Controller
{
    public function __invoke(int $court_id, GetCourtReservationsHandler $handler): JsonResponse
    {
        $result = $handler->handle(new GetCourtReservationsQuery($court_id));
        return $this->successResponse(data: array_map(fn($dto) => $dto->toArray(), $result));
    }
}
