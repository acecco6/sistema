<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Payments\CreateCheckout\CreatePaymentCheckoutCommand;
use App\Application\Payments\CreateCheckout\CreatePaymentCheckoutHandler;
use App\Application\Reservations\Create\CreateReservationCommand;
use App\Application\Reservations\Create\CreateReservationHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\GuestReservationRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class BookCourtGuestController extends Controller
{
    public function __invoke(
        int $court_id,
        GuestReservationRequest $request,
        CreateReservationHandler $reservationHandler,
        CreatePaymentCheckoutHandler $paymentCheckoutHandler,
    ): JsonResponse {
        $validated = $request->validated();

        $reservation = $reservationHandler->handle(
            new CreateReservationCommand(
                courtId: $court_id,
                customerUserId: null,
                createdByUserId: null,
                guestName: $validated['name'],
                guestEmail: $validated['email'] ?? null,
                guestPhone: $validated['phone'] ?? null,
                startsAt: new DateTimeImmutable(
                    $validated['starts_at']
                ),
                endsAt: new DateTimeImmutable(
                    $validated['ends_at']
                ),
                notes: $validated['notes'] ?? null,

                /*
                 * Guest:
                 * siempre comienza PENDING y debe abonar
                 * la seña requerida para confirmar.
                 */
                confirmed: false,
            )
        );

        $payment = $paymentCheckoutHandler(
            new CreatePaymentCheckoutCommand(
                reservationId: $reservation->id,
                payerEmail: $validated['email'] ?? null,
            )
        );

        $data = $reservation->toArray();

        /*
         * El public_token solamente se devuelve
         * al crear la reserva guest.
         */
        $data['public_token'] = $reservation->publicToken;

        $data['payment'] = $payment->toArray();

        return $this->successResponse(
            data: $data,
            message: 'Reserva creada. Tenés 15 minutos para realizar el pago.',
            code: 201,
        );
    }
}
