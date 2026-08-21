<?php

namespace App\Application\Clubs\Update;

use App\Domain\Clubs\Repositories\ClubRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UpdateHandler
{
    public function __construct(
        private ClubRepository $clubs,
    ) {}

    public function handle(UpdateCommand $command): array
    {
        $club = $this->clubs->findById($command->id);

        if (!$club) {
            throw new NotFoundHttpException('Club no encontrado');
        }

        $club->changeName($command->name);
        $club->changeActive($command->active);

        $club = $this->clubs->update($club);

        return [
            'id' => $club->getId(),
            'name' => $club->getName(),
            'active' => $club->isActive(),
        ];
    }
}
