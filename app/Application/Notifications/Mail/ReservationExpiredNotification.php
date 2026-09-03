<?php

namespace App\Application\Notifications\Mail;

use App\Application\Reservations\DTOs\ReservationDto;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ReservationExpiredNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ReservationDto $reservation,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reserva vencida')
            ->greeting('Tu reserva venció')
            ->line('La reserva venció porque no se completó el pago dentro del tiempo disponible.')
            ->line('Fecha: ' . $this->reservation->startsAt)
            ->line('Total: $' . $this->reservation->totalPrice);
    }
}
