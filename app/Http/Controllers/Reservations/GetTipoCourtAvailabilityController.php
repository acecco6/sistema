<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Availability\GetTipoCourtAvailabilityHandler;
use App\Application\Reservations\Availability\GetTipoCourtAvailabilityQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\GetTipoCourtAvailabilityRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class GetTipoCourtAvailabilityController extends Controller
{
    public function __invoke(int $branch_id, GetTipoCourtAvailabilityRequest $request, GetTipoCourtAvailabilityHandler $handler,): JsonResponse
    {
        $validated = $request->validated();
        $result = $handler->handle(
            new GetTipoCourtAvailabilityQuery(
                branchId: $branch_id,
                tipoCourtId: $validated['tipo_court_id'],
                date: new DateTimeImmutable(
                    $validated['date']
                ),
                durationMinutes: (int) ($validated['duration_minutes'] ?? 60),
                startTime: $validated['start_time'] ?? null,
                endTime: $validated['end_time'] ?? null,
            )
        );

        return $this->successResponse(
            data: $result->toArray()
        );
    }
}
