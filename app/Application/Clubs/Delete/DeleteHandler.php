<?php

namespace App\Application\Clubs\Delete;

use App\Domain\Clubs\Repositories\ClubRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class DeleteHandler
{
    public function __construct(
        private ClubRepository $clubs,
    ) {}

    public function handle(DeleteCommand $command): array
    {
        $club = $this->clubs->findById($command->id);

        if (!$club) {
            throw new NotFoundHttpException('Club no encontrado');
        }

        $club->deactivate();

        $club = $this->clubs->update($club);

        return [
            'id' => $club->getId(),
            'name' => $club->getName(),
            'active' => $club->isActive(),
        ];
    }
}
