<?php

namespace App\Application\Backoffice;

use App\Application\Backoffice\Contracts\BackofficeQueryRepository;

final readonly class GetSessionContextHandler
{
    public function __construct(private BackofficeQueryRepository $queries) {}

    public function handle(int $userId): array
    {
        return $this->queries->sessionContext($userId);
    }
}
