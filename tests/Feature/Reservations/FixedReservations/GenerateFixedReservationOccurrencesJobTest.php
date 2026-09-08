<?php

namespace Tests\Feature\Reservations\FixedReservations;

use App\Jobs\GenerateFixedReservationOccurrencesJob;
use App\Models\FixedReservationConflict;
use App\Models\Reservation;
use DateInterval;
use DateTimeImmutable;

final class GenerateFixedReservationOccurrencesJobTest extends FixedReservationTestCase
{
    public function test_job_salta_ocurrencia_ocupada_registra_conflicto_y_genera_el_resto(): void
    {
        $scenario = $this->createCourtScenario();

        $today = new DateTimeImmutable('today');
        $firstOccurrence = $today->add(
            new DateInterval('P1D')
        );

        $fixedReservation = $this->createFixedReservation(
            club: $scenario['club'],
            createdBy: $scenario['user'],
            startsOn: $today,
        );

        $slot = $this->createFixedSlot(
            fixedReservation: $fixedReservation,
            court: $scenario['court'],
            dayOfWeek: (int) $firstOccurrence->format('N'),
            startTime: '18:00:00',
            durationMinutes: 60,
        );

        $this->createBlockingReservation(
            court: $scenario['court'],
            startsAt: $firstOccurrence->setTime(18, 0),
            endsAt: $firstOccurrence->setTime(19, 0),
        );

        GenerateFixedReservationOccurrencesJob::dispatchSync();

        $this->assertSame(
            7,
            Reservation::query()
                ->where(
                    'fixed_reservation_slot_id',
                    $slot->id
                )
                ->count()
        );

        $this->assertDatabaseMissing(
            'reservations',
            [
                'fixed_reservation_slot_id' => $slot->id,
                'recurrence_date' => $firstOccurrence->format('Y-m-d') . ' 00:00:00',
            ]
        );

        $this->assertDatabaseHas(
            'fixed_reservation_conflicts',
            [
                'fixed_reservation_id' => $fixedReservation->id,
                'fixed_reservation_slot_id' => $slot->id,
                'recurrence_date' => $firstOccurrence->format('Y-m-d') . ' 00:00:00',
                'reason' => 'court_not_available',
                'resolved' => 0,
            ]
        );
    }

    public function test_ejecutar_job_dos_veces_no_duplica_reservas_ni_conflictos(): void
    {
        $scenario = $this->createCourtScenario();

        $today = new DateTimeImmutable('today');
        $firstOccurrence = $today->add(
            new DateInterval('P1D')
        );

        $fixedReservation = $this->createFixedReservation(
            club: $scenario['club'],
            createdBy: $scenario['user'],
            startsOn: $today,
        );

        $slot = $this->createFixedSlot(
            fixedReservation: $fixedReservation,
            court: $scenario['court'],
            dayOfWeek: (int) $firstOccurrence->format('N'),
        );

        $this->createBlockingReservation(
            court: $scenario['court'],
            startsAt: $firstOccurrence->setTime(18, 0),
            endsAt: $firstOccurrence->setTime(19, 0),
        );

        GenerateFixedReservationOccurrencesJob::dispatchSync();
        GenerateFixedReservationOccurrencesJob::dispatchSync();

        $this->assertSame(
            7,
            Reservation::query()
                ->where(
                    'fixed_reservation_slot_id',
                    $slot->id
                )
                ->count()
        );

        $this->assertSame(
            1,
            FixedReservationConflict::query()
                ->where(
                    'fixed_reservation_slot_id',
                    $slot->id
                )
                ->whereDate(
                    'recurrence_date',
                    $firstOccurrence->format('Y-m-d')
                )
                ->count()
        );
    }
}
