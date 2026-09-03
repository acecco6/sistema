<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Notifications\Entities\EmailLog;
use App\Domain\Notifications\Repositories\EmailLogRepository;
use App\Models\EmailLog as EmailLogModel;
use DateTimeImmutable;

final class EloquentEmailLogRepository implements EmailLogRepository
{
    public function findById(int $id): ?EmailLog
    {
        $model = EmailLogModel::query()->find($id);

        return $model
            ? $this->toDomain($model)
            : null;
    }

    public function save(EmailLog $emailLog): EmailLog
    {
        $model = EmailLogModel::query()->create([
            'to_email' => $emailLog->getToEmail(),
            'subject' => $emailLog->getSubject(),
            'notification_type' => $emailLog->getNotificationType(),
            'template' => $emailLog->getTemplate(),
            'payload' => $emailLog->getPayload(),
            'status' => $emailLog->getStatus()->value,
            'error_message' => $emailLog->getErrorMessage(),
            'sent_at' => $emailLog->getSentAt(),
        ]);

        return $this->toDomain($model);
    }

    public function update(EmailLog $emailLog): EmailLog
    {
        $model = EmailLogModel::query()->findOrFail(
            $emailLog->getId()
        );

        $model->update([
            'to_email' => $emailLog->getToEmail(),
            'subject' => $emailLog->getSubject(),
            'notification_type' => $emailLog->getNotificationType(),
            'template' => $emailLog->getTemplate(),
            'payload' => $emailLog->getPayload(),
            'status' => $emailLog->getStatus()->value,
            'error_message' => $emailLog->getErrorMessage(),
            'sent_at' => $emailLog->getSentAt(),
        ]);

        return $this->toDomain(
            $model->fresh()
        );
    }

    private function toDomain(
        EmailLogModel $model
    ): EmailLog {
        return new EmailLog(
            id: $model->id,
            toEmail: $model->to_email,
            subject: $model->subject,
            notificationType: $model->notification_type,
            template: $model->template,
            payload: $model->payload,
            status: $model->status,
            errorMessage: $model->error_message,
            sentAt: $model->sent_at
                ? new DateTimeImmutable(
                    $model->sent_at->toDateTimeString()
                )
                : null,
        );
    }
}
