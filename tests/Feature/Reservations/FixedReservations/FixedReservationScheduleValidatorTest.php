<?php

namespace Tests\Feature\Reservations\FixedReservations;

use App\Application\Reservations\FixedReservations\Create\CreateFixedReservationCommand;
use App\Application\Reservations\FixedReservations\Create\CreateFixedReservationSlotData;
use App\Application\Reservations\FixedReservations\Validation\FixedReservationScheduleValidator;
use App\Domain\Reservations\FixedReservations\Exceptions\FixedReservationScheduleConflictException;
use DateInterval;
use DateTimeImmutable;

final class FixedReservationScheduleValidatorTest extends FixedReservationTestCase
{
    public function test_rechaza_slot_que_se_superpone_con_otra_serie_en_la_misma_cancha_y_dia(): void
    {
        $this->expectException(FixedReservationScheduleConflictException::class);
        $scenario = $this->createCourtScenario();
        $monday = (new DateTimeImmutable('next monday'))->setTime(0, 0);

        $otherCourt = \App\Models\Court::factory()->create([
            'branch_id' => $scenario['branch']->id, 'tipo_court_id' => $scenario['tipoCourt']->id, 'active' => true,
        ]);
        $existing = $this->createFixedReservation($scenario['club'], $scenario['user'], $monday);
        $this->createFixedSlot($existing, $scenario['court'], 1, '18:00:00', 60);
        $this->createFixedSlot($existing, $scenario['court'], 2, '18:00:00', 60);

        $command = new CreateFixedReservationCommand(
            clubId: $scenario['club']->id,
            customerUserId: null,
            guestName: 'Lucas', guestEmail: 'lucas@example.com', guestPhone: null,
            createdByUserId: $scenario['user']->id,
            startsOn: $monday,
            endsOn: null,
            notes: null,
            slots: [
                new CreateFixedReservationSlotData($otherCourt->id, 1, '18:00', 60),
                new CreateFixedReservationSlotData($scenario['court']->id, 2, '18:00', 60),
            ],
        );

        $this->app->make(FixedReservationScheduleValidator::class)->validate(
            $command, $monday, $monday->add(new DateInterval('P8W')),
        );
    }

    public function test_permite_mismo_horario_en_otra_cancha(): void
    {
        $scenario = $this->createCourtScenario();
        $otherCourt = \App\Models\Court::factory()->create([
            'branch_id' => $scenario['branch']->id, 'tipo_court_id' => $scenario['tipoCourt']->id, 'active' => true,
        ]);
        $monday = (new DateTimeImmutable('next monday'))->setTime(0, 0);
        $existing = $this->createFixedReservation($scenario['club'], $scenario['user'], $monday);
        $this->createFixedSlot($existing, $scenario['court'], 1, '18:00:00', 60);

        $command = new CreateFixedReservationCommand(
            clubId: $scenario['club']->id,
            customerUserId: null,
            guestName: 'Lucas', guestEmail: 'lucas@example.com', guestPhone: null,
            createdByUserId: $scenario['user']->id,
            startsOn: $monday, endsOn: null, notes: null,
            slots: [new CreateFixedReservationSlotData($otherCourt->id, 1, '18:00', 60)],
        );

        $this->app->make(FixedReservationScheduleValidator::class)->validate(
            $command, $monday, $monday->add(new DateInterval('P8W')),
        );

        $this->assertTrue(true);
    }
}
