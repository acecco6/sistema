<?php

namespace App\Application\Payments\RegisterManualPayment;

use App\Application\Payments\DTOs\ReservationPaymentSummary;
use App\Application\Payments\Services\ReservationPaymentSummaryService;
use App\Domain\Payments\Entities\Payment;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Exceptions\InvalidManualPaymentMethodException;
use App\Domain\Payments\Exceptions\InvalidPaymentAmountException;
use App\Domain\Payments\Exceptions\PaymentExceedsRemainingAmountException;
use App\Domain\Payments\Repositories\PaymentRepository;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

final class RegisterManualPaymentHandler
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly PaymentRepository $paymentRepository,
        private readonly ReservationPaymentSummaryService $paymentSummaryService,
    ) {}

    public function __invoke(RegisterManualPaymentCommand $command): ReservationPaymentSummary
    {
        return DB::transaction(function () use ($command) {

            /*
             * Bloqueamos la reserva durante toda la operación.
             *
             * Esto evita que dos empleados registren pagos
             * simultáneamente usando el mismo saldo pendiente.
             */
            $reservation = $this->reservationRepository->findByIdForUpdate($command->reservationId);

            if ($reservation === null) {
                throw new ReservationNotFoundException();
            }

            /*
             * Nunca usamos float para dinero.
             */
            if (bccomp($command->amount, '0.00', 2) <= 0) {
                throw new InvalidPaymentAmountException();
            }

            /*
             * Mercado Pago solo puede aprobarse mediante
             * el flujo checkout + webhook.
             */
            if ($command->method === PaymentMethod::MERCADO_PAGO) {
                throw new InvalidManualPaymentMethodException();
            }

            /*
            * Calculamos el estado financiero real de la reserva.
            *
            * Esto tiene en cuenta:
            * - pagos APPROVED
            * - refunds COMPLETED
            * - net_paid_amount
            * - remaining_amount
            */

            $summary = $this->paymentSummaryService->calculate(
                $reservation
            );

            $remainingAmount = $summary->remainingAmount;

            /*
             * Si por algún motivo ya existe sobrepago,
             * consideramos saldo 0.
             */
            if (bccomp($remainingAmount, '0.00', 2) < 0) {
                $remainingAmount = '0.00';
            }

            /*
             * No permitimos registrar manualmente más
             * dinero que el saldo pendiente.
             */
            if (bccomp($command->amount, $remainingAmount, 2) === 1) {
                throw new PaymentExceedsRemainingAmountException(amount: $command->amount, remainingAmount: $remainingAmount,);
            }

            /*
             * Los pagos manuales nacen APPROVED.
             *
             * No tienen provider, checkout ni payment id
             * externo porque el dinero fue recibido por
             * fuera de Mercado Pago.
             */
            $payment = new Payment(
                id: null,
                reservationId: $reservation->getId(),
                amount: bcadd($command->amount, '0', 2),
                method: $command->method,
                status: PaymentStatus::APPROVED,
                provider: null,
                providerPreferenceId: null,
                providerPaymentId: null,
                externalReference: sprintf('MANUAL-%s', Uuid::uuid4()->toString()),
                checkoutUrl: null,
                createdByUserId: $command->createdByUserId,
                paidAt: new DateTimeImmutable(),
            );

            $this->paymentRepository->save($payment);

            /*
             * Recalculamos después de guardar el pago.
             */
            return $this->paymentSummaryService->calculate($reservation);
        }, 3);
    }
}
