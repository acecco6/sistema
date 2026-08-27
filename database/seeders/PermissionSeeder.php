<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

final class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'club.view', 'description' => 'Ver clubes y su información'],
            ['name' => 'club.create', 'description' => 'Crear clubes'],
            ['name' => 'club.update', 'description' => 'Modificar clubes'],
            ['name' => 'club.deactivate', 'description' => 'Desactivar clubes'],

            ['name' => 'branch.view', 'description' => 'Ver sucursales y su información'],
            ['name' => 'branch.create', 'description' => 'Crear sucursales'],
            ['name' => 'branch.update', 'description' => 'Modificar sucursales'],
            ['name' => 'branch.deactivate', 'description' => 'Desactivar sucursales'],

            ['name' => 'membership.create', 'description' => 'Agregar usuarios a un club o sucursal'],
            ['name' => 'membership.change_status', 'description' => 'Activar o desactivar membresías'],
            ['name' => 'membership.change_role', 'description' => 'Cambiar el rol de una membresía'],
            ['name' => 'membership.change_branch', 'description' => 'Cambiar el alcance de sucursal de una membresía'],

            ['name' => 'court.view', 'description' => 'Ver una cancha específica'],
            ['name' => 'court.create', 'description' => 'Crear canchas'],
            ['name' => 'court.update', 'description' => 'Modificar canchas'],
            ['name' => 'court.deactivate', 'description' => 'Desactivar canchas'],

            ['name' => 'court_price.view', 'description' => 'Ver precios de canchas'],
            ['name' => 'court_price.create', 'description' => 'Crear precios de canchas'],
            ['name' => 'court_price.update', 'description' => 'Modificar precios de canchas'],
            ['name' => 'court_price.change_status', 'description' => 'Activar o desactivar precios de canchas'],

            ['name' => 'court_promotion.view', 'description' => 'Ver promociones de canchas'],
            ['name' => 'court_promotion.create', 'description' => 'Crear promociones de canchas'],
            ['name' => 'court_promotion.update', 'description' => 'Modificar promociones de canchas'],
            ['name' => 'court_promotion.change_status', 'description' => 'Activar o desactivar promociones de canchas'],

            ['name' => 'reservation.create', 'description' => 'Crear reservas'],
            ['name' => 'reservation.view', 'description' => 'Ver reservas'],
            ['name' => 'reservation.cancel', 'description' => 'Cancelar reservas'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                ['description' => $permission['description']],
            );
        }
    }
}
