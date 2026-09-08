<?php

namespace Tests\Feature\Reservations\FixedReservations;

use App\Application\Reservations\FixedReservations\Services\FixedReservationConflictService;
use App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository;
use App\Models\FixedReservationConflict;
use DateTimeImmutable;

final class FixedReservationConflictServiceTest extends FixedReservationTestCase
{
    public function test_registrar_dos_veces_misma_ocurrencia_no_duplica_conflicto(): void
    {
        $scenario = $this->createCourtScenario();

        $fixedReservationModel = $this->createFixedReservation(
            club: $scenario['club'],
            createdBy: $scenario['user'],
            startsOn: new DateTimeImmutable('2030-09-16'),
        );

        $slotModel = $this->createFixedSlot(
            fixedReservation: $fixedReservationModel,
            court: $scenario['court'],
            dayOfWeek: 1,
        );

        $fixedRepository = $this->app->make(
            FixedReservationRepository::class
        );

        $fixedReservation = $fixedRepository
            ->findById($fixedReservationModel->id);

        $slot = $fixedRepository
            ->findSlotById($slotModel->id);

        $service = $this->app->make(
            FixedReservationConflictService::class
        );

        $date = new DateTimeImmutable('2030-09-16');

        $service->register(
            fixedReservation: $fixedReservation,
            slot: $slot,
            recurrenceDate: $date,
            startsAt: new DateTimeImmutable('2030-09-16 18:00:00'),
            endsAt: new DateTimeImmutable('2030-09-16 19:00:00'),
            reason: 'court_not_available',
            message: 'Primer conflicto',
        );

        $service->register(
            fixedReservation: $fixedReservation,
            slot: $slot,
            recurrenceDate: $date,
            startsAt: new DateTimeImmutable('2030-09-16 18:00:00'),
            endsAt: new DateTimeImmutable('2030-09-16 19:00:00'),
            reason: 'court_not_available',
            message: 'Sigue ocupado',
        );

        $this->assertSame(
            1,
            FixedReservationConflict::query()
                ->where(
                    'fixed_reservation_slot_id',
                    $slotModel->id
                )
                ->whereDate(
                    'recurrence_date',
                    '2030-09-16'
                )
                ->count()
        );

        $this->assertDatabaseHas(
            'fixed_reservation_conflicts',
            [
                'fixed_reservation_slot_id' => $slotModel->id,
                'recurrence_date' => '2030-09-16 00:00:00',
                'resolved' => 0,
                'message' => 'Sigue ocupado',
            ]
        );
    }

    public function test_conflicto_resuelto_se_reabre_si_vuelve_a_detectarse(): void
    {
        $scenario = $this->createCourtScenario();

        $fixedReservationModel = $this->createFixedReservation(
            club: $scenario['club'],
            createdBy: $scenario['user'],
            startsOn: new DateTimeImmutable('2030-09-16'),
        );

        $slotModel = $this->createFixedSlot(
            fixedReservation: $fixedReservationModel,
            court: $scenario['court'],
            dayOfWeek: 1,
        );

        $fixedRepository = $this->app->make(
            FixedReservationRepository::class
        );

        $fixedReservation = $fixedRepository
            ->findById($fixedReservationModel->id);

        $slot = $fixedRepository
            ->findSlotById($slotModel->id);

        $service = $this->app->make(
            FixedReservationConflictService::class
        );

        $date = new DateTimeImmutable('2030-09-16');

        $service->register(
            fixedReservation: $fixedReservation,
            slot: $slot,
            recurrenceDate: $date,
            startsAt: new DateTimeImmutable('2030-09-16 18:00:00'),
            endsAt: new DateTimeImmutable('2030-09-16 19:00:00'),
            reason: 'court_not_available',
        );

        $service->resolveOccurrence(
            fixedReservationSlotId: $slotModel->id,
            recurrenceDate: $date,
        );

        $conflict = FixedReservationConflict::query()
            ->firstOrFail();

        $this->assertTrue(
            (bool) $conflict->resolved
        );

        $service->register(
            fixedReservation: $fixedReservation,
            slot: $slot,
            recurrenceDate: $date,
            startsAt: new DateTimeImmutable('2030-09-16 18:00:00'),
            endsAt: new DateTimeImmutable('2030-09-16 19:00:00'),
            reason: 'court_not_available',
            message: 'Conflicto nuevamente detectado',
        );

        $conflict->refresh();

        $this->assertFalse(
            (bool) $conflict->resolved
        );

        $this->assertNull(
            $conflict->resolved_at
        );

        $this->assertNull(
            $conflict->resolved_by_user_id
        );

        $this->assertSame(
            'Conflicto nuevamente detectado',
            $conflict->message
        );
    }
}
