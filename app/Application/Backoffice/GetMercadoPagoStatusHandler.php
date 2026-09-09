<?php

namespace App\Application\Backoffice;

use App\Domain\Clubs\Exceptions\ClubNotFoundException;
use App\Domain\Clubs\Repositories\ClubRepository;
use App\Domain\Payments\MercadoPagoAccounts\Repositories\MercadoPagoAccountRepository;

final readonly class GetMercadoPagoStatusHandler
{
    public function __construct(
        private ClubRepository $clubs,
        private MercadoPagoAccountRepository $accounts,
    ) {}

    public function handle(int $clubId): array
    {
        if ($this->clubs->findById($clubId) === null) {
            throw new ClubNotFoundException();
        }

        $account = $this->accounts->findByClubId($clubId);

        return [
            'club_id' => $clubId,
            'connected' => $account !== null,
            'active' => $account?->isActive() ?? false,
            'connected_at' => $account?->getConnectedAt()->format('Y-m-d H:i:s'),
            'expires_at' => $account?->getExpiresAt()?->format('Y-m-d H:i:s'),
            'mercado_pago_user_id' => $account?->getMercadoPagoUserId(),
            'public_key' => $account?->getPublicKey(),
        ];
    }
}
