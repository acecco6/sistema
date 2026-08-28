<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Availability\GetCourtAvailabilityHandler;
use App\Application\Reservations\Availability\GetCourtAvailabilityQuery;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetCourtAvailabilityController extends Controller
{
    public function __invoke(int $court_id, Request $request, GetCourtAvailabilityHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'duration_minutes' => [
                'sometimes',
                'integer',
                'min:60',
            ],
        ]);

        $query = new GetCourtAvailabilityQuery(
            courtId: $court_id,
            date: new DateTimeImmutable($validated['date']),
            durationMinutes: (int) ($validated['duration_minutes'] ?? 60),
        );
        $result = $handler->handle($query);

        return $this->successResponse(data: $result->toArray());
    }
}
