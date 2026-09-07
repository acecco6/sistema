<?php

namespace App\Domain\Payments\MercadoPagoAccounts\Repositories;

use App\Domain\Payments\MercadoPagoAccounts\Entities\MercadoPagoAccount;

interface MercadoPagoAccountRepository
{
    public function findById(int $id): ?MercadoPagoAccount;

    public function findByClubId(int $clubId): ?MercadoPagoAccount;

    public function findActiveByClubId(
        int $clubId
    ): ?MercadoPagoAccount;

    public function findByMercadoPagoUserId(
        string $mercadoPagoUserId
    ): ?MercadoPagoAccount;

    public function save(
        MercadoPagoAccount $account
    ): MercadoPagoAccount;

    public function update(
        MercadoPagoAccount $account
    ): MercadoPagoAccount;
}
