<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'SuperAdmin', 'description' => 'Super Administrador'],
            ['name' => 'Admin', 'description' => 'Administrador'],
            ['name' => 'Manager', 'description' => 'Gerente'],
            ['name' => 'Employee', 'description' => 'Empleado'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']],
            );
        }
    }
}
