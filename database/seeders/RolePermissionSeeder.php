<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rolesPermissions = [

            /*
            |--------------------------------------------------------------------------
            | SuperAdmin
            |--------------------------------------------------------------------------
            |
            | Tiene absolutamente todos los permisos existentes.
            |
            */

            'SuperAdmin' => '*',


            /*
            |--------------------------------------------------------------------------
            | Admin
            |--------------------------------------------------------------------------
            |
            | Administra completamente un club:
            |
            | - Club
            | - Sucursales
            | - Membresías
            | - Canchas
            | - Precios
            | - Promociones
            |
            */

            'Admin' => [

                // Club
                'club.view',
                'club.update',

                // Branches
                'branch.view',
                'branch.create',
                'branch.update',
                'branch.deactivate',

                // Memberships
                'membership.create',
                'membership.change_status',
                'membership.change_role',
                'membership.change_branch',

                // Courts
                'court.view',
                'court.create',
                'court.update',
                'court.deactivate',

                // Court Prices
                'court_price.view',
                'court_price.create',
                'court_price.update',
                'court_price.change_status',

                // Court Promotions
                'court_promotion.view',
                'court_promotion.create',
                'court_promotion.update',
                'court_promotion.change_status',

                /*
                |--------------------------------------------------------------------------
                | Reservations
                |--------------------------------------------------------------------------
                | 1. Crear reservación
                | 2. Ver reservaciones
                | 3. Cancelar reservación
                | 4. Confirmar reservación
                */
                'reservation.create',
                'reservation.view',
                'reservation.cancel',
                'reservation.confirm',
            ],


            /*
            |--------------------------------------------------------------------------
            | Manager
            |--------------------------------------------------------------------------
            |
            | Puede administrar operaciones del club/sucursal,
            | pero tiene menos poder que Admin.
            |
            | Puede administrar canchas, precios y promociones,
            | pero NO desactivar configuraciones importantes.
            |
            */

            'Manager' => [

                // Club
                'club.view',

                // Branches
                'branch.view',
                'branch.create',
                'branch.update',

                // Memberships
                'membership.create',
                'membership.change_status',
                'membership.change_branch',

                // Courts
                'court.view',
                'court.create',
                'court.update',

                // Court Prices
                'court_price.view',
                'court_price.create',
                'court_price.update',

                // Court Promotions
                'court_promotion.view',
                'court_promotion.create',
                'court_promotion.update',

                /*
                |--------------------------------------------------------------------------
                | Reservations
                |--------------------------------------------------------------------------
                | 1. Crear reservación
                | 2. Ver reservaciones
                | 3. Cancelar reservación
                | 4. Confirmar reservación
                */
                'reservation.create',
                'reservation.view',
                'reservation.cancel',
                'reservation.confirm',

            ],


            /*
            |--------------------------------------------------------------------------
            | Employee
            |--------------------------------------------------------------------------
            |
            | Rol principalmente operativo/de lectura.
            |
            | Puede consultar información necesaria para trabajar,
            | pero no modifica configuración administrativa.
            |
            */

            'Employee' => [

                // Club
                'club.view',

                // Branch
                'branch.view',

                // Courts
                'court.view',

                // Pricing
                'court_price.view',

                // Promotions
                'court_promotion.view',

                /*
                |--------------------------------------------------------------------------
                | Reservations
                |--------------------------------------------------------------------------
                | 1. Crear reservación
                | 2. Ver reservaciones
                | 3. Cancelar reservación
                | 4. Confirmar reservación
                */
                'reservation.create',
                'reservation.view',
                // 'reservation.cancel',
                // 'reservation.confirm',

            ],
        ];


        /*
        |--------------------------------------------------------------------------
        | Asignación
        |--------------------------------------------------------------------------
        */

        foreach ($rolesPermissions as $roleName => $permissions) {

            $role = Role::where(
                'name',
                $roleName
            )->firstOrFail();


            /*
             * SuperAdmin recibe todos los permisos registrados
             * actualmente en la tabla permissions.
             */
            if ($permissions === '*') {

                $permissionIds = Permission::pluck(
                    'id'
                )->all();
            } else {

                $permissionIds = Permission::whereIn(
                    'name',
                    $permissions
                )->pluck('id')->all();
            }


            /*
             * sync() hace que los permisos del rol queden
             * exactamente iguales a los definidos arriba.
             *
             * También elimina permisos viejos que ya no
             * correspondan al rol.
             */
            $role->permissions()->sync(
                $permissionIds
            );
        }
    }
}
