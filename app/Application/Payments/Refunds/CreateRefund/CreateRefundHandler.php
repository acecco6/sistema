<?php

namespace App\Application\Payments\Refunds\CreateRefund;

use App\Application\Payments\DTOs\PaymentRefundDto;
use App\Domain\Payments\Entities\PaymentRefund;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Exceptions\InvalidRefundAmountException;
use App\Domain\Payments\Exceptions\RefundExceedsRefundableAmountException;
use App\Domain\Payments\Repositories\PaymentRefundRepository;
use App\Domain\Payments\Repositories\PaymentRepository;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;
use Illuminate\Support\Facades\DB;

final class CreateRefundHandler
{
    public function __construct(
        private ReservationRepository $reservations,
        private PaymentRepository $payments,
        private PaymentRefundRepository $refunds,
    ) {}

    public function handle(
        CreateRefundCommand $command
    ): PaymentRefundDto {
        return DB::transaction(function () use ($command) {

            /*
             * Bloqueamos la reserva.
             *
             * Esto es importante porque dos admins podrían
             * intentar crear refunds al mismo tiempo.
             */
            $reservation = $this->reservations
                ->findByIdForUpdate(
                    $command->reservationId
                );

            if ($reservation === null) {
                throw new ReservationNotFoundException();
            }

            /*
             * Nunca usamos float para dinero.
             */
            if (bccomp($command->amount, '0.00', 2) <= 0) {
                throw new InvalidRefundAmountException();
            }

            /*
             * Total de dinero realmente cobrado.
             */
            $approvedAmount = $this->payments
                ->sumApprovedByReservation(
                    $reservation->getId()
                );

            /*
             * Refunds que ya están comprometidos.
             *
             * PENDING:
             * todavía hay que devolverlos.
             *
             * COMPLETED:
             * ya fueron devueltos.
             *
             * CANCELLED:
             * no participan.
             */
            $committedAmount = $this->refunds
                ->sumCommittedByReservation(
                    $reservation->getId()
                );

            /*
             * Lo que todavía podemos devolver.
             */
            $refundableAmount = bcsub(
                $approvedAmount,
                $committedAmount,
                2
            );

            /*
             * Por seguridad nunca trabajamos con negativo.
             */
            if (bccomp($refundableAmount, '0.00', 2) < 0) {
                $refundableAmount = '0.00';
            }

            /*
             * No permitimos comprometer más dinero
             * del efectivamente disponible.
             */
            if (
                bccomp(
                    $command->amount,
                    $refundableAmount,
                    2
                ) === 1
            ) {
                throw new RefundExceedsRefundableAmountException(
                    amount: $command->amount,
                    refundableAmount: $refundableAmount,
                );
            }

            $refund = new PaymentRefund(
                id: null,
                reservationId: $reservation->getId(),

                /*
                 * Seguimos trabajando el refund
                 * a nivel de reserva.
                 */
                paymentId: null,

                amount: bcadd(
                    $command->amount,
                    '0',
                    2
                ),

                status: RefundStatus::PENDING,

                reason: $command->reason,

                /*
                 * El método se conoce recién cuando
                 * efectivamente se devuelve el dinero.
                 */
                method: null,

                notes: null,

                createdByUserId: $command->createdByUserId,

                completedByUserId: null,

                completedAt: null,
            );

            $saved = $this->refunds->save(
                $refund
            );

            return PaymentRefundDto::fromDomain(
                $saved
            );
        }, 3);
    }
}
