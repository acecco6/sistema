<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Collection\GetCourtReservationsHandler;
use App\Application\Reservations\Collection\GetCourtReservationsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\GetCourtReservationsRequest;
use Illuminate\Http\JsonResponse;

final class GetCourtReservationsController extends Controller
{
    public function __invoke(int $court_id, GetCourtReservationsRequest $request, GetCourtReservationsHandler $handler): JsonResponse
    {
        $validated = $request->validated();

        $result = $handler->handle(new GetCourtReservationsQuery($court_id, $validated['date']));

        return $this->successResponse(data: array_map(fn($dto) => $dto->toArray(), $result));
    }
}
