<?php

namespace App\Http\Controllers\Reservations\FixedReservations;

use App\Application\Reservations\FixedReservations\Create\CreateFixedReservationCommand;
use App\Application\Reservations\FixedReservations\Create\CreateFixedReservationSlotData;
use App\Application\Reservations\FixedReservations\Create\CreateFixedReservationWithOccurrencesHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\FixedReservations\CreateFixedReservationRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class CreateFixedReservationController extends Controller
{
    public function __invoke(
        int $club_id,
        CreateFixedReservationRequest $request,
        CreateFixedReservationWithOccurrencesHandler $handler,
    ): JsonResponse {
        $validated = $request->validated();

        $slots = array_map(
            fn(array $slot) =>
            new CreateFixedReservationSlotData(
                courtId: (int) $slot['court_id'],
                dayOfWeek: (int) $slot['day_of_week'],
                startTime: $slot['start_time'],
                durationMinutes: (int) $slot['duration_minutes'],
            ),
            $validated['slots']
        );

        $result = $handler->handle(
            new CreateFixedReservationCommand(
                clubId: $club_id,

                customerUserId: isset($validated['customer_user_id'])
                    ? (int) $validated['customer_user_id']
                    : null,

                guestName: $validated['guest_name'] ?? null,

                guestEmail: $validated['guest_email'] ?? null,

                guestPhone: $validated['guest_phone'] ?? null,

                createdByUserId: (int) $request->user()->id,

                startsOn: new DateTimeImmutable(
                    $validated['starts_on']
                ),

                endsOn: isset($validated['ends_on'])
                    ? new DateTimeImmutable(
                        $validated['ends_on']
                    )
                    : null,

                notes: $validated['notes'] ?? null,

                slots: $slots,
                clubCustomerId: isset($validated['club_customer_id']) ? (int) $validated['club_customer_id'] : null,
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Reserva fija creada correctamente.',
            code: 201,
        );
    }
}
