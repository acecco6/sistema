<?php

namespace App\Application\Reservations\DTOs;

final readonly class TipoCourtAvailabilityDto
{
    /**
     * @param CourtAvailabilitySummaryDto[] $courts
     */
    public function __construct(
        public int $branchId,
        public int $tipoCourtId,
        public string $date,
        public int $intervalMinutes,
        public array $courts,
    ) {}

    public function toArray(): array
    {
        return [
            'branch_id' => $this->branchId,
            'tipo_court_id' => $this->tipoCourtId,
            'date' => $this->date,
            'interval_minutes' => $this->intervalMinutes,

            'courts' => array_map(
                fn(CourtAvailabilitySummaryDto $court) =>
                $court->toArray(),
                $this->courts
            ),
        ];
    }
}
