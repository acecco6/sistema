<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Availability\GetTipoCourtAvailabilityHandler;
use App\Application\Reservations\Availability\GetTipoCourtAvailabilityQuery;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GetTipoCourtAvailabilityController extends Controller
{
    public function __invoke(
        int $branch_id,
        Request $request,
        GetTipoCourtAvailabilityHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'tipo_court_id' => [
                'required',
                'integer',
                'exists:tipos_court,id',
            ],

            'date' => [
                'required',
                'date_format:Y-m-d',
            ],

            'start_time' => [
                'nullable',
                'date_format:H:i:s',
                'required_with:end_time',
            ],

            'end_time' => [
                'nullable',
                'date_format:H:i:s',
                'required_with:start_time',
                'after:start_time',
            ],
        ]);

        $result = $handler->handle(
            new GetTipoCourtAvailabilityQuery(
                branchId: $branch_id,
                tipoCourtId: $validated['tipo_court_id'],
                date: new DateTimeImmutable(
                    $validated['date']
                ),
                startTime: $validated['start_time'] ?? null,
                endTime: $validated['end_time'] ?? null,
            )
        );

        return $this->successResponse(
            data: $result->toArray()
        );
    }
}
