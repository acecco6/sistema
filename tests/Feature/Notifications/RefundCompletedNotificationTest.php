<?php

namespace Tests\Feature\Notifications;

use App\Application\Notifications\Listeners\SendRefundCompletedNotification;
use App\Application\Notifications\Mail\RefundCompletedNotification;
use App\Application\Payments\Refunds\CompleteRefund\CompleteRefundCommand;
use App\Application\Payments\Refunds\CompleteRefund\CompleteRefundHandler;
use App\Domain\Notifications\Enums\EmailLogStatus;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Events\RefundCompleted;
use App\Domain\Payments\Exceptions\InvalidRefundStatusTransitionException;
use App\Models\EmailLog;
use App\Models\PaymentRefund;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class RefundCompletedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_completar_refund_dispara_refund_completed_una_vez(): void
    {
        Event::fake([
            RefundCompleted::class,
        ]);

        $admin = User::factory()->createOne();

        $refund = PaymentRefund::factory()
            ->pending()
            ->createOne();

        $result = $this->app
            ->make(CompleteRefundHandler::class)
            ->handle(
                new CompleteRefundCommand(
                    refundId: $refund->id,
                    method: PaymentMethod::TRANSFER,
                    completedByUserId: $admin->id,
                    notes: 'Transferencia realizada',
                )
            );

        $this->assertSame(
            RefundStatus::COMPLETED->value,
            $result->status
        );

        Event::assertDispatched(
            RefundCompleted::class,
            fn(RefundCompleted $event) =>
            $event->refundId === $refund->id
        );

        Event::assertDispatchedTimes(
            RefundCompleted::class,
            1
        );
    }

    public function test_refund_ya_completado_no_dispara_evento(): void
    {
        Event::fake([
            RefundCompleted::class,
        ]);

        $admin = User::factory()->createOne();

        $refund = PaymentRefund::factory()
            ->completed()
            ->createOne();

        try {
            $this->app
                ->make(CompleteRefundHandler::class)
                ->handle(
                    new CompleteRefundCommand(
                        refundId: $refund->id,
                        method: PaymentMethod::TRANSFER,
                        completedByUserId: $admin->id,
                    )
                );

            $this->fail(
                'Se esperaba InvalidRefundStatusTransitionException.'
            );
        } catch (InvalidRefundStatusTransitionException) {
            // Esperado.
        }

        Event::assertNotDispatched(
            RefundCompleted::class
        );
    }

    public function test_refund_cancelado_no_dispara_evento(): void
    {
        Event::fake([
            RefundCompleted::class,
        ]);

        $admin = User::factory()->createOne();

        $refund = PaymentRefund::factory()
            ->cancelled()
            ->createOne();

        try {
            $this->app
                ->make(CompleteRefundHandler::class)
                ->handle(
                    new CompleteRefundCommand(
                        refundId: $refund->id,
                        method: PaymentMethod::CASH,
                        completedByUserId: $admin->id,
                    )
                );

            $this->fail(
                'Se esperaba InvalidRefundStatusTransitionException.'
            );
        } catch (InvalidRefundStatusTransitionException) {
            // Esperado.
        }

        Event::assertNotDispatched(
            RefundCompleted::class
        );
    }

    public function test_listener_refund_completed_envia_mail_a_guest_y_marca_sent(): void
    {
        Notification::fake();

        $reservation = Reservation::factory()
            ->guest()
            ->cancelled()
            ->createOne([
                'guest_email' => 'refund-guest@test.com',
            ]);

        $refund = PaymentRefund::factory()
            ->forReservation($reservation)
            ->completed(PaymentMethod::TRANSFER)
            ->withAmount('15000.00')
            ->createOne();

        $this->app
            ->make(SendRefundCompletedNotification::class)
            ->handle(
                new RefundCompleted(
                    refundId: $refund->id,
                )
            );

        Notification::assertSentOnDemand(
            RefundCompletedNotification::class,
            function (
                RefundCompletedNotification $notification,
                array $channels,
                object $notifiable
            ) {
                return in_array('mail', $channels, true)
                    && $notifiable->routes['mail']
                    === 'refund-guest@test.com';
            }
        );

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'refund-guest@test.com',
            'subject' => 'Devolución realizada',
            'notification_type' => RefundCompletedNotification::class,
            'status' => EmailLogStatus::SENT->value,
            'error_message' => null,
        ]);

        $log = EmailLog::query()
            ->where(
                'to_email',
                'refund-guest@test.com'
            )
            ->firstOrFail();

        $this->assertNotNull($log->sent_at);

        $this->assertSame(
            $refund->id,
            $log->payload['refund_id']
        );

        $this->assertSame(
            $reservation->id,
            $log->payload['reservation_id']
        );

        $this->assertSame(
            '15000.00',
            $log->payload['amount']
        );

        $this->assertSame(
            PaymentMethod::TRANSFER->value,
            $log->payload['method']
        );
    }

    public function test_listener_refund_completed_envia_mail_a_customer_registrado(): void
    {
        Notification::fake();

        $customer = User::factory()->createOne([
            'email' => 'refund-customer@test.com',
        ]);

        $reservation = Reservation::factory()
            ->forCustomer($customer)
            ->cancelled()
            ->createOne();

        $refund = PaymentRefund::factory()
            ->forReservation($reservation)
            ->completed(PaymentMethod::CASH)
            ->withAmount('10000.00')
            ->createOne();

        $this->app
            ->make(SendRefundCompletedNotification::class)
            ->handle(
                new RefundCompleted(
                    refundId: $refund->id,
                )
            );

        Notification::assertSentOnDemand(
            RefundCompletedNotification::class,
            function (
                RefundCompletedNotification $notification,
                array $channels,
                object $notifiable
            ) {
                return in_array('mail', $channels, true)
                    && $notifiable->routes['mail']
                    === 'refund-customer@test.com';
            }
        );

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'refund-customer@test.com',
            'status' => EmailLogStatus::SENT->value,
        ]);
    }

    public function test_si_falla_mail_de_refund_email_log_queda_failed(): void
    {
        $reservation = Reservation::factory()
            ->guest()
            ->cancelled()
            ->createOne([
                'guest_email' => 'refund-error@test.com',
            ]);

        $refund = PaymentRefund::factory()
            ->forReservation($reservation)
            ->completed(PaymentMethod::TRANSFER)
            ->withAmount('15000.00')
            ->createOne();

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
                ->make(SendRefundCompletedNotification::class)
                ->handle(
                    new RefundCompleted(
                        refundId: $refund->id,
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
            'to_email' => 'refund-error@test.com',
            'status' => EmailLogStatus::FAILED->value,
            'error_message' => 'Error enviando email',
        ]);

        $log = EmailLog::query()
            ->where(
                'to_email',
                'refund-error@test.com'
            )
            ->firstOrFail();

        $this->assertNull($log->sent_at);
    }
}
