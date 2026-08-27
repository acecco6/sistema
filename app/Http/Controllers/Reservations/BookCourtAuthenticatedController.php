<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Reservations\Create\CreateReservationCommand;
use App\Application\Reservations\Create\CreateReservationHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\AuthenticatedCustomerReservationRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class BookCourtAuthenticatedController extends Controller
{
    public function __invoke(int $court_id, AuthenticatedCustomerReservationRequest $request, CreateReservationHandler $handler,): JsonResponse
    {
        $validated = $request->validated();
        $userId = (int) $request->user()->id;
        $result = $handler->handle(
            new CreateReservationCommand(
                courtId: $court_id,
                customerUserId: $userId,
                createdByUserId: $userId,
                guestName: null,
                guestEmail: null,
                guestPhone: null,
                startsAt: new DateTimeImmutable($validated['starts_at']),
                endsAt: new DateTimeImmutable(
                    $validated['ends_at']
                ),

                notes: $validated['notes'] ?? null,

                /*
                 * Por ahora la dejamos PENDING.
                 *
                 * Esto nos prepara para Payments.
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
