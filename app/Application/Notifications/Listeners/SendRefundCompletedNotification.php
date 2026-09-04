<?php

namespace App\Application\Notifications\Listeners;

use App\Application\Notifications\Mail\RefundCompletedNotification;
use App\Application\Payments\DTOs\PaymentRefundDto;
use App\Domain\Notifications\Entities\EmailLog;
use App\Domain\Notifications\Enums\EmailLogStatus;
use App\Domain\Notifications\Repositories\EmailLogRepository;
use App\Domain\Payments\Events\RefundCompleted;
use App\Domain\Payments\Repositories\PaymentRefundRepository;
use App\Domain\Reservations\Repositories\ReservationRepository;
use App\Domain\Users\Repositories\UserRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

final class SendRefundCompletedNotification implements ShouldQueue
{
    public function __construct(
        private PaymentRefundRepository $refunds,
        private ReservationRepository $reservations,
        private UserRepository $users,
        private EmailLogRepository $emailLogs,
    ) {}

    public function handle(RefundCompleted $event): void
    {
        $refund = $this->refunds->findById(
            $event->refundId
        );

        if ($refund === null) {
            throw new RuntimeException(
                "No se encontró la devolución {$event->refundId}."
            );
        }

        $reservation = $this->reservations->findById(
            $refund->getReservationId()
        );

        if ($reservation === null) {
            throw new RuntimeException(
                "No se encontró la reserva de la devolución {$event->refundId}."
            );
        }

        $toEmail = $reservation->getGuestEmail();

        if ($toEmail === null && $reservation->getCustomerUserId() !== null) {
            $user = $this->users->findById(
                $reservation->getCustomerUserId()
            );

            if ($user === null) {
                throw new RuntimeException(
                    "No se encontró el usuario de la reserva."
                );
            }

            $toEmail = $user->email()->value();
        }

        if ($toEmail === null) {
            throw new RuntimeException(
                "La reserva {$reservation->getId()} no tiene email."
            );
        }

        $dto = PaymentRefundDto::fromDomain(
            $refund
        );

        $emailLog = $this->emailLogs->save(
            new EmailLog(
                id: null,
                toEmail: $toEmail,
                subject: 'Devolución realizada',
                notificationType: RefundCompletedNotification::class,
                template: null,
                payload: [
                    'refund_id' => $refund->getId(),
                    'reservation_id' => $refund->getReservationId(),
                    'amount' => $refund->getAmount(),
                    'method' => $refund->getMethod()?->value,
                ],
                status: EmailLogStatus::PENDING,
                errorMessage: null,
                sentAt: null,
            )
        );

        try {
            Notification::route('mail', $toEmail)
                ->notify(
                    new RefundCompletedNotification($dto)
                );

            $emailLog->markSent();

            $this->emailLogs->update(
                $emailLog
            );
        } catch (Throwable $exception) {
            $emailLog->markFailed(
                $exception->getMessage()
            );

            $this->emailLogs->update(
                $emailLog
            );

            throw $exception;
        }
    }
}
