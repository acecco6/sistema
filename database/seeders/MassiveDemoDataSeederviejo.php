<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Club;
use App\Models\Court;
use App\Models\Membership;
use App\Models\Role;
use App\Models\TipoCourt;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class MassiveDemoDataSeederviejo extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Configuración
        |--------------------------------------------------------------------------
        */

        $cantidadUsuarios = 150;
        $cantidadClubes = 20;

        /*
        |--------------------------------------------------------------------------
        | Tipos de cancha
        |--------------------------------------------------------------------------
        */

        $tiposCourt = collect([
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
        ])->map(function (array $data) {
            return TipoCourt::firstOrCreate(
                ['name' => $data['name']],
                ['description' => $data['description']]
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Clubs + Branches + Courts
        |--------------------------------------------------------------------------
        */

        $clubs = collect();

        for ($i = 0; $i < $cantidadClubes; $i++) {
            /** @var Club $club */
            $club = Club::factory()->createOne();

            $clubs->push($club);

            $cantidadBranches = fake()->numberBetween(2, 7);

            $branches = Branch::factory()
                ->count($cantidadBranches)
                ->for($club)
                ->create();

            foreach ($branches as $branch) {

                /*
                 * Configuración de intervalos por tipo de court.
                 */
                foreach ($tiposCourt as $tipoCourt) {
                    DB::table('interval_time_tipo_court')
                        ->updateOrInsert(
                            [
                                'branch_id' => $branch->id,
                                'tipo_court_id' => $tipoCourt->id,
                            ],
                            [
                                'interval_minutes' => fake()->randomElement([
                                    30,
                                    60
                                ]),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                }

                /*
                 * Courts de la branch.
                 */
                $cantidadCourts = fake()->numberBetween(2, 8);

                for ($courtNumber = 1; $courtNumber <= $cantidadCourts; $courtNumber++) {
                    $tipoCourt = $tiposCourt->random();

                    Court::factory()->create([
                        'branch_id' => $branch->id,
                        'tipo_court_id' => $tipoCourt->id,

                        /*
                         * Evitamos nombres duplicados dentro de la misma branch.
                         */
                        'name' => "{$tipoCourt->name} {$courtNumber}",

                        'active' => fake()->boolean(90),
                    ]);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Usuarios
        |--------------------------------------------------------------------------
        */

        $users = User::factory()
            ->count($cantidadUsuarios)
            ->create([
                'active' => true,
            ]);

        /*
        |--------------------------------------------------------------------------
        | Roles disponibles
        |--------------------------------------------------------------------------
        |
        | No asignamos SuperAdmin aleatoriamente.
        |
        | 2 = Admin
        | 3 = Manager
        | 4 = Employee
        |--------------------------------------------------------------------------
        */

        $roles = Role::query()
            ->whereIn('id', [2, 3, 4])
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Memberships
        |--------------------------------------------------------------------------
        */

        foreach ($users as $user) {

            /*
             * Cada usuario pertenece entre 1 y 5 clubs.
             */
            $clubsUsuario = $clubs
                ->shuffle()
                ->take(fake()->numberBetween(1, 5));

            foreach ($clubsUsuario as $club) {

                $branches = Branch::query()
                    ->where('club_id', $club->id)
                    ->get();

                if ($branches->isEmpty()) {
                    continue;
                }

                /*
                 * Aproximadamente 25% tendrán membership global.
                 */
                $membershipGlobal = fake()->boolean(25);

                if ($membershipGlobal) {
                    Membership::create([
                        'user_id' => $user->id,
                        'club_id' => $club->id,
                        'rol_id' => $roles->random()->id,
                        'branch_id' => null,
                        'active' => fake()->boolean(90),
                    ]);

                    /*
                     * IMPORTANTE:
                     * Si es global NO generamos memberships
                     * específicas para este mismo club.
                     */
                    continue;
                }

                /*
                 * Memberships específicas.
                 *
                 * Puede tener acceso a varias branches.
                 */
                $cantidadBranches = fake()->numberBetween(
                    1,
                    min(3, $branches->count())
                );

                $branchesUsuario = $branches
                    ->shuffle()
                    ->take($cantidadBranches);

                foreach ($branchesUsuario as $branch) {
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
}
