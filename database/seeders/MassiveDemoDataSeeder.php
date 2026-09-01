<?php

namespace Database\Seeders;

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Branch;
use App\Models\Club;
use App\Models\Court;
use App\Models\CourtPrice;
use App\Models\CourtPriceRule;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Reservation;
use App\Models\ReservationPriceSegment;
use App\Models\Role;
use App\Models\TipoCourt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class MassiveDemoDataSeeder extends Seeder
{
    private const USUARIOS = 150;
    private const CLUBES = 20;

    public function run(): void
    {
        DB::transaction(function (): void {
            $tiposCourt = $this->crearTiposCourt();
            $clubs = $this->crearClubesConInfraestructura($tiposCourt);
            $users = $this->crearUsuarios();
            $roles = $this->obtenerRoles();

            $this->crearMemberships($users, $clubs, $roles);
            $this->crearReservasDemo($users);
        });
    }

    /**
     * @return Collection<int, TipoCourt>
     */
    private function crearTiposCourt(): Collection
    {
        return collect([
            [
                'name' => 'Padel',
                'description' => 'Cancha de pádel',
            ],
            [
                'name' => 'Tenis',
                'description' => 'Cancha de tenis',
            ],
            [
                'name' => 'Fútbol 5',
                'description' => 'Cancha de fútbol 5',
            ],
            [
                'name' => 'Fútbol 7',
                'description' => 'Cancha de fútbol 7',
            ],
        ])->map(
            fn (array $data) => TipoCourt::firstOrCreate(
                ['name' => $data['name']],
                ['description' => $data['description']]
            )
        );
    }

    /**
     * Crea Club -> Branch -> Court -> Intervalo -> CourtPrice -> CourtPriceRule.
     *
     * El intervalo se crea solamente para los tipos de cancha que realmente
     * existen en cada branch.
     *
     * @param Collection<int, TipoCourt> $tiposCourt
     * @return Collection<int, Club>
     */
    private function crearClubesConInfraestructura(Collection $tiposCourt): Collection
    {
        $clubs = collect();

        for ($i = 0; $i < self::CLUBES; $i++) {
            /** @var Club $club */
            $club = Club::factory()->createOne();
            $clubs->push($club);

            $branches = Branch::factory()
                ->count(fake()->numberBetween(2, 7))
                ->for($club)
                ->create();

            foreach ($branches as $branch) {
                $tiposUsados = collect();
                $cantidadCourts = fake()->numberBetween(2, 8);

                for ($courtNumber = 1; $courtNumber <= $cantidadCourts; $courtNumber++) {
                    /** @var TipoCourt $tipoCourt */
                    $tipoCourt = $tiposCourt->random();
                    $tiposUsados->put($tipoCourt->id, $tipoCourt);

                    Court::factory()->create([
                        'branch_id' => $branch->id,
                        'tipo_court_id' => $tipoCourt->id,
                        'name' => "{$tipoCourt->name} {$courtNumber}",
                        'active' => fake()->boolean(90),
                    ]);
                }

                foreach ($tiposUsados as $tipoCourt) {
                    $this->crearIntervalo($branch, $tipoCourt);
                    $this->crearPrecioYPromociones($branch, $tipoCourt);
                }
            }
        }

        return $clubs;
    }

    private function crearIntervalo(Branch $branch, TipoCourt $tipoCourt): void
    {
        DB::table('interval_time_tipo_court')->updateOrInsert(
            [
                'branch_id' => $branch->id,
                'tipo_court_id' => $tipoCourt->id,
            ],
            [
                'interval_minutes' => fake()->randomElement([30, 60]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function crearPrecioYPromociones(Branch $branch, TipoCourt $tipoCourt): void
    {
        $basePrice = match ($tipoCourt->name) {
            'Padel' => fake()->randomElement([30000, 32000, 35000, 38000]),
            'Tenis' => fake()->randomElement([22000, 25000, 28000]),
            'Fútbol 5' => fake()->randomElement([45000, 50000, 55000]),
            'Fútbol 7' => fake()->randomElement([65000, 70000, 75000]),
            default => fake()->numberBetween(20000, 50000),
        };

        /** @var CourtPrice $courtPrice */
        $courtPrice = CourtPrice::updateOrCreate(
            [
                'branch_id' => $branch->id,
                'tipo_court_id' => $tipoCourt->id,
            ],
            [
                'price' => $basePrice,
                'active' => true,
            ]
        );

        // Promo semanal: lunes a viernes, 14:00 - 18:00.
        // Creamos varias reglas, una por día, porque day_of_week es un único entero.
        foreach ([1, 2, 3, 4, 5] as $dayOfWeek) {
            CourtPriceRule::create([
                'court_price_id' => $courtPrice->id,
                'name' => 'Promo tarde',
                'price' => round($basePrice * 0.80, 2),
                'day_of_week' => $dayOfWeek,
                'specific_date' => null,
                'start_time' => '14:00:00',
                'end_time' => '18:00:00',
                'priority' => 10,
                'starts_at' => now()->subMonths(2)->startOfDay(),
                'ends_at' => now()->addMonths(6)->endOfDay(),
                'active' => true,
            ]);
        }

        // Promo de fecha específica con mayor prioridad.
        $specificDate = now()->addDays(fake()->numberBetween(3, 20))->startOfDay();

        CourtPriceRule::create([
            'court_price_id' => $courtPrice->id,
            'name' => 'Promo especial',
            'price' => round($basePrice * 0.70, 2),
            'day_of_week' => null,
            'specific_date' => $specificDate->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '13:00:00',
            'priority' => 20,
            'starts_at' => now()->subDay()->startOfDay(),
            'ends_at' => now()->addMonths(2)->endOfDay(),
            'active' => true,
        ]);

        // Algunas reglas inactivas sirven para probar que el resolver las ignore.
        if (fake()->boolean(25)) {
            CourtPriceRule::create([
                'court_price_id' => $courtPrice->id,
                'name' => 'Promo inactiva',
                'price' => round($basePrice * 0.50, 2),
                'day_of_week' => null,
                'specific_date' => null,
                'start_time' => '18:00:00',
                'end_time' => '20:00:00',
                'priority' => 99,
                'starts_at' => now()->subMonth()->startOfDay(),
                'ends_at' => now()->addMonth()->endOfDay(),
                'active' => false,
            ]);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function crearUsuarios(): Collection
    {
        return User::factory()
            ->count(self::USUARIOS)
            ->create([
                'active' => true,
            ]);
    }

    /**
     * 2 = Admin
     * 3 = Manager
     * 4 = Employee
     *
     * @return Collection<int, Role>
     */
    private function obtenerRoles(): Collection
    {
        $roles = Role::query()
            ->whereIn('id', [2, 3, 4])
            ->get();

        if ($roles->isEmpty()) {
            throw new RuntimeException(
                'MassiveDemoDataSeeder necesita los roles 2, 3 y 4. Ejecutá primero los seeders de roles.'
            );
        }

        return $roles;
    }

    /**
     * @param Collection<int, User> $users
     * @param Collection<int, Club> $clubs
     * @param Collection<int, Role> $roles
     */
    private function crearMemberships(Collection $users, Collection $clubs, Collection $roles): void
    {
        foreach ($users as $user) {
            $clubsUsuario = $clubs
                ->shuffle()
                ->take(fake()->numberBetween(1, min(5, $clubs->count())));

            foreach ($clubsUsuario as $club) {
                $branches = Branch::query()
                    ->where('club_id', $club->id)
                    ->get();

                if ($branches->isEmpty()) {
                    continue;
                }

                // Membership global: no se mezclan memberships específicas del mismo club.
                if (fake()->boolean(25)) {
                    Membership::create([
                        'user_id' => $user->id,
                        'club_id' => $club->id,
                        'rol_id' => $roles->random()->id,
                        'branch_id' => null,
                        'active' => fake()->boolean(90),
                    ]);

                    continue;
                }

                $cantidadBranches = fake()->numberBetween(
                    1,
                    min(3, $branches->count())
                );

                foreach ($branches->shuffle()->take($cantidadBranches) as $branch) {
                    Membership::create([
                        'user_id' => $user->id,
                        'club_id' => $club->id,
                        'rol_id' => $roles->random()->id,
                        'branch_id' => $branch->id,
                        'active' => fake()->boolean(90),
                    ]);
                }
            }
        }
    }

    /**
     * Genera reservas demo sin overlaps dentro de una misma cancha.
     *
     * Cada court recibe reservas en días/horarios distintos. Los precios
     * históricos se guardan como snapshot en reservation_price_segments.
     *
     * @param Collection<int, User> $users
     */
    private function crearReservasDemo(Collection $users): void
    {
        $courts = Court::query()
            ->where('active', true)
            ->with('branch')
            ->get();

        foreach ($courts as $court) {
            $courtPrice = CourtPrice::query()
                ->where('branch_id', $court->branch_id)
                ->where('tipo_court_id', $court->tipo_court_id)
                ->where('active', true)
                ->first();

            if ($courtPrice === null) {
                continue;
            }

            $intervalMinutes = (int) DB::table('interval_time_tipo_court')
                ->where('branch_id', $court->branch_id)
                ->where('tipo_court_id', $court->tipo_court_id)
                ->value('interval_minutes');

            if ($intervalMinutes <= 0) {
                continue;
            }

            // Entre 8 y 14 por cancha para tener datos abundantes sin exagerar.
            $cantidadReservas = fake()->numberBetween(8, 14);

            for ($i = 0; $i < $cantidadReservas; $i++) {
                $this->crearReservaParaCourt(
                    court: $court,
                    courtPrice: $courtPrice,
                    intervalMinutes: $intervalMinutes,
                    users: $users,
                    index: $i,
                );
            }
        }
    }

    /**
     * @param Collection<int, User> $users
     */
    private function crearReservaParaCourt(
        Court $court,
        CourtPrice $courtPrice,
        int $intervalMinutes,
        Collection $users,
        int $index,
    ): void {
        $scenario = $index % 7;

        [$startsAt, $endsAt, $status, $expiresAt, $cancelledAt] =
            $this->crearEscenarioTemporal($scenario, $intervalMinutes, $index);

        // Aproximadamente 65% son clientes registrados y 35% guest.
        $esClienteRegistrado = fake()->boolean(65);
        /** @var User|null $customer */
        $customer = $esClienteRegistrado ? $users->random() : null;

        // Algunas reservas fueron creadas por personal.
        $createdBy = fake()->boolean(35)
            ? $this->buscarStaffParaCourt($court)
            : null;

        $guestName = null;
        $guestEmail = null;
        $guestPhone = null;

        if (! $esClienteRegistrado) {
            $guestName = fake()->name();

            if (fake()->boolean()) {
                $guestEmail = fake()->safeEmail();
            } else {
                $guestPhone = fake()->numerify('11########');
            }
        }

        [$hourlyPrice, $rule] = $this->resolverPrecioDemo(
            courtPrice: $courtPrice,
            startsAt: $startsAt,
        );

        $durationMinutes = $startsAt->diffInMinutes($endsAt);
        $subtotal = round(($hourlyPrice / 60) * $durationMinutes, 2);

        /** @var Reservation $reservation */
        $reservation = Reservation::create([
            'court_id' => $court->id,
            'customer_user_id' => $customer?->id,
            'created_by_user_id' => $createdBy?->id,
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'guest_phone' => $guestPhone,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'total_price' => $subtotal,
            'status' => $status->value,
            'public_token' => (string) Str::uuid(),
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
            'cancelled_at' => $cancelledAt,
            'expires_at' => $expiresAt,
        ]);

        ReservationPriceSegment::create([
            'reservation_id' => $reservation->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'hourly_price' => $hourlyPrice,
            'subtotal' => $subtotal,
            'court_price_rule_id' => $rule?->id,
            'rule_name' => $rule?->name,
        ]);

        $this->crearEscenarioFinanciero(
            reservation: $reservation,
            status: $status,
            court: $court,
            staff: $createdBy,
            index: $index,
        );
    }

    private function crearEscenarioFinanciero(
        Reservation $reservation,
        ReservationStatus $status,
        Court $court,
        ?User $staff,
        int $index,
    ): void {
        $total = $this->money($reservation->total_price);
        $deposit = bcdiv($total, '2', 2);
        $staff ??= $this->buscarStaffParaCourt($court);

        if ($status === ReservationStatus::PENDING) {
            // La mayoría de las pending tienen checkout pendiente; algunas ya fueron rechazadas.
            $paymentStatus = $reservation->expires_at !== null && $reservation->expires_at->isPast()
                ? PaymentStatus::REJECTED
                : PaymentStatus::PENDING;

            $this->crearPago(
                reservation: $reservation,
                amount: $deposit,
                method: PaymentMethod::MERCADO_PAGO,
                status: $paymentStatus,
            );

            return;
        }

        if ($status === ReservationStatus::EXPIRED) {
            $this->crearPago(
                reservation: $reservation,
                amount: $deposit,
                method: PaymentMethod::MERCADO_PAGO,
                status: fake()->randomElement([
                    PaymentStatus::REJECTED,
                    PaymentStatus::CANCELLED,
                ]),
            );

            return;
        }

        if ($status === ReservationStatus::CONFIRMED) {
            // Alternamos entre seña, pago total y pago dividido en dos movimientos.
            match ($index % 3) {
                0 => $this->crearPago(
                    reservation: $reservation,
                    amount: $deposit,
                    method: PaymentMethod::MERCADO_PAGO,
                    status: PaymentStatus::APPROVED,
                ),
                1 => $this->crearPago(
                    reservation: $reservation,
                    amount: $total,
                    method: PaymentMethod::TRANSFER,
                    status: PaymentStatus::APPROVED,
                    createdByUserId: $staff?->id,
                ),
                default => $this->crearPagoDividido(
                    reservation: $reservation,
                    total: $total,
                    staff: $staff,
                ),
            };

            return;
        }

        if ($status === ReservationStatus::COMPLETED) {
            $this->crearPagoDividido(
                reservation: $reservation,
                total: $total,
                staff: $staff,
            );

            return;
        }

        if ($status === ReservationStatus::CANCELLED) {
            // Algunas canceladas nunca llegaron a cobrar nada.
            if (fake()->boolean(20)) {
                return;
            }

            $payment = $this->crearPago(
                reservation: $reservation,
                amount: $total,
                method: $index % 2 === 0
                    ? PaymentMethod::MERCADO_PAGO
                    : PaymentMethod::TRANSFER,
                status: PaymentStatus::APPROVED,
                createdByUserId: $index % 2 === 0 ? null : $staff?->id,
            );

            $refundStatus = fake()->randomElement([
                RefundStatus::PENDING,
                RefundStatus::COMPLETED,
                RefundStatus::CANCELLED,
            ]);

            // Un refund COMPLETED siempre debe identificar quién lo completó.
            if ($refundStatus === RefundStatus::COMPLETED && $staff === null) {
                $refundStatus = RefundStatus::PENDING;
            }

            $this->crearRefund(
                reservation: $reservation,
                payment: $payment,
                amount: $total,
                status: $refundStatus,
                staff: $staff,
            );
        }
    }

    private function crearPagoDividido(
        Reservation $reservation,
        string $total,
        ?User $staff,
    ): void {
        $first = bcdiv($total, '2', 2);
        $second = bcsub($total, $first, 2);

        $this->crearPago(
            reservation: $reservation,
            amount: $first,
            method: PaymentMethod::CASH,
            status: PaymentStatus::APPROVED,
            createdByUserId: $staff?->id,
        );

        $this->crearPago(
            reservation: $reservation,
            amount: $second,
            method: PaymentMethod::CARD,
            status: PaymentStatus::APPROVED,
            createdByUserId: $staff?->id,
        );
    }

    private function crearPago(
        Reservation $reservation,
        string $amount,
        PaymentMethod $method,
        PaymentStatus $status,
        ?int $createdByUserId = null,
    ): Payment {
        $isMercadoPago = $method === PaymentMethod::MERCADO_PAGO;
        $isApproved = $status === PaymentStatus::APPROVED;

        return Payment::create([
            'reservation_id' => $reservation->id,
            'amount' => $amount,
            'method' => $method->value,
            'status' => $status->value,
            'provider' => $isMercadoPago ? 'mercadopago' : null,
            'provider_preference_id' => $isMercadoPago ? 'pref_' . Str::uuid() : null,
            'provider_payment_id' => $isMercadoPago && $isApproved
                ? 'mp_' . Str::uuid()
                : null,
            'external_reference' => 'MASSIVE-PAY-' . Str::uuid(),
            'checkout_url' => $isMercadoPago
                ? 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=demo'
                : null,
            'created_by_user_id' => $createdByUserId,
            'paid_at' => $isApproved ? now() : null,
        ]);
    }

    private function crearRefund(
        Reservation $reservation,
        Payment $payment,
        string $amount,
        RefundStatus $status,
        ?User $staff,
    ): PaymentRefund {
        $completed = $status === RefundStatus::COMPLETED;

        return PaymentRefund::create([
            'reservation_id' => $reservation->id,
            'payment_id' => $payment->id,
            'amount' => $amount,
            'status' => $status,
            'reason' => 'Cancelación de reserva demo',
            'method' => $completed ? PaymentMethod::TRANSFER : null,
            'notes' => $completed
                ? 'Devolución completada en dataset masivo.'
                : ($status === RefundStatus::PENDING
                    ? 'Devolución pendiente en dataset masivo.'
                    : 'Devolución cancelada en dataset masivo.'),
            'created_by_user_id' => $staff?->id,
            'completed_by_user_id' => $completed ? $staff?->id : null,
            'completed_at' => $completed ? now() : null,
        ]);
    }

    private function money(string|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Crea estados útiles para probar disponibilidad, expiración y reporting.
     *
     * @return array{0: Carbon, 1: Carbon, 2: ReservationStatus, 3: ?Carbon, 4: ?Carbon}
     */
    private function crearEscenarioTemporal(
        int $scenario,
        int $intervalMinutes,
        int $index,
    ): array {
        // Separar cada reserva por día evita overlaps incluso si el intervalo es 60.
        $futureDay = now()->addDays(2 + $index)->startOfDay();
        $pastDay = now()->subDays(2 + $index)->startOfDay();

        $hour = match ($index % 4) {
            0 => 10,
            1 => 14,
            2 => 16,
            default => 18,
        };

        // La reserva mínima del dominio es 60 minutos.
        // Para intervalos de 30, sigue siendo múltiplo válido del intervalo.
        $duration = max(60, $intervalMinutes);

        return match ($scenario) {
            // PENDING vigente: bloquea.
            0 => [
                $futureDay->copy()->setTime($hour, 0),
                $futureDay->copy()->setTime($hour, 0)->addMinutes($duration),
                ReservationStatus::PENDING,
                now()->addMinutes(fake()->numberBetween(5, 15)),
                null,
            ],

            // PENDING vencida: todavía pending para probar el Job.
            1 => [
                $futureDay->copy()->setTime($hour, 0),
                $futureDay->copy()->setTime($hour, 0)->addMinutes($duration),
                ReservationStatus::PENDING,
                now()->subMinutes(fake()->numberBetween(1, 30)),
                null,
            ],

            // CONFIRMED futura.
            2 => [
                $futureDay->copy()->setTime($hour, 0),
                $futureDay->copy()->setTime($hour, 0)->addMinutes($duration),
                ReservationStatus::CONFIRMED,
                null,
                null,
            ],

            // CANCELLED futura.
            3 => [
                $futureDay->copy()->setTime($hour, 0),
                $futureDay->copy()->setTime($hour, 0)->addMinutes($duration),
                ReservationStatus::CANCELLED,
                null,
                now()->subHours(fake()->numberBetween(1, 72)),
            ],

            // COMPLETED pasada.
            4 => [
                $pastDay->copy()->setTime($hour, 0),
                $pastDay->copy()->setTime($hour, 0)->addMinutes($duration),
                ReservationStatus::COMPLETED,
                null,
                null,
            ],

            // EXPIRED explícita.
            5 => [
                $futureDay->copy()->setTime($hour, 0),
                $futureDay->copy()->setTime($hour, 0)->addMinutes($duration),
                ReservationStatus::EXPIRED,
                now()->subMinutes(fake()->numberBetween(5, 60)),
                null,
            ],

            // Otra confirmada para reforzar disponibilidad futura.
            default => [
                $futureDay->copy()->setTime($hour, 0),
                $futureDay->copy()->setTime($hour, 0)->addMinutes($duration),
                ReservationStatus::CONFIRMED,
                null,
                null,
            ],
        };
    }

    /**
     * Resuelve un precio demo coherente con las reglas creadas.
     *
     * Para no duplicar toda la lógica de PriceResolver, elegimos una única
     * regla que cubra completamente el inicio de la reserva. El snapshot queda
     * consistente y las pruebas detalladas de segmentación siguen siendo
     * responsabilidad de PriceResolver.
     *
     * @return array{0: float, 1: ?CourtPriceRule}
     */
    private function resolverPrecioDemo(CourtPrice $courtPrice, Carbon $startsAt): array
    {
        $rules = CourtPriceRule::query()
            ->where('court_price_id', $courtPrice->id)
            ->where('active', true)
            ->orderByDesc('priority')
            ->get();

        /** @var CourtPriceRule|null $rule */
        $rule = $rules->first(function (CourtPriceRule $rule) use ($startsAt): bool {
            if ($rule->starts_at !== null && $startsAt->lt($rule->starts_at)) {
                return false;
            }

            if ($rule->ends_at !== null && $startsAt->gte($rule->ends_at)) {
                return false;
            }

            if (
                $rule->specific_date !== null
                && $startsAt->toDateString() !== $rule->specific_date->toDateString()
            ) {
                return false;
            }

            if (
                $rule->day_of_week !== null
                && $startsAt->dayOfWeekIso !== (int) $rule->day_of_week
            ) {
                return false;
            }

            $time = $startsAt->format('H:i:s');

            if ($rule->start_time !== null && $time < $rule->start_time) {
                return false;
            }

            if ($rule->end_time !== null && $time >= $rule->end_time) {
                return false;
            }

            return true;
        });

        if ($rule !== null) {
            return [(float) $rule->price, $rule];
        }

        return [(float) $courtPrice->price, null];
    }

    private function buscarStaffParaCourt(Court $court): ?User
    {
        $membership = Membership::query()
            ->where('club_id', $court->branch->club_id)
            ->where('active', true)
            ->whereIn('rol_id', [2, 3, 4])
            ->where(function ($query) use ($court) {
                $query
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $court->branch_id);
            })
            ->inRandomOrder()
            ->first();

        return $membership?->user_id
            ? User::find($membership->user_id)
            : null;
    }
}
