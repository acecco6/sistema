<?php

namespace Tests\Feature\Notifications;

use App\Application\Notifications\Listeners\SendReservationCancelledNotification;
use App\Application\Notifications\Mail\ReservationCancelledNotification;
use App\Application\Reservations\Cancel\CancelCustomerReservationCommand;
use App\Application\Reservations\Cancel\CancelCustomerReservationHandler;
use App\Application\Reservations\Cancel\CancelReservationCommand;
use App\Application\Reservations\Cancel\CancelReservationHandler;
use App\Application\Reservations\Guest\CancelGuestReservationCommand;
use App\Application\Reservations\Guest\CancelGuestReservationHandler;
use App\Domain\Notifications\Enums\EmailLogStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Events\ReservationCancelled;
use App\Domain\Reservations\Exceptions\InvalidReservationStatusTransitionException;
use App\Models\EmailLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class ReservationCancelledNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelacion_staff_dispara_reservation_cancelled_una_vez(): void
    {
        Event::fake([
            ReservationCancelled::class,
        ]);

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'starts_at' => now()->addDays(2),
                'ends_at' => now()->addDays(2)->addHour(),
            ]);

        $this->app
            ->make(CancelReservationHandler::class)
            ->handle(
                new CancelReservationCommand(
                    id: $reservation->id,
                )
            );

        Event::assertDispatched(
            ReservationCancelled::class,
            fn(ReservationCancelled $event) =>
            $event->reservationId === $reservation->id
        );

        Event::assertDispatchedTimes(
            ReservationCancelled::class,
            1
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CANCELLED->value,
        ]);
    }

    public function test_cancelacion_customer_dispara_reservation_cancelled_una_vez(): void
    {
        Event::fake([
            ReservationCancelled::class,
        ]);

        $customer = User::factory()->createOne();

        $reservation = Reservation::factory()
            ->forCustomer($customer)
            ->confirmed()
            ->createOne([
                'starts_at' => now()->addDays(2),
                'ends_at' => now()->addDays(2)->addHour(),
            ]);

        $this->app
            ->make(CancelCustomerReservationHandler::class)
            ->handle(
                new CancelCustomerReservationCommand(
                    reservationId: $reservation->id,
                    customerUserId: $customer->id,
                )
            );

        Event::assertDispatched(
            ReservationCancelled::class,
            fn(ReservationCancelled $event) =>
            $event->reservationId === $reservation->id
        );

        Event::assertDispatchedTimes(
            ReservationCancelled::class,
            1
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CANCELLED->value,
        ]);
    }

    public function test_cancelacion_guest_dispara_reservation_cancelled_una_vez(): void
    {
        Event::fake([
            ReservationCancelled::class,
        ]);

        $reservation = Reservation::factory()
            ->guest()
            ->confirmed()
            ->createOne([
                'starts_at' => now()->addDays(2),
                'ends_at' => now()->addDays(2)->addHour(),
            ]);

        $this->app
            ->make(CancelGuestReservationHandler::class)
            ->handle(
                new CancelGuestReservationCommand(
                    publicToken: $reservation->public_token,
                )
            );

        Event::assertDispatched(
            ReservationCancelled::class,
            fn(ReservationCancelled $event) =>
            $event->reservationId === $reservation->id
        );

        Event::assertDispatchedTimes(
            ReservationCancelled::class,
            1
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CANCELLED->value,
        ]);
    }

    public function test_cancelacion_invalida_no_dispara_evento(): void
    {
        Event::fake([
            ReservationCancelled::class,
        ]);

        $reservation = Reservation::factory()
            ->completed()
            ->createOne();

        try {
            $this->app
                ->make(CancelReservationHandler::class)
                ->handle(
                    new CancelReservationCommand(
                        id: $reservation->id,
                    )
                );

            $this->fail(
                'Se esperaba InvalidReservationStatusTransitionException.'
            );
        } catch (InvalidReservationStatusTransitionException) {
            // Esperado.
        }

        Event::assertNotDispatched(
            ReservationCancelled::class
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::COMPLETED->value,
        ]);
    }

    public function test_listener_envia_mail_a_guest_y_email_log_queda_sent(): void
    {
        Notification::fake();

        $reservation = Reservation::factory()
            ->guest()
            ->cancelled()
            ->withTotalPrice('30000.00')
            ->createOne([
                'guest_email' => 'guest@test.com',
            ]);

        $listener = $this->app->make(
            SendReservationCancelledNotification::class
        );

        $listener->handle(
            new ReservationCancelled(
                reservationId: $reservation->id,
            )
        );

        Notification::assertSentOnDemand(
            ReservationCancelledNotification::class,
            function (
                ReservationCancelledNotification $notification,
                array $channels,
                object $notifiable
            ) {
                return in_array('mail', $channels, true)
                    && $notifiable->routes['mail'] === 'guest@test.com';
            }
        );

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'guest@test.com',
            'subject' => 'Reserva cancelada',
            'notification_type' => ReservationCancelledNotification::class,
            'status' => EmailLogStatus::SENT->value,
            'error_message' => null,
        ]);

        $log = EmailLog::query()
            ->where('to_email', 'guest@test.com')
            ->firstOrFail();

        $this->assertNotNull($log->sent_at);

        $this->assertSame(
            $reservation->id,
            $log->payload['reservation_id']
        );

        $this->assertSame(
            '30000.00',
            $log->payload['total_price']
        );
    }

    public function test_listener_envia_mail_a_customer_registrado(): void
    {
        Notification::fake();

        $customer = User::factory()->createOne([
            'email' => 'cliente@test.com',
        ]);

        $reservation = Reservation::factory()
            ->forCustomer($customer)
            ->cancelled()
            ->createOne();

        $this->app
            ->make(SendReservationCancelledNotification::class)
            ->handle(
                new ReservationCancelled(
                    reservationId: $reservation->id,
                )
            );

        Notification::assertSentOnDemand(
            ReservationCancelledNotification::class,
            function (
                ReservationCancelledNotification $notification,
                array $channels,
                object $notifiable
            ) {
                return in_array('mail', $channels, true)
                    && $notifiable->routes['mail'] === 'cliente@test.com';
            }
        );

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'cliente@test.com',
            'status' => EmailLogStatus::SENT->value,
        ]);
    }

    public function test_si_falla_mail_de_cancelacion_email_log_queda_failed(): void
    {
        $reservation = Reservation::factory()
            ->guest()
            ->cancelled()
            ->createOne([
                'guest_email' => 'error@test.com',
            ]);

        $mailChannel = Mockery::mock(
            MailChannel::class
        );

        $mailChannel
            ->shouldReceive('send')
            ->once()
            ->andThrow(
                new RuntimeException('Error enviando email')
            );

        $this->app->instance(
            MailChannel::class,
            $mailChannel
        );

        try {
            $this->app
                ->make(SendReservationCancelledNotification::class)
                ->handle(
                    new ReservationCancelled(
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
            'to_email' => 'error@test.com',
            'status' => EmailLogStatus::FAILED->value,
            'error_message' => 'Error enviando email',
        ]);

        $log = EmailLog::query()
            ->where('to_email', 'error@test.com')
            ->firstOrFail();

        $this->assertNull($log->sent_at);
    }
}
