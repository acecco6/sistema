<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\Entities\EmailLog;
use App\Domain\Notifications\Enums\EmailLogStatus;
use App\Domain\Notifications\Repositories\EmailLogRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EmailLogRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guarda_y_recupera_un_email_log_pending(): void
    {
        $repository = $this->repository();

        $emailLog = new EmailLog(
            id: null,
            toEmail: 'cliente@test.com',
            subject: 'Reserva confirmada',
            notificationType: 'reservation_confirmed',
            template: 'emails.reservations.confirmed',
            payload: [
                'reservation_id' => 10,
            ],
            status: EmailLogStatus::PENDING,
            errorMessage: null,
            sentAt: null,
        );

        $saved = $repository->save($emailLog);

        $this->assertNotNull($saved->getId());
        $this->assertSame(
            EmailLogStatus::PENDING,
            $saved->getStatus()
        );

        $found = $repository->findById(
            $saved->getId()
        );

        $this->assertNotNull($found);
        $this->assertSame(
            'cliente@test.com',
            $found->getToEmail()
        );
        $this->assertSame(
            'Reserva confirmada',
            $found->getSubject()
        );
        $this->assertSame(
            'reservation_confirmed',
            $found->getNotificationType()
        );
        $this->assertSame(
            [
                'reservation_id' => 10,
            ],
            $found->getPayload()
        );
    }

    public function test_puede_marcar_un_email_log_como_sent(): void
    {
        $repository = $this->repository();

        $saved = $repository->save(
            new EmailLog(
                id: null,
                toEmail: 'cliente@test.com',
                subject: 'Reserva confirmada',
                notificationType: 'reservation_confirmed',
                template: 'emails.reservations.confirmed',
                payload: [
                    'reservation_id' => 10,
                ],
                status: EmailLogStatus::PENDING,
                errorMessage: null,
                sentAt: null,
            )
        );

        $saved->markSent();

        $updated = $repository->update($saved);

        $this->assertSame(
            EmailLogStatus::SENT,
            $updated->getStatus()
        );

        $this->assertNotNull(
            $updated->getSentAt()
        );

        $this->assertNull(
            $updated->getErrorMessage()
        );

        $this->assertDatabaseHas('email_logs', [
            'id' => $saved->getId(),
            'status' => EmailLogStatus::SENT->value,
            'error_message' => null,
        ]);
    }

    public function test_puede_marcar_un_email_log_como_failed(): void
    {
        $repository = $this->repository();

        $saved = $repository->save(
            new EmailLog(
                id: null,
                toEmail: 'cliente@test.com',
                subject: 'Reserva confirmada',
                notificationType: 'reservation_confirmed',
                template: 'emails.reservations.confirmed',
                payload: [
                    'reservation_id' => 10,
                ],
                status: EmailLogStatus::PENDING,
                errorMessage: null,
                sentAt: null,
            )
        );

        $saved->markFailed(
            'SMTP connection failed'
        );

        $updated = $repository->update($saved);

        $this->assertSame(
            EmailLogStatus::FAILED,
            $updated->getStatus()
        );

        $this->assertSame(
            'SMTP connection failed',
            $updated->getErrorMessage()
        );

        $this->assertDatabaseHas('email_logs', [
            'id' => $saved->getId(),
            'status' => EmailLogStatus::FAILED->value,
            'error_message' => 'SMTP connection failed',
        ]);
    }

    public function test_find_by_id_devuelve_null_si_no_existe(): void
    {
        $found = $this->repository()
            ->findById(999999);

        $this->assertNull($found);
    }

    private function repository(): EmailLogRepository
    {
        return $this->app->make(
            EmailLogRepository::class
        );
    }
}
