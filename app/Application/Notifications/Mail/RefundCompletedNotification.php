<?php

namespace App\Application\Notifications\Mail;

use App\Application\Payments\DTOs\PaymentRefundDto;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class RefundCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly PaymentRefundDto $refund,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Devolución realizada')
            ->greeting('Tu devolución fue realizada')
            ->line('Registramos correctamente la devolución del dinero.')
            ->line('Monto devuelto: $' . $this->refund->amount)
            ->line('Método: ' . ($this->refund->method ?? '-'));
    }
}
