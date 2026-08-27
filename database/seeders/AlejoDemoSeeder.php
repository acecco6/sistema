<?php

namespace Database\Seeders;

use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Branch;
use App\Models\Club;
use App\Models\Court;
use App\Models\CourtPrice;
use App\Models\CourtPriceRule;
use App\Models\Membership;
use App\Models\Reservation;
use App\Models\ReservationPriceSegment;
use App\Models\Role;
use App\Models\TipoCourt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class AlejoDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            /*
            |--------------------------------------------------------------------------
            | Usuario principal
            |--------------------------------------------------------------------------
            */

            $alejo = User::updateOrCreate(
                ['email' => 'acecco6@gmail.com'],
                [
                    'name' => 'Alejo Cecco',
                    'password' => Hash::make('hola1234'),
                    'active' => true,
                ]
            );

            $customers = $this->createDemoCustomers();

            /*
            |--------------------------------------------------------------------------
            | Roles
            |--------------------------------------------------------------------------
            */

            $admin = Role::findOrFail(2);
            $manager = Role::findOrFail(3);
            $employee = Role::findOrFail(4);

            /*
            |--------------------------------------------------------------------------
            | CLUB 1 - Alejo ADMIN GLOBAL
            |--------------------------------------------------------------------------
            */

            $clubGlobal = Club::factory()->createOne([
                'name' => 'Padel Center Global',
                'active' => true,
            ]);

            $branchesGlobal = Branch::factory()
                ->count(5)
                ->for($clubGlobal)
                ->create();

            $this->createCompleteBranchData($branchesGlobal);

            Membership::create([
                'user_id' => $alejo->id,
                'club_id' => $clubGlobal->id,
                'rol_id' => $admin->id,
                'branch_id' => null,
                'active' => true,
            ]);

            /*
            |--------------------------------------------------------------------------
            | CLUB 2 - acceso parcial
            |--------------------------------------------------------------------------
            */

            $clubParcial = Club::factory()->createOne([
                'name' => 'Padel Center Parcial',
                'active' => true,
            ]);

            $branchesParciales = Branch::factory()
                ->count(6)
                ->for($clubParcial)
                ->create();

            $this->createCompleteBranchData($branchesParciales);

            Membership::create([
                'user_id' => $alejo->id,
                'club_id' => $clubParcial->id,
                'rol_id' => $manager->id,
                'branch_id' => $branchesParciales[0]->id,
                'active' => true,
            ]);

            Membership::create([
                'user_id' => $alejo->id,
                'club_id' => $clubParcial->id,
                'rol_id' => $employee->id,
                'branch_id' => $branchesParciales[2]->id,
                'active' => true,
            ]);

            /*
            |--------------------------------------------------------------------------
            | CLUB 3 - Admin específico de una branch
            |--------------------------------------------------------------------------
            */

            $clubAdminParcial = Club::factory()->createOne([
                'name' => 'Club Admin Parcial',
                'active' => true,
            ]);

            $branchesAdmin = Branch::factory()
                ->count(4)
                ->for($clubAdminParcial)
                ->create();

            $this->createCompleteBranchData($branchesAdmin);

            Membership::create([
                'user_id' => $alejo->id,
                'club_id' => $clubAdminParcial->id,
                'rol_id' => $admin->id,
                'branch_id' => $branchesAdmin[1]->id,
                'active' => true,
            ]);

            /*
            |--------------------------------------------------------------------------
            | CLUB 4 - membership inactiva
            |--------------------------------------------------------------------------
            */

            $clubInactivo = Club::factory()->createOne([
                'name' => 'Club Membership Inactiva',
                'active' => true,
            ]);

            $branchesInactivas = Branch::factory()
                ->count(2)
                ->for($clubInactivo)
                ->create();

            $this->createCompleteBranchData($branchesInactivas);

            $branchInactiva = $branchesInactivas->first();

            Membership::create([
                'user_id' => $alejo->id,
                'club_id' => $clubInactivo->id,
                'rol_id' => $manager->id,
                'branch_id' => $branchInactiva->id,
                'active' => false,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Reservas demo
            |--------------------------------------------------------------------------
            |
            | Generamos reservas determinísticas para poder probar fácilmente:
            | - confirmed
            | - pending vigente
            | - pending vencida (para probar el Job)
            | - expired
            | - cancelled
            | - completed
            | - cliente registrado
            | - guest
            | - reserva creada por staff
            |
            */

            $allBranches = collect()
                ->concat($branchesGlobal)
                ->concat($branchesParciales)
                ->concat($branchesAdmin)
                ->concat($branchesInactivas);

            $this->createReservationsForBranches(
                branches: $allBranches,
                alejo: $alejo,
                customers: $customers,
            );
        });
    }

    /**
     * Crea courts, intervalos, precios base y promociones para cada branch.
     */
    private function createCompleteBranchData(Collection $branches): void
    {
        $tiposCourt = TipoCourt::query()->get();

        if ($tiposCourt->isEmpty()) {
            throw new \RuntimeException(
                'No existen tipos de cancha. Ejecutá TipoCourtSeeder antes de AlejoDemoSeeder.'
            );
        }

        foreach ($branches as $branch) {
            $cantidadCourts = fake()->numberBetween(3, 6);
            $tiposUsados = collect();

            for ($index = 1; $index <= $cantidadCourts; $index++) {
                /** @var TipoCourt $tipoCourt */
                $tipoCourt = $tiposCourt->random();

                $tiposUsados->put($tipoCourt->id, $tipoCourt);

                Court::factory()->create([
                    'branch_id' => $branch->id,
                    'tipo_court_id' => $tipoCourt->id,
                    'name' => "{$tipoCourt->name} {$index}",
                    'active' => true,
                ]);
            }

            foreach ($tiposUsados as $tipoCourt) {
                DB::table('interval_time_tipo_court')->updateOrInsert(
                    [
                        'branch_id' => $branch->id,
                        'tipo_court_id' => $tipoCourt->id,
                    ],
                    [
                        'interval_minutes' => $this->intervalForTipoCourt($tipoCourt->name),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $courtPrice = CourtPrice::create([
                    'branch_id' => $branch->id,
                    'tipo_court_id' => $tipoCourt->id,
                    'price' => $this->basePriceForTipoCourt($tipoCourt->name),
                    'active' => true,
                ]);

                $this->createPriceRules($courtPrice);
            }
        }
    }

    private function createPriceRules(CourtPrice $courtPrice): void
    {
        $base = (float) $courtPrice->price;

        // Promo semanal en horario de menor demanda.
        CourtPriceRule::create([
            'court_price_id' => $courtPrice->id,
            'name' => 'Promo tarde semanal',
            'price' => round($base * 0.80, 2),
            'day_of_week' => 2,
            'specific_date' => null,
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
            'priority' => 10,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->addMonths(6),
            'active' => true,
        ]);

        // Promo puntual con mayor prioridad.
        CourtPriceRule::create([
            'court_price_id' => $courtPrice->id,
            'name' => 'Promo especial',
            'price' => round($base * 0.65, 2),
            'day_of_week' => null,
            'specific_date' => now()->addDays(10)->toDateString(),
            'start_time' => '18:00:00',
            'end_time' => '21:00:00',
            'priority' => 100,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'active' => true,
        ]);

        // Regla inactiva para poder probar filtros.
        CourtPriceRule::create([
            'court_price_id' => $courtPrice->id,
            'name' => 'Promo inactiva demo',
            'price' => round($base * 0.50, 2),
            'day_of_week' => 5,
            'specific_date' => null,
            'start_time' => '10:00:00',
            'end_time' => '13:00:00',
            'priority' => 999,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'active' => false,
        ]);
    }

    /**
     * @return Collection<int, User>
     */
    private function createDemoCustomers(): Collection
    {
        return collect([
            ['name' => 'Cliente Demo Uno', 'email' => 'cliente1@demo.test'],
            ['name' => 'Cliente Demo Dos', 'email' => 'cliente2@demo.test'],
            ['name' => 'Cliente Demo Tres', 'email' => 'cliente3@demo.test'],
        ])->map(function (array $data): User {
            return User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('hola1234'),
                    'active' => true,
                ]
            );
        });
    }

    private function createReservationsForBranches(
        Collection $branches,
        User $alejo,
        Collection $customers,
    ): void {
        foreach ($branches as $branch) {
            $courts = Court::query()
                ->where('branch_id', $branch->id)
                ->where('active', true)
                ->take(2)
                ->get();

            foreach ($courts as $courtIndex => $court) {
                $courtPrice = CourtPrice::query()
                    ->where('branch_id', $branch->id)
                    ->where('tipo_court_id', $court->tipo_court_id)
                    ->where('active', true)
                    ->first();

                if ($courtPrice === null) {
                    continue;
                }

                $basePrice = (float) $courtPrice->price;
                $customer = $customers->random();

                // Cada cancha arranca en una hora distinta para evitar overlaps accidentales.
                $hourOffset = $courtIndex * 3;

                $this->createReservationWithSnapshot(
                    court: $court,
                    startsAt: CarbonImmutable::now()->addDays(2)->setTime(17 + $hourOffset, 0),
                    endsAt: CarbonImmutable::now()->addDays(2)->setTime(18 + $hourOffset, 0),
                    status: ReservationStatus::CONFIRMED,
                    hourlyPrice: $basePrice,
                    customerUserId: $customer->id,
                    createdByUserId: $alejo->id,
                    expiresAt: null,
                    notes: 'Reserva confirmada creada por staff.',
                );

                $this->createReservationWithSnapshot(
                    court: $court,
                    startsAt: CarbonImmutable::now()->addDays(3)->setTime(17 + $hourOffset, 0),
                    endsAt: CarbonImmutable::now()->addDays(3)->setTime(18 + $hourOffset, 0),
                    status: ReservationStatus::PENDING,
                    hourlyPrice: $basePrice,
                    customerUserId: $customer->id,
                    createdByUserId: $customer->id,
                    expiresAt: CarbonImmutable::now()->addMinutes(15),
                    notes: 'Pending vigente de cliente autenticado.',
                );

                $this->createReservationWithSnapshot(
                    court: $court,
                    startsAt: CarbonImmutable::now()->addDays(4)->setTime(17 + $hourOffset, 0),
                    endsAt: CarbonImmutable::now()->addDays(4)->setTime(18 + $hourOffset, 0),
                    status: ReservationStatus::PENDING,
                    hourlyPrice: $basePrice,
                    guestName: 'Guest pendiente vencido',
                    guestEmail: 'guest.vencido@demo.test',
                    guestPhone: '1111111111',
                    expiresAt: CarbonImmutable::now()->subMinutes(5),
                    notes: 'Pending vencida para probar ExpirePendingReservationsJob.',
                );

                $this->createReservationWithSnapshot(
                    court: $court,
                    startsAt: CarbonImmutable::now()->addDays(5)->setTime(17 + $hourOffset, 0),
                    endsAt: CarbonImmutable::now()->addDays(5)->setTime(18 + $hourOffset, 0),
                    status: ReservationStatus::EXPIRED,
                    hourlyPrice: $basePrice,
                    guestName: 'Guest expirado',
                    guestEmail: 'guest.expired@demo.test',
                    guestPhone: '2222222222',
                    expiresAt: CarbonImmutable::now()->subHour(),
                    notes: 'Reserva ya expirada.',
                );

                $this->createReservationWithSnapshot(
                    court: $court,
                    startsAt: CarbonImmutable::now()->addDay()->setTime(19 + $hourOffset, 0),
                    endsAt: CarbonImmutable::now()->addDay()->setTime(20 + $hourOffset, 0),
                    status: ReservationStatus::CANCELLED,
                    hourlyPrice: $basePrice,
                    customerUserId: $customer->id,
                    createdByUserId: $alejo->id,
                    expiresAt: null,
                    cancelledAt: CarbonImmutable::now()->subHour(),
                    notes: 'Reserva cancelada demo.',
                );

                $this->createReservationWithSnapshot(
                    court: $court,
                    startsAt: CarbonImmutable::now()->subDays(2)->setTime(18 + $hourOffset, 0),
                    endsAt: CarbonImmutable::now()->subDays(2)->setTime(19 + $hourOffset, 0),
                    status: ReservationStatus::COMPLETED,
                    hourlyPrice: $basePrice,
                    customerUserId: $customer->id,
                    createdByUserId: $alejo->id,
                    expiresAt: null,
                    notes: 'Reserva completada demo.',
                );
            }
        }
    }

    private function createReservationWithSnapshot(
        Court $court,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ReservationStatus $status,
        float $hourlyPrice,
        ?int $customerUserId = null,
        ?int $createdByUserId = null,
        ?string $guestName = null,
        ?string $guestEmail = null,
        ?string $guestPhone = null,
        ?CarbonImmutable $expiresAt = null,
        ?CarbonImmutable $cancelledAt = null,
        ?string $notes = null,
    ): void {
        $minutes = $startsAt->diffInMinutes($endsAt);
        $subtotal = round($hourlyPrice * ($minutes / 60), 2);

        $reservation = Reservation::create([
            'court_id' => $court->id,
            'customer_user_id' => $customerUserId,
            'created_by_user_id' => $createdByUserId,
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'guest_phone' => $guestPhone,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'total_price' => $subtotal,
            'status' => $status->value,
            'public_token' => (string) Str::uuid(),
            'notes' => $notes,
            'cancelled_at' => $cancelledAt,
            'expires_at' => $expiresAt,
        ]);

        ReservationPriceSegment::create([
            'reservation_id' => $reservation->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'hourly_price' => $hourlyPrice,
            'subtotal' => $subtotal,
            'court_price_rule_id' => null,
            'rule_name' => null,
        ]);
    }

    private function intervalForTipoCourt(string $tipo): int
    {
        return match ($tipo) {
            'Padel' => 30,
            'Tenis' => 60,
            'Fútbol 5' => 60,
            'Fútbol 7' => 60,
            default => 60,
        };
    }

    private function basePriceForTipoCourt(string $tipo): float
    {
        return match ($tipo) {
            'Padel' => 30000,
            'Tenis' => 26000,
            'Fútbol 5' => 45000,
            'Fútbol 7' => 65000,
            default => 30000,
        };
    }
}
