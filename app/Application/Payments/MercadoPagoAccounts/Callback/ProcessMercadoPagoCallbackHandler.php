<?php

namespace App\Application\Payments\MercadoPagoAccounts\Callback;

use App\Domain\Payments\MercadoPagoAccounts\Entities\MercadoPagoAccount;
use App\Domain\Payments\MercadoPagoAccounts\Exceptions\InvalidMercadoPagoOAuthStateException;
use App\Domain\Payments\MercadoPagoAccounts\Repositories\MercadoPagoAccountRepository;
use App\Domain\Payments\MercadoPagoAccounts\Services\MercadoPagoOAuthClient;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

final class ProcessMercadoPagoCallbackHandler
{
    public function __construct(
        private MercadoPagoOAuthClient $oauth,
        private MercadoPagoAccountRepository $accounts,
    ) {}

    public function handle(
        ProcessMercadoPagoCallbackCommand $command
    ): ProcessMercadoPagoCallbackDto {

        /*
        |--------------------------------------------------------------------------
        | 1. Consumir state
        |--------------------------------------------------------------------------
        |
        | pull() obtiene el valor Y lo elimina.
        |
        | Esto evita que el callback pueda reutilizarse.
        |
        */

        $stateData = Cache::pull(
            $this->stateCacheKey($command->state)
        );

        if ($stateData === null) {
            throw new InvalidMercadoPagoOAuthStateException();
        }

        $clubId = (int) $stateData['club_id'];

        /*
        |--------------------------------------------------------------------------
        | 2. Intercambiar authorization code
        |--------------------------------------------------------------------------
        */

        $credentials = $this->oauth
            ->exchangeAuthorizationCode(
                code: $command->code,
                state: $command->state,
            );

        /*
        |--------------------------------------------------------------------------
        | 3. Calcular vencimiento
        |--------------------------------------------------------------------------
        */

        $expiresAt = (new DateTimeImmutable())
            ->modify("+{$credentials->expiresIn} seconds");

        /*
        |--------------------------------------------------------------------------
        | 4. ¿El club ya tenía Mercado Pago conectado?
        |--------------------------------------------------------------------------
        */

        $account = $this->accounts->findByClubId($clubId);

        if ($account !== null) {

            /*
            |--------------------------------------------------------------------------
            | Reconexión
            |--------------------------------------------------------------------------
            */

            $account->updateCredentials(
                mercadoPagoUserId: $credentials->mercadoPagoUserId,
                accessToken: $credentials->accessToken,
                refreshToken: $credentials->refreshToken,
                expiresAt: $expiresAt,
                publicKey: $credentials->publicKey,
            );

            $account->activate();

            $saved = $this->accounts->update($account);

            return new ProcessMercadoPagoCallbackDto(
                clubId: $clubId,
                mercadoPagoUserId: $saved->getMercadoPagoUserId(),
                connected: true,
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Primera conexión
        |--------------------------------------------------------------------------
        */

        $account = new MercadoPagoAccount(
            id: null,
            clubId: $clubId,
            mercadoPagoUserId: $credentials->mercadoPagoUserId,
            accessToken: $credentials->accessToken,
            refreshToken: $credentials->refreshToken,
            expiresAt: $expiresAt,
            publicKey: $credentials->publicKey,
            active: true,
            connectedAt: new DateTimeImmutable(),
        );

        $saved = $this->accounts->save($account);

        return new ProcessMercadoPagoCallbackDto(
            clubId: $clubId,
            mercadoPagoUserId: $saved->getMercadoPagoUserId(),
            connected: true,
        );
    }

    private function stateCacheKey(string $state): string
    {
        return 'mercadopago:oauth:state:' . $state;
    }
}
