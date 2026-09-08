<?php

namespace App\Application\Payments\MercadoPagoAccounts\Connect;

use App\Domain\Clubs\Exceptions\ClubNotFoundException;
use App\Domain\Clubs\Repositories\ClubRepository;
use App\Domain\Payments\MercadoPagoAccounts\Services\MercadoPagoOAuthClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class ConnectMercadoPagoHandler
{
    private const STATE_TTL_MINUTES = 10;

    public function __construct(
        private ClubRepository $clubs,
        private MercadoPagoOAuthClient $oauth,
    ) {}

    public function handle(
        ConnectMercadoPagoCommand $command
    ): ConnectMercadoPagoDto {
        $club = $this->clubs->findById($command->clubId);

        if ($club === null) {
            throw new ClubNotFoundException();
        }

        $state = Str::random(64);

        Cache::put(
            $this->stateCacheKey($state),
            [
                'club_id' => $command->clubId,
                'user_id' => $command->userId,
                'created_at' => now()->toISOString(),
            ],
            now()->addMinutes(self::STATE_TTL_MINUTES),
        );

        return new ConnectMercadoPagoDto(
            authorizationUrl: $this->oauth->getAuthorizationUrl(
                $state
            ),
        );
    }

    private function stateCacheKey(string $state): string
    {
        return 'mercadopago:oauth:state:' . $state;
    }
}
