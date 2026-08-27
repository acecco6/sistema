<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Club;
use App\Models\Court;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Models\TipoCourt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

final class AlejoDemoSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Usuario principal
        |--------------------------------------------------------------------------
        */

        $alejo = User::updateOrCreate(
            [
                'email' => 'acecco6@gmail.com',
            ],
            [
                'name' => 'Alejo Cecco',
                'password' => Hash::make('hola1234'),
                'active' => true,
            ]
        );

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
        | CLUB 1
        |
        | Alejo es ADMIN GLOBAL.
        | Puede acceder a todas las branches.
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

        $this->createCourtsForBranches($branchesGlobal);

        Membership::create([
            'user_id' => $alejo->id,
            'club_id' => $clubGlobal->id,
            'rol_id' => $admin->id,
            'branch_id' => null,
            'active' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | CLUB 2
        |
        | Alejo solamente tiene acceso a algunas branches.
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

        $this->createCourtsForBranches($branchesParciales);

        /*
         * Branch 0 → Manager
         */
        Membership::create([
            'user_id' => $alejo->id,
            'club_id' => $clubParcial->id,
            'rol_id' => $manager->id,
            'branch_id' => $branchesParciales[0]->id,
            'active' => true,
        ]);

        /*
         * Branch 2 → Employee
         */
        Membership::create([
            'user_id' => $alejo->id,
            'club_id' => $clubParcial->id,
            'rol_id' => $employee->id,
            'branch_id' => $branchesParciales[2]->id,
            'active' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | CLUB 3
        |
        | Membership específica con ROLE 2 (Admin).
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

        $this->createCourtsForBranches($branchesAdmin);

        Membership::create([
            'user_id' => $alejo->id,
            'club_id' => $clubAdminParcial->id,
            'rol_id' => $admin->id,
            'branch_id' => $branchesAdmin[1]->id,
            'active' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | CLUB 4
        |
        | Membership inactiva para poder probar autorización.
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

        $this->createCourtsForBranches($branchesInactivas);

        $branchInactiva = $branchesInactivas->first();

        Membership::create([
            'user_id' => $alejo->id,
            'club_id' => $clubInactivo->id,
            'rol_id' => $manager->id,
            'branch_id' => $branchInactiva->id,
            'active' => false,
        ]);
    }

    private function createCourtsForBranches($branches): void
    {
        $tiposCourt = TipoCourt::query()->get();

        if ($tiposCourt->isEmpty()) {
            throw new \RuntimeException(
                'No existen tipos de cancha. Ejecutá TipoCourtSeeder antes de AlejoDemoSeeder.'
            );
        }

        foreach ($branches as $branch) {

            $cantidadCourts = fake()->numberBetween(3, 6);

            for ($index = 1; $index <= $cantidadCourts; $index++) {

                $tipoCourt = $tiposCourt->random();

                /*
             * Configuración del intervalo para
             * Branch + TipoCourt.
             *
             * Si ya existe, NO crea otro.
             */
                DB::table('interval_time_tipo_court')
                    ->updateOrInsert(
                        [
                            'branch_id' => $branch->id,
                            'tipo_court_id' => $tipoCourt->id,
                        ],
                        [
                            'interval_minutes' => $this->intervalForTipoCourt(
                                $tipoCourt->name
                            ),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                /*
             * Court
             */
                Court::factory()->create([
                    'branch_id' => $branch->id,
                    'tipo_court_id' => $tipoCourt->id,
                    'name' => "{$tipoCourt->name} {$index}",
                    'active' => true,
                ]);
            }
        }
    }

    private function intervalForTipoCourt(string $tipo): int
    {
        $opciones = [
            30,
            60,
        ];

        return match ($tipo) {
            'Padel' => $opciones[array_rand($opciones)],
            'Tenis' => $opciones[array_rand($opciones)],
            'Fútbol 5' => $opciones[array_rand($opciones)],
            'Fútbol 7' => $opciones[array_rand($opciones)],
            default => $opciones[array_rand($opciones)],
        };
    }
}
