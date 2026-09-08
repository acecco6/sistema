<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Payments\MercadoPagoAccounts\Entities\MercadoPagoAccount;
use App\Domain\Payments\MercadoPagoAccounts\Repositories\MercadoPagoAccountRepository;
use App\Models\MercadoPagoAccount as MercadoPagoAccountModel;
use DateTimeImmutable;

final class EloquentMercadoPagoAccountRepository implements MercadoPagoAccountRepository
{
    public function findById(int $id): ?MercadoPagoAccount
    {
        $model = MercadoPagoAccountModel::query()
            ->find($id);

        return $model
            ? $this->toDomain($model)
            : null;
    }

    public function findByClubId(
        int $clubId
    ): ?MercadoPagoAccount {
        $model = MercadoPagoAccountModel::query()
            ->where('club_id', $clubId)
            ->first();

        return $model
            ? $this->toDomain($model)
            : null;
    }

    public function findActiveByClubId(
        int $clubId
    ): ?MercadoPagoAccount {
        $model = MercadoPagoAccountModel::query()
            ->where('club_id', $clubId)
            ->where('active', true)
            ->first();

        return $model
            ? $this->toDomain($model)
            : null;
    }

    public function findByMercadoPagoUserId(
        string $mercadoPagoUserId
    ): ?MercadoPagoAccount {
        $model = MercadoPagoAccountModel::query()
            ->where(
                'mercado_pago_user_id',
                $mercadoPagoUserId
            )
            ->first();

        return $model
            ? $this->toDomain($model)
            : null;
    }

    public function save(
        MercadoPagoAccount $account
    ): MercadoPagoAccount {
        $model = MercadoPagoAccountModel::query()
            ->create([
                'club_id' => $account->getClubId(),
                'mercado_pago_user_id' => $account->getMercadoPagoUserId(),
                'access_token' => $account->getAccessToken(),
                'refresh_token' => $account->getRefreshToken(),
                'expires_at' => $account->getExpiresAt(),
                'public_key' => $account->getPublicKey(),
                'active' => $account->isActive(),
                'connected_at' => $account->getConnectedAt(),
            ]);

        return $this->toDomain($model);
    }

    public function update(
        MercadoPagoAccount $account
    ): MercadoPagoAccount {
        $model = MercadoPagoAccountModel::query()
            ->findOrFail($account->getId());

        $model->update([
            'club_id' => $account->getClubId(),
            'mercado_pago_user_id' => $account->getMercadoPagoUserId(),
            'access_token' => $account->getAccessToken(),
            'refresh_token' => $account->getRefreshToken(),
            'expires_at' => $account->getExpiresAt(),
            'public_key' => $account->getPublicKey(),
            'active' => $account->isActive(),
            'connected_at' => $account->getConnectedAt(),
        ]);

        $model->refresh();

        return $this->toDomain($model);
    }

    private function toDomain(
        MercadoPagoAccountModel $model
    ): MercadoPagoAccount {
        return new MercadoPagoAccount(
            id: $model->id,
            clubId: $model->club_id,
            mercadoPagoUserId: $model->mercado_pago_user_id,
            accessToken: $model->access_token,
            refreshToken: $model->refresh_token,

            expiresAt: $model->expires_at
                ? new DateTimeImmutable(
                    $model->expires_at->format('Y-m-d H:i:s')
                )
                : null,

            publicKey: $model->public_key,
            active: $model->active,

            connectedAt: new DateTimeImmutable(
                $model->connected_at->format('Y-m-d H:i:s')
            ),
        );
    }
}
