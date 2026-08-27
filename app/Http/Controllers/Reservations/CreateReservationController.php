<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Create\CreateReservationCommand;
use App\Application\Reservations\Create\CreateReservationHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\CreateReservationRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class CreateReservationController extends Controller
{
    public function __invoke(int $court_id, CreateReservationRequest $request, CreateReservationHandler $handler,): JsonResponse
    {
        $validated = $request->validated();

        $result = $handler->handle(
            new CreateReservationCommand(
                courtId: $court_id,
                customerUserId: $validated['customer_user_id'] ?? null,
                /*
                 * Como esta ruta es administrativa,
                 * quien crea la reserva es el usuario autenticado.
                 */
                createdByUserId: $request->user()->id,
                guestName: $validated['guest_name'] ?? null,
                guestEmail: $validated['guest_email'] ?? null,
                guestPhone: $validated['guest_phone'] ?? null,
                startsAt: new DateTimeImmutable($validated['starts_at']),
                endsAt: new DateTimeImmutable($validated['ends_at']),
                notes: $validated['notes'] ?? null,
                /*
                 * Reserva creada por personal del club:
                 * nace confirmada.
                 */
                confirmed: $validated['confirmed'] ?? false,
            )
        );

        return $this->successResponse(
            data: $result->toArray(),
            message: 'Reserva creada correctamente.',
            code: 201,
        );
    }
}
