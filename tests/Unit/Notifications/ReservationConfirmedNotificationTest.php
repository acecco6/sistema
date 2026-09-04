<?php

namespace Tests\Unit\Notifications;

use App\Application\Notifications\Mail\ReservationConfirmedNotification;
use App\Application\Reservations\DTOs\ReservationDto;
use Tests\TestCase;

final class ReservationConfirmedNotificationTest extends TestCase
{
    public function test_construye_correctamente_el_mail_de_reserva_confirmada(): void
    {
        $reservation = new ReservationDto(
            id: 10,
            courtId: 5,
            customerUserId: 2,
            createdByUserId: null,

            guestName: null,
            guestEmail: null,
            guestPhone: null,

            startsAt: '2026-09-10 18:00:00',
            endsAt: '2026-09-10 19:00:00',

            totalPrice: '25000.00',
            status: 'confirmed',

            publicToken: 'test-token',

            notes: null,
            cancelledAt: null,
        );

        $notification = new ReservationConfirmedNotification(
            $reservation
        );

        $mail = $notification->toMail(
            new \stdClass()
        );

        $this->assertSame(
            'Reserva confirmada',
            $mail->subject
        );

        $this->assertSame(
            ['mail'],
            $notification->via(new \stdClass())
        );

        $content = implode(
            ' ',
            $mail->introLines
        );

        $this->assertStringContainsString(
            '2026-09-10 18:00:00',
            $content
        );

        $this->assertStringContainsString(
            '2026-09-10 19:00:00',
            $content
        );

        $this->assertStringContainsString(
            '25000.00',
            $content
        );
    }
}
