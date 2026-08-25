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
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                ['description' => $permission['description']],
            );
        }
    }
}
