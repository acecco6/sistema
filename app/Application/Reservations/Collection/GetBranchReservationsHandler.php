<?php

namespace App\Application\Reservations\Collection;

use App\Application\Reservations\DTOs\BranchReservationsDto;
use App\Application\Reservations\DTOs\CourtReservationsDto;
use App\Application\Reservations\DTOs\ReservationDto;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Domain\Reservations\Repositories\ReservationRepository;

final class GetBranchReservationsHandler
{
    public function __construct(
        private BranchRepository $branches,
        private CourtRepository $courts,
        private ReservationRepository $reservations,
    ) {}

    public function handle(GetBranchReservationsQuery $query): BranchReservationsDto
    {
        $branch = $this->branches->findById($query->branchId);

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        $reservations = $this->reservations->findByBranchAndDate($query->branchId, $query->date);

        $courts = $this->courts->findByBranchId($query->branchId);

        $courtsData = [];

        foreach ($courts as $court) {

            $reservationsByCourt = array_filter($reservations, function ($reservation) use ($court) {
                return $reservation->getCourtId() == $court->getId();
            });

            $reservationsData = [];
            foreach ($reservationsByCourt as $reservation) {
                $reservationsData[] = ReservationDto::fromDomain($reservation)->toArray();
            }

            $courtsData[] = new CourtReservationsDto(
                id: $court->getId(),
                name: $court->getName(),
                reservations: $reservationsData
            );
        }

        return new BranchReservationsDto(
            id: $branch->getId(),
            name: $branch->getName(),
            address: $branch->getAddress(),
            courts: $courtsData,
        );
    }
}
