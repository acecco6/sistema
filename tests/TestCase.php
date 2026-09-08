<?php

namespace Tests;

use App\Models\MercadoPagoAccount;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function connectMercadoPagoToClub(
        int $clubId
    ): MercadoPagoAccount {
        return MercadoPagoAccount::create([
            'club_id' => $clubId,
            'mercado_pago_user_id' => 'TEST-SELLER-' . $clubId,
            'access_token' => 'TEST-ACCESS-TOKEN',
            'refresh_token' => 'TEST-REFRESH-TOKEN',
            'expires_at' => now()->addMonths(6),
            'public_key' => 'TEST-PUBLIC-KEY',
            'active' => true,
            'connected_at' => now(),
        ]);
    }
}
