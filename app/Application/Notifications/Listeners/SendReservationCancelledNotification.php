<?php

namespace App\Application\Notifications\Listeners;

use App\Application\Notifications\Mail\ReservationCancelledNotification;
use App\Application\Reservations\DTOs\ReservationDto;
use App\Domain\Notifications\Entities\EmailLog;
use App\Domain\Notifications\Enums\EmailLogStatus;
use App\Domain\Notifications\Repositories\EmailLogRepository;
use App\Domain\Reservations\Events\ReservationCancelled;
use App\Domain\Reservations\Repositories\ReservationRepository;
use App\Domain\Users\Repositories\UserRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

final class SendReservationCancelledNotification implements ShouldQueue
{
    public function __construct(
        private ReservationRepository $reservations,
        private UserRepository $users,
        private EmailLogRepository $emailLogs,
    ) {}

    public function handle(ReservationCancelled $event): void
    {
        $reservation = $this->reservations->findById(
            $event->reservationId
        );

        if ($reservation === null) {
            throw new RuntimeException(
                "No se encontró la reserva {$event->reservationId}."
            );
        }

        $toEmail = $reservation->getGuestEmail();

        if ($toEmail === null && $reservation->getCustomerUserId() !== null) {
            $user = $this->users->findById(
                $reservation->getCustomerUserId()
            );

            if ($user === null) {
                throw new RuntimeException(
                    "No se encontró el usuario de la reserva {$event->reservationId}."
                );
            }

            $toEmail = $user->email()->value();
        }

        if ($toEmail === null) {
            throw new RuntimeException(
                "La reserva {$event->reservationId} no tiene email."
            );
        }

        $dto = ReservationDto::fromDomain($reservation);

        $emailLog = $this->emailLogs->save(
            new EmailLog(
                id: null,
                toEmail: $toEmail,
                subject: 'Reserva cancelada',
                notificationType: ReservationCancelledNotification::class,
                template: null,
                payload: [
                    'reservation_id' => $reservation->getId(),
                    'starts_at' => $dto->startsAt,
                    'ends_at' => $dto->endsAt,
                    'total_price' => $dto->totalPrice,
                ],
                status: EmailLogStatus::PENDING,
                errorMessage: null,
                sentAt: null,
            )
        );

        try {
            Notification::route('mail', $toEmail)
                ->notify(
                    new ReservationCancelledNotification($dto)
                );

            $emailLog->markSent();

            $this->emailLogs->update($emailLog);
        } catch (Throwable $exception) {
            $emailLog->markFailed(
                $exception->getMessage()
            );

            $this->emailLogs->update($emailLog);

            throw $exception;
        }
    }
}
