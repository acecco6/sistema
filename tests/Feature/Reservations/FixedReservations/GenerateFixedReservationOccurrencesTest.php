<?php

namespace Tests\Feature\Reservations\FixedReservations;

use App\Application\Reservations\FixedReservations\Services\GenerateFixedReservationOccurrences;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Domain\Reservations\Exceptions\CourtNotAvailableException;
use App\Models\FixedReservationConflict;
use App\Models\Reservation;
use DateTimeImmutable;

final class GenerateFixedReservationOccurrencesTest extends FixedReservationTestCase
{
    public function test_genera_ocurrencias_de_dos_slots_semanales(): void
    {
        $scenario = $this->createCourtScenario(
            intervalMinutes: 60,
        );

        $fixedReservation = $this->createFixedReservation(
            club: $scenario['club'],
            createdBy: $scenario['user'],
            startsOn: new DateTimeImmutable('2030-09-09'),
        );

        $slotMonday = $this->createFixedSlot(
            fixedReservation: $fixedReservation,
            court: $scenario['court'],
            dayOfWeek: 1,
            startTime: '18:00:00',
            durationMinutes: 60,
        );

        $slotTuesday = $this->createFixedSlot(
            fixedReservation: $fixedReservation,
            court: $scenario['court'],
            dayOfWeek: 2,
            startTime: '20:00:00',
            durationMinutes: 120,
        );

        $generator = $this->app->make(
            GenerateFixedReservationOccurrences::class
        );

        $generator->generate(
            fixedReservation: $this->domainFixedReservation($fixedReservation->id),
            from: new DateTimeImmutable('2030-09-09'),
            to: new DateTimeImmutable('2030-10-06'),
        );

        $this->assertSame(
            8,
            Reservation::query()
                ->whereNotNull('fixed_reservation_slot_id')
                ->count()
        );

        $this->assertSame(
            4,
            Reservation::query()
                ->where('fixed_reservation_slot_id', $slotMonday->id)
                ->count()
        );

        $this->assertSame(
            4,
            Reservation::query()
                ->where('fixed_reservation_slot_id', $slotTuesday->id)
                ->count()
        );

        $this->assertSame(
            0,
            Reservation::query()
                ->whereNotNull('fixed_reservation_slot_id')
                ->where('status', '!=', ReservationStatus::CONFIRMED->value)
                ->count()
        );

        $this->assertSame(
            0,
            Reservation::query()
                ->whereNotNull('fixed_reservation_slot_id')
                ->whereNotNull('expires_at')
                ->count()
        );
    }

    public function test_generador_es_idempotente_y_no_duplica_ocurrencias(): void
    {
        $scenario = $this->createCourtScenario();

        $fixedReservation = $this->createFixedReservation(
            club: $scenario['club'],
            createdBy: $scenario['user'],
            startsOn: new DateTimeImmutable('2030-09-09'),
        );

        $slot = $this->createFixedSlot(
            fixedReservation: $fixedReservation,
            court: $scenario['court'],
            dayOfWeek: 1,
        );

        $generator = $this->app->make(
            GenerateFixedReservationOccurrences::class
        );

        $domain = $this->domainFixedReservation(
            $fixedReservation->id
        );

        $generator->generate(
            fixedReservation: $domain,
            from: new DateTimeImmutable('2030-09-09'),
            to: new DateTimeImmutable('2030-10-06'),
        );

        $generator->generate(
            fixedReservation: $domain,
            from: new DateTimeImmutable('2030-09-09'),
            to: new DateTimeImmutable('2030-10-06'),
        );

        $this->assertSame(
            4,
            Reservation::query()
                ->where('fixed_reservation_slot_id', $slot->id)
                ->count()
        );
    }

    public function test_ocurrencia_cancelada_no_se_regenera(): void
    {
        $scenario = $this->createCourtScenario();

        $fixedReservation = $this->createFixedReservation(
            club: $scenario['club'],
            createdBy: $scenario['user'],
            startsOn: new DateTimeImmutable('2030-09-09'),
        );

        $slot = $this->createFixedSlot(
            fixedReservation: $fixedReservation,
            court: $scenario['court'],
            dayOfWeek: 1,
        );

        $generator = $this->app->make(
            GenerateFixedReservationOccurrences::class
        );

        $domain = $this->domainFixedReservation(
            $fixedReservation->id
        );

        $generator->generate(
            fixedReservation: $domain,
            from: new DateTimeImmutable('2030-09-09'),
            to: new DateTimeImmutable('2030-09-23'),
        );

        $occurrence = Reservation::query()
            ->where('fixed_reservation_slot_id', $slot->id)
            ->whereDate('recurrence_date', '2030-09-16')
            ->firstOrFail();

        $occurrence->update([
            'status' => ReservationStatus::CANCELLED->value,
            'cancelled_at' => now(),
        ]);

        $generator->generate(
            fixedReservation: $domain,
            from: new DateTimeImmutable('2030-09-09'),
            to: new DateTimeImmutable('2030-09-23'),
        );

        $this->assertSame(
            1,
            Reservation::query()
                ->where('fixed_reservation_slot_id', $slot->id)
                ->whereDate('recurrence_date', '2030-09-16')
                ->count()
        );

        $this->assertDatabaseHas('reservations', [
            'id' => $occurrence->id,
            'status' => ReservationStatus::CANCELLED->value,
        ]);

        $this->assertDatabaseHas('reservations', [
            'fixed_reservation_slot_id' => $slot->id,
            'recurrence_date' => '2030-09-23 00:00:00',
            'status' => ReservationStatus::CONFIRMED->value,
        ]);
    }

    public function test_job_mode_salta_conflicto_registra_alerta_y_continua(): void
    {
        $scenario = $this->createCourtScenario();

        $fixedReservation = $this->createFixedReservation(
            club: $scenario['club'],
            createdBy: $scenario['user'],
            startsOn: new DateTimeImmutable('2030-09-09'),
        );

        $slot = $this->createFixedSlot(
            fixedReservation: $fixedReservation,
            court: $scenario['court'],
            dayOfWeek: 1,
        );

        $this->createBlockingReservation(
            court: $scenario['court'],
            startsAt: new DateTimeImmutable('2030-09-16 18:00:00'),
            endsAt: new DateTimeImmutable('2030-09-16 19:00:00'),
        );

        $generator = $this->app->make(
            GenerateFixedReservationOccurrences::class
        );

        $generator->generate(
            fixedReservation: $this->domainFixedReservation($fixedReservation->id),
            from: new DateTimeImmutable('2030-09-09'),
            to: new DateTimeImmutable('2030-09-23'),
            skipConflicts: true,
        );

        $this->assertDatabaseHas('reservations', [
            'fixed_reservation_slot_id' => $slot->id,
            'recurrence_date' => '2030-09-09 00:00:00',
            'status' => ReservationStatus::CONFIRMED->value,
        ]);

        $this->assertDatabaseMissing('reservations', [
            'fixed_reservation_slot_id' => $slot->id,
            'recurrence_date' => '2030-09-16 00:00:00',
        ]);

        $this->assertDatabaseHas('reservations', [
            'fixed_reservation_slot_id' => $slot->id,
            'recurrence_date' => '2030-09-23 00:00:00',
            'status' => ReservationStatus::CONFIRMED->value,
        ]);

        $this->assertDatabaseHas('fixed_reservation_conflicts', [
            'fixed_reservation_id' => $fixedReservation->id,
            'fixed_reservation_slot_id' => $slot->id,
            'court_id' => $scenario['court']->id,
            'recurrence_date' => '2030-09-16 00:00:00',
            'reason' => 'court_not_available',
            'resolved' => 0,
        ]);
    }

    public function test_modo_estricto_lanza_excepcion_si_hay_conflicto(): void
    {
        $this->expectException(
            CourtNotAvailableException::class
        );

        $scenario = $this->createCourtScenario();

        $fixedReservation = $this->createFixedReservation(
            club: $scenario['club'],
            createdBy: $scenario['user'],
            startsOn: new DateTimeImmutable('2030-09-16'),
        );

        $this->createFixedSlot(
            fixedReservation: $fixedReservation,
            court: $scenario['court'],
            dayOfWeek: 1,
        );

        $this->createBlockingReservation(
            court: $scenario['court'],
            startsAt: new DateTimeImmutable('2030-09-16 18:00:00'),
            endsAt: new DateTimeImmutable('2030-09-16 19:00:00'),
        );

        $generator = $this->app->make(
            GenerateFixedReservationOccurrences::class
        );

        $generator->generate(
            fixedReservation: $this->domainFixedReservation($fixedReservation->id),
            from: new DateTimeImmutable('2030-09-16'),
            to: new DateTimeImmutable('2030-09-16'),
        );
    }

    public function test_conflicto_se_resuelve_automaticamente_si_horario_queda_libre(): void
    {
        $scenario = $this->createCourtScenario();

        $fixedReservation = $this->createFixedReservation(
            club: $scenario['club'],
            createdBy: $scenario['user'],
            startsOn: new DateTimeImmutable('2030-09-16'),
        );

        $slot = $this->createFixedSlot(
            fixedReservation: $fixedReservation,
            court: $scenario['court'],
            dayOfWeek: 1,
        );

        $blocking = $this->createBlockingReservation(
            court: $scenario['court'],
            startsAt: new DateTimeImmutable('2030-09-16 18:00:00'),
            endsAt: new DateTimeImmutable('2030-09-16 19:00:00'),
        );

        $generator = $this->app->make(
            GenerateFixedReservationOccurrences::class
        );

        $domain = $this->domainFixedReservation(
            $fixedReservation->id
        );

        $generator->generate(
            fixedReservation: $domain,
            from: new DateTimeImmutable('2030-09-16'),
            to: new DateTimeImmutable('2030-09-16'),
            skipConflicts: true,
        );

        $conflict = FixedReservationConflict::query()
            ->where('fixed_reservation_slot_id', $slot->id)
            ->whereDate('recurrence_date', '2030-09-16')
            ->firstOrFail();

        $this->assertFalse(
            (bool) $conflict->resolved
        );

        $blocking->delete();

        $generator->generate(
            fixedReservation: $domain,
            from: new DateTimeImmutable('2030-09-16'),
            to: new DateTimeImmutable('2030-09-16'),
            skipConflicts: true,
        );

        $conflict->refresh();

        $this->assertTrue(
            (bool) $conflict->resolved
        );

        $this->assertNotNull(
            $conflict->resolved_at
        );

        $this->assertNull(
            $conflict->resolved_by_user_id
        );

        $this->assertDatabaseHas('reservations', [
            'fixed_reservation_slot_id' => $slot->id,
            'recurrence_date' => '2030-09-16 00:00:00',
            'status' => ReservationStatus::CONFIRMED->value,
        ]);
    }

    private function domainFixedReservation(int $id)
    {
        $repository = $this->app->make(
            \App\Domain\Reservations\FixedReservations\Repositories\FixedReservationRepository::class
        );

        return $repository->findById($id);
    }
}
