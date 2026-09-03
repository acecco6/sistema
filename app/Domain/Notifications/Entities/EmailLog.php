<?php

namespace App\Domain\Notifications\Entities;

use App\Domain\Notifications\Enums\EmailLogStatus;
use DateTimeImmutable;

final class EmailLog
{
    public function __construct(
        private ?int $id,
        private string $toEmail,
        private string $subject,
        private ?string $notificationType,
        private ?string $template,
        private ?array $payload,
        private EmailLogStatus $status,
        private ?string $errorMessage,
        private ?DateTimeImmutable $sentAt,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToEmail(): string
    {
        return $this->toEmail;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getNotificationType(): ?string
    {
        return $this->notificationType;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function getStatus(): EmailLogStatus
    {
        return $this->status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getSentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function markSent(?DateTimeImmutable $sentAt = null): void
    {
        $this->status = EmailLogStatus::SENT;
        $this->sentAt = $sentAt ?? new DateTimeImmutable();
        $this->errorMessage = null;
    }

    public function markFailed(string $errorMessage): void
    {
        $this->status = EmailLogStatus::FAILED;
        $this->errorMessage = $errorMessage;
    }
}
