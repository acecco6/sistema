<?php

namespace App\Application\Clubs\Show;

use App\Domain\Clubs\Repositories\ClubRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ShowHandler
{
    public function __construct(
        private ClubRepository $clubs,
    ) {}

    public function handle(ShowCommand $command): array
    {
        $club = $this->clubs->findById($command->id);

        if (!$club) {
            throw new NotFoundHttpException('Club no encontrado');
        }

        return [
            'id' => $club->getId(),
            'name' => $club->getName(),
            'active' => $club->isActive(),
        ];
    }
}
