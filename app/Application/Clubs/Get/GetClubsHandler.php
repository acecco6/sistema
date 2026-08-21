<?php

namespace App\Application\Clubs\Get;

use App\Domain\Clubs\Repositories\ClubRepository;

final class GetClubsHandler
{
    public function __construct(
        private ClubRepository $clubs
    ) {}

    public function handle(): array
    {
        $clubs = $this->clubs->findAll();
        
        // Convertimos las Entidades de Dominio a arrays simples para la API
        return array_map(function ($club) {
            return [
                'id' => $club->getId(),
                'name' => $club->getName(),
                'active' => $club->isActive(),
            ];
        }, $clubs);
    }
}
