<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Create\CreateReservationCommand;
use App\Application\Reservations\Create\CreateReservationHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\GuestReservationRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class BookCourtGuestController extends Controller
{
    public function __invoke(int $court_id, GuestReservationRequest $request, CreateReservationHandler $handler): JsonResponse
    {
        $validated = $request->validated();

        $result = $handler->handle(
            new CreateReservationCommand(
                courtId: $court_id,
                customerUserId: null,
                createdByUserId: null,
                guestName: $validated['name'],
                guestEmail: $validated['email'] ?? null,
                guestPhone: $validated['phone'] ?? null,
                startsAt: new DateTimeImmutable($validated['starts_at']),
                endsAt: new DateTimeImmutable($validated['ends_at']),
                notes: $validated['notes'] ?? null,
                /*
                 * Invitado:
                 * siempre comienza PENDING.
                 */
                confirmed: false,
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Reserva creada correctamente.',
            code: 201,
        );
    }
}
