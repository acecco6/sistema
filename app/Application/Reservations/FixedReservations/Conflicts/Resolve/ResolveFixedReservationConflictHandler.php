<?php

namespace App\Application\Reservations\FixedReservations\Conflicts\Resolve;

use App\Application\Reservations\FixedReservations\DTOs\FixedReservationConflictDto;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationConflictRepository;
use RuntimeException;

final class ResolveFixedReservationConflictHandler
{
    public function __construct(
        private FixedReservationConflictRepository $conflicts,
    ) {}

    public function handle(
        ResolveFixedReservationConflictCommand $command
    ): FixedReservationConflictDto {
        $conflict = $this->conflicts->findById(
            $command->id
        );

        if ($conflict === null) {
            throw new RuntimeException(
                'No se encontró el conflicto de reserva fija.'
            );
        }

        $conflict->resolve(
            userId: $command->userId,
        );

        $updated = $this->conflicts->update(
            $conflict
        );

        return FixedReservationConflictDto::fromDomain(
            $updated
        );
    }
}
