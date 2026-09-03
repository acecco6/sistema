<?php

namespace App\Application\Notifications\Listeners;

use App\Application\Notifications\Mail\ReservationConfirmedNotification;
use App\Application\Reservations\DTOs\ReservationDto;
use App\Domain\Notifications\Entities\EmailLog;
use App\Domain\Notifications\Enums\EmailLogStatus;
use App\Domain\Notifications\Repositories\EmailLogRepository;
use App\Domain\Reservations\Events\ReservationConfirmed;
use App\Domain\Reservations\Repositories\ReservationRepository;
use App\Domain\Users\Repositories\UserRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

final class SendReservationConfirmedNotification implements ShouldQueue
{
    public function __construct(
        private ReservationRepository $reservations,
        private UserRepository $users,
        private EmailLogRepository $emailLogs,
    ) {}

    public function handle(ReservationConfirmed $event): void
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
                "La reserva {$event->reservationId} no tiene email de destino."
            );
        }

        $reservationDto = ReservationDto::fromDomain(
            $reservation
        );

        $emailLog = $this->emailLogs->save(
            new EmailLog(
                id: null,
                toEmail: $toEmail,
                subject: 'Reserva confirmada',
                notificationType: ReservationConfirmedNotification::class,
                template: null,
                payload: [
                    'reservation_id' => $reservation->getId(),
                    'starts_at' => $reservationDto->startsAt,
                    'ends_at' => $reservationDto->endsAt,
                    'total_price' => $reservationDto->totalPrice,
                ],
                status: EmailLogStatus::PENDING,
                errorMessage: null,
                sentAt: null,
            )
        );

        try {
            Notification::route(
                'mail',
                $toEmail
            )->notify(
                new ReservationConfirmedNotification(
                    $reservationDto
                )
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
