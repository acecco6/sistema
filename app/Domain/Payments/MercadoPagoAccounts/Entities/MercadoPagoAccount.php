<?php

namespace App\Domain\Payments\MercadoPagoAccounts\Entities;

use DateTimeImmutable;

final class MercadoPagoAccount
{
    public function __construct(
        private ?int $id,
        private int $clubId,
        private string $mercadoPagoUserId,
        private string $accessToken,
        private string $refreshToken,
        private ?DateTimeImmutable $expiresAt,
        private ?string $publicKey,
        private bool $active,
        private DateTimeImmutable $connectedAt,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClubId(): int
    {
        return $this->clubId;
    }

    public function getMercadoPagoUserId(): string
    {
        return $this->mercadoPagoUserId;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getConnectedAt(): DateTimeImmutable
    {
        return $this->connectedAt;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function updateCredentials(
        string $mercadoPagoUserId,
        string $accessToken,
        string $refreshToken,
        ?DateTimeImmutable $expiresAt,
        ?string $publicKey,
    ): void {
        $this->mercadoPagoUserId = $mercadoPagoUserId;
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->expiresAt = $expiresAt;
        $this->publicKey = $publicKey;
    }
}
