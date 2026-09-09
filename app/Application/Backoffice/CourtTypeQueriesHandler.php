<?php

namespace App\Application\Backoffice;

use App\Application\Backoffice\Contracts\BackofficeQueryRepository;

final readonly class CourtTypeQueriesHandler
{
    public function __construct(private BackofficeQueryRepository $queries) {}

    public function all(): array
    {
        return $this->queries->courtTypes();
    }
}
