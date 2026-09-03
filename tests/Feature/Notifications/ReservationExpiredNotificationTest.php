<?php

namespace Tests\Feature\Notifications;

use App\Application\Notifications\Listeners\SendReservationExpiredNotification;
use App\Application\Notifications\Mail\ReservationExpiredNotification;
use App\Domain\Notifications\Enums\EmailLogStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Events\ReservationExpired;
use App\Domain\Reservations\Repositories\ReservationRepository;
use App\Jobs\ExpirePendingReservationsJob;
use App\Models\EmailLog;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class ReservationExpiredNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_job_expira_pending_vencida_y_dispara_evento(): void
    {
        Carbon::setTestNow(
            '2030-09-10 12:30:00'
        );

        Event::fake([
            ReservationExpired::class,
        ]);

        $reservation = Reservation::factory()
            ->pending()
            ->createOne([
                'expires_at' => '2030-09-10 12:15:00',
            ]);

        $this->runJob();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::EXPIRED->value,
        ]);

        Event::assertDispatched(
            ReservationExpired::class,
            fn(ReservationExpired $event) =>
            $event->reservationId === $reservation->id
        );

        Event::assertDispatchedTimes(
            ReservationExpired::class,
            1
        );
    }

    public function test_job_no_expira_pending_vigente_y_no_dispara_evento(): void
    {
        Carbon::setTestNow(
            '2030-09-10 12:00:00'
        );

        Event::fake([
            ReservationExpired::class,
        ]);

        $reservation = Reservation::factory()
            ->pending()
            ->createOne([
                'expires_at' => '2030-09-10 12:15:00',
            ]);

        $this->runJob();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::PENDING->value,
        ]);

        Event::assertNotDispatched(
            ReservationExpired::class
        );
    }

    public function test_job_no_expira_reserva_confirmada(): void
    {
        Carbon::setTestNow(
            '2030-09-10 12:30:00'
        );

        Event::fake([
            ReservationExpired::class,
        ]);

        $reservation = Reservation::factory()
            ->confirmed()
            ->createOne([
                'expires_at' => '2030-09-10 12:15:00',
            ]);

        $this->runJob();

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => ReservationStatus::CONFIRMED->value,
        ]);

        Event::assertNotDispatched(
            ReservationExpired::class
        );
    }

    public function test_job_expira_varias_reservas_y_dispara_un_evento_por_cada_una(): void
    {
        Carbon::setTestNow(
            '2030-09-10 12:30:00'
        );

        Event::fake([
            ReservationExpired::class,
        ]);

        $first = Reservation::factory()
            ->pending()
            ->createOne([
                'expires_at' => '2030-09-10 12:10:00',
            ]);

        $second = Reservation::factory()
            ->pending()
            ->createOne([
                'expires_at' => '2030-09-10 12:20:00',
            ]);

        $this->runJob();

        Event::assertDispatchedTimes(
            ReservationExpired::class,
            2
        );

        Event::assertDispatched(
            ReservationExpired::class,
            fn(ReservationExpired $event) =>
            $event->reservationId === $first->id
        );

        Event::assertDispatched(
            ReservationExpired::class,
            fn(ReservationExpired $event) =>
            $event->reservationId === $second->id
        );
    }

    public function test_listener_envia_mail_a_guest_y_email_log_queda_sent(): void
    {
        Notification::fake();

        $reservation = Reservation::factory()
            ->guest()
            ->createOne([
                'guest_email' => 'guest-expired@test.com',
                'status' => ReservationStatus::EXPIRED->value,
                'total_price' => '30000.00',
            ]);

        $this->app
            ->make(SendReservationExpiredNotification::class)
            ->handle(
                new ReservationExpired(
                    reservationId: $reservation->id,
                )
            );

        Notification::assertSentOnDemand(
            ReservationExpiredNotification::class,
            function (
                ReservationExpiredNotification $notification,
                array $channels,
                object $notifiable
            ) {
                return in_array('mail', $channels, true)
                    && $notifiable->routes['mail']
                    === 'guest-expired@test.com';
            }
        );

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'guest-expired@test.com',
            'subject' => 'Reserva expirada',
            'notification_type' => ReservationExpiredNotification::class,
            'status' => EmailLogStatus::SENT->value,
            'error_message' => null,
        ]);

        $log = EmailLog::query()
            ->where(
                'to_email',
                'guest-expired@test.com'
            )
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
            'email' => 'cliente-expired@test.com',
        ]);

        $reservation = Reservation::factory()
            ->forCustomer($customer)
            ->createOne([
                'status' => ReservationStatus::EXPIRED->value,
            ]);

        $this->app
            ->make(SendReservationExpiredNotification::class)
            ->handle(
                new ReservationExpired(
                    reservationId: $reservation->id,
                )
            );

        Notification::assertSentOnDemand(
            ReservationExpiredNotification::class,
            function (
                ReservationExpiredNotification $notification,
                array $channels,
                object $notifiable
            ) {
                return in_array('mail', $channels, true)
                    && $notifiable->routes['mail']
                    === 'cliente-expired@test.com';
            }
        );

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'cliente-expired@test.com',
            'status' => EmailLogStatus::SENT->value,
        ]);
    }

    public function test_si_falla_mail_de_expiracion_email_log_queda_failed(): void
    {
        $reservation = Reservation::factory()
            ->guest()
            ->createOne([
                'guest_email' => 'error-expired@test.com',
                'status' => ReservationStatus::EXPIRED->value,
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
                ->make(SendReservationExpiredNotification::class)
                ->handle(
                    new ReservationExpired(
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
            'to_email' => 'error-expired@test.com',
            'status' => EmailLogStatus::FAILED->value,
            'error_message' => 'Error enviando email',
        ]);

        $log = EmailLog::query()
            ->where(
                'to_email',
                'error-expired@test.com'
            )
            ->firstOrFail();

        $this->assertNull($log->sent_at);
    }

    private function runJob(): void
    {
        $this->app
            ->make(ExpirePendingReservationsJob::class)
            ->handle(
                $this->app->make(
                    ReservationRepository::class
                )
            );
    }
}
