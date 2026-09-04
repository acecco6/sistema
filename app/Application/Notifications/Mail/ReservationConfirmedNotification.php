<?php

namespace App\Application\Notifications\Mail;

use App\Application\Reservations\DTOs\ReservationDto;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ReservationConfirmedNotification extends Notification
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
            ->subject('Reserva confirmada')
            ->greeting('¡Tu reserva fue confirmada!')
            ->line('La reserva fue confirmada correctamente.')
            ->line('Fecha: ' . $this->reservation->startsAt)
            ->line('Hasta: ' . $this->reservation->endsAt)
            ->line('Total: $' . $this->reservation->totalPrice)
            ->line('Gracias por reservar con nosotros.');
    }
}
