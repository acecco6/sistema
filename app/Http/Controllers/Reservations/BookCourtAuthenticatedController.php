<?php

namespace App\Http\Controllers\Reservations;

use App\Application\Payments\CreateCheckout\CreatePaymentCheckoutCommand;
use App\Application\Payments\CreateCheckout\CreatePaymentCheckoutHandler;
use App\Application\Reservations\Create\CreateReservationCommand;
use App\Application\Reservations\Create\CreateReservationHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\AuthenticatedCustomerReservationRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;

final class BookCourtAuthenticatedController extends Controller
{
    public function __invoke(
        int $court_id,
        AuthenticatedCustomerReservationRequest $request,
        CreateReservationHandler $reservationHandler,
        CreatePaymentCheckoutHandler $paymentCheckoutHandler,
    ): JsonResponse {
        $validated = $request->validated();

        $user = $request->user();
        $userId = (int) $user->id;

        $reservation = $reservationHandler->handle(
            new CreateReservationCommand(
                courtId: $court_id,
                customerUserId: $userId,
                createdByUserId: $userId,
                guestName: null,
                guestEmail: null,
                guestPhone: null,
                startsAt: new DateTimeImmutable(
                    $validated['starts_at']
                ),
                endsAt: new DateTimeImmutable(
                    $validated['ends_at']
                ),
                notes: $validated['notes'] ?? null,

                /*
                 * Cliente:
                 * comienza PENDING y debe pagar
                 * la seña requerida para confirmar.
                 */
                confirmed: false,
            )
        );

        $payment = $paymentCheckoutHandler(
            new CreatePaymentCheckoutCommand(
                reservationId: $reservation->id,
                payerEmail: $user->email,
            )
        );

        $data = $reservation->toArray();
        $data['payment'] = $payment->toArray();

        return $this->successResponse(
            data: $data,
            message: 'Reserva creada. Tenés 15 minutos para realizar el pago.',
            code: 201,
        );
    }
}
