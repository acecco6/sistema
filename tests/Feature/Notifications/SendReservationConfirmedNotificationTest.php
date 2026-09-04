<?php

namespace Tests\Feature\Notifications;

use App\Application\Notifications\Listeners\SendReservationConfirmedNotification;
use App\Application\Notifications\Mail\ReservationConfirmedNotification;
use App\Domain\Notifications\Enums\EmailLogStatus;
use App\Domain\Reservations\Events\ReservationConfirmed;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class SendReservationConfirmedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_evento_reservation_confirmed_encola_el_listener(): void
    {
        Queue::fake();

        $reservation = Reservation::factory()
            ->guest()
            ->confirmed()
            ->create();

        ReservationConfirmed::dispatch(
            $reservation->id
        );

        Queue::assertPushed(
            CallQueuedListener::class,
            function (CallQueuedListener $job) {
                return $job->class
                    === SendReservationConfirmedNotification::class;
            }
        );
    }

    public function test_envia_notificacion_a_reserva_guest_y_marca_email_log_como_sent(): void
    {
        Notification::fake();

        $reservation = Reservation::factory()
            ->guest()
            ->confirmed()
            ->create([
                'guest_email' => 'guest@example.com',
                'total_price' => '25000.00',
            ]);

        $listener = $this->app->make(
            SendReservationConfirmedNotification::class
        );

        $listener->handle(
            new ReservationConfirmed(
                reservationId: $reservation->id,
            )
        );

        Notification::assertSentOnDemand(
            ReservationConfirmedNotification::class,
            function (
                ReservationConfirmedNotification $notification,
                array $channels,
                object $notifiable
            ) {
                return in_array('mail', $channels, true)
                    && $notifiable->routes['mail'] === 'guest@example.com';
            }
        );

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'guest@example.com',
            'subject' => 'Reserva confirmada',
            'notification_type' => ReservationConfirmedNotification::class,
            'status' => EmailLogStatus::SENT->value,
            'error_message' => null,
        ]);

        $emailLog = \App\Models\EmailLog::query()
            ->where('to_email', 'guest@example.com')
            ->firstOrFail();

        $this->assertNotNull(
            $emailLog->sent_at
        );

        $this->assertSame(
            $reservation->id,
            $emailLog->payload['reservation_id']
        );

        $this->assertSame(
            '25000.00',
            $emailLog->payload['total_price']
        );
    }

    public function test_envia_notificacion_al_email_del_usuario_registrado(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'cliente@example.com',
        ]);

        $reservation = Reservation::factory()
            ->forCustomer($user)
            ->confirmed()
            ->create();

        $listener = $this->app->make(
            SendReservationConfirmedNotification::class
        );

        $listener->handle(
            new ReservationConfirmed(
                reservationId: $reservation->id,
            )
        );

        Notification::assertSentOnDemand(
            ReservationConfirmedNotification::class,
            function (
                ReservationConfirmedNotification $notification,
                array $channels,
                object $notifiable
            ) {
                return in_array('mail', $channels, true)
                    && $notifiable->routes['mail'] === 'cliente@example.com';
            }
        );

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'cliente@example.com',
            'status' => EmailLogStatus::SENT->value,
        ]);
    }

    public function test_si_falla_el_envio_email_log_queda_failed(): void
    {
        $reservation = Reservation::factory()
            ->guest()
            ->confirmed()
            ->create([
                'guest_email' => 'error@example.com',
            ]);

        $mailChannel = Mockery::mock(MailChannel::class);

        $mailChannel->shouldReceive('send')
            ->once()
            ->andThrow(
                new RuntimeException('Error enviando email')
            );

        $this->app->instance(
            MailChannel::class,
            $mailChannel
        );

        $listener = $this->app->make(
            SendReservationConfirmedNotification::class
        );

        try {
            $listener->handle(
                new ReservationConfirmed(
                    reservationId: $reservation->id,
                )
            );

            $this->fail(
                'Se esperaba una excepción durante el envío.'
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Error enviando email',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'error@example.com',
            'status' => EmailLogStatus::FAILED->value,
            'error_message' => 'Error enviando email',
        ]);

        $emailLog = \App\Models\EmailLog::query()
            ->where('to_email', 'error@example.com')
            ->firstOrFail();

        $this->assertNull(
            $emailLog->sent_at
        );
    }


    public function test_no_envia_notificacion_si_la_reserva_no_existe(): void
    {
        Notification::fake();

        $listener = $this->app->make(
            SendReservationConfirmedNotification::class
        );

        try {
            $listener->handle(
                new ReservationConfirmed(
                    reservationId: 999999,
                )
            );

            $this->fail(
                'Se esperaba una excepción.'
            );
        } catch (RuntimeException) {
            //
        }

        Notification::assertNothingSent();

        $this->assertDatabaseCount(
            'email_logs',
            0
        );
    }
}
