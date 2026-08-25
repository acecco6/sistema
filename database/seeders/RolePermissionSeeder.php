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

            'SuperAdmin' => '*',

            'Admin' => [
                'club.view',
                'club.update',

                'branch.view',
                'branch.create',
                'branch.update',
                'branch.deactivate',

                'membership.create',
                'membership.change_status',
                'membership.change_role',
                'membership.change_branch',
            ],

            'Manager' => [
                'club.view',

                'branch.view',
                'branch.create',
                'branch.update',

                'membership.create',
                'membership.change_status',
                'membership.change_branch',
            ],

            'Employee' => [
                'club.view',
                'branch.view',
            ],
        ];

        foreach ($rolesPermissions as $roleName => $permissions) {

            $role = Role::where('name', $roleName)->firstOrFail();

            if ($permissions === '*') {
                $permissionIds = Permission::pluck('id')->all();
            } else {
                $permissionIds = Permission::whereIn(
                    'name',
                    $permissions
                )->pluck('id')->all();
            }

            $role->permissions()->sync($permissionIds);
        }
    }
}
