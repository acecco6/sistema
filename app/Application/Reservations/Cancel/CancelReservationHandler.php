<?php

namespace App\Application\Reservations\Cancel;

use App\Application\Reservations\DTOs\ReservationDto;
use App\Domain\Payments\Entities\PaymentRefund;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Repositories\PaymentRefundRepository;
use App\Domain\Payments\Repositories\PaymentRepository;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;
use Illuminate\Support\Facades\DB;

final class CancelReservationHandler
{
    public function __construct(
        private ReservationRepository $reservations,
        private PaymentRepository $payments,
        private PaymentRefundRepository $refunds,
    ) {}

    public function handle(CancelReservationCommand $command): ReservationDto
    {
        return DB::transaction(function () use ($command) {

            $reservation = $this->reservations
                ->findByIdForUpdate($command->id);

            if ($reservation === null) {
                throw new ReservationNotFoundException();
            }

            /*
             * Primero aplicamos las reglas de dominio existentes
             * de cancelación.
             *
             * Si cancel() lanza una excepción, no se modifica
             * la reserva ni se crea ningún refund.
             */
            $reservation->cancel(
                $command->cancelledAt
            );

            /*
             * La devolución es una decisión explícita.
             *
             * Tener pagos aprobados NO implica automáticamente
             * que haya que devolverlos.
             */
            if ($command->createRefund) {
                $this->createRefundIfNecessary(
                    reservationId: $reservation->getId(),
                    reason: $command->refundReason,
                    createdByUserId: $command->cancelledByUserId,
                );
            }

            $updated = $this->reservations->update(
                $reservation
            );

            return ReservationDto::fromDomain(
                $updated
            );
        }, 3);
    }

    private function createRefundIfNecessary(
        int $reservationId,
        ?string $reason,
        ?int $createdByUserId,
    ): void {
        /*
         * Dinero efectivamente cobrado.
         */
        $approvedAmount = $this->payments
            ->sumApprovedByReservation($reservationId);

        /*
         * Refunds ya comprometidos.
         *
         * PENDING + COMPLETED.
         *
         * CANCELLED no cuenta.
         */
        $committedAmount = $this->refunds
            ->sumCommittedByReservation($reservationId);

        /*
         * Monto que todavía puede generar una obligación
         * de devolución.
         */
        $refundableAmount = bcsub(
            $approvedAmount,
            $committedAmount,
            2
        );

        /*
         * No hubo pagos o ya existe un refund por todo
         * el dinero cobrado.
         */
        if (bccomp($refundableAmount, '0.00', 2) <= 0) {
            return;
        }

        $refund = new PaymentRefund(
            id: null,
            reservationId: $reservationId,

            /*
             * Es una devolución a nivel reserva.
             *
             * Puede haber varios Payments que formen el total.
             */
            paymentId: null,

            amount: $refundableAmount,
            status: RefundStatus::PENDING,
            reason: $reason,
            method: null,
            notes: null,
            createdByUserId: $createdByUserId,
            completedByUserId: null,
            completedAt: null,
        );

        $this->refunds->save($refund);
    }
}
