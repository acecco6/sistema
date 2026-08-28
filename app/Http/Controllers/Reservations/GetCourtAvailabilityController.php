<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Availability\GetCourtAvailabilityHandler;
use App\Application\Reservations\Availability\GetCourtAvailabilityQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\GetCourtAvailabilityRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class GetCourtAvailabilityController extends Controller
{
    public function __invoke(int $court_id, GetCourtAvailabilityRequest $request, GetCourtAvailabilityHandler $handler): JsonResponse
    {
        $validated = $request->validated();

        $query = new GetCourtAvailabilityQuery(
            courtId: $court_id,
            date: new DateTimeImmutable($validated['date']),
            durationMinutes: (int) ($validated['duration_minutes'] ?? 60),
        );
        $result = $handler->handle($query);

        return $this->successResponse(data: $result->toArray());
    }
}
