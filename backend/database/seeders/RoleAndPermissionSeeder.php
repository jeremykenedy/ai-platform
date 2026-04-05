<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'conversations.create',
            'conversations.delete',
            'conversations.view-all',
            'models.view',
            'models.manage',
            'personas.create',
            'personas.manage-own',
            'personas.manage-all',
            'projects.create',
            'projects.manage-own',
            'training.view',
            'training.manage',
            'users.manage',
            'admin.access',
            'settings.manage-own',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $userRole = Role::firstOrCreate(['name' => 'user']);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);

        $userPermissions = [
            'conversations.create',
            'conversations.delete',
            'models.view',
            'personas.create',
            'personas.manage-own',
            'projects.create',
            'projects.manage-own',
            'training.view',
            'settings.manage-own',
        ];

        $userRole->syncPermissions($userPermissions);

        $adminPermissions = array_merge($userPermissions, [
            'conversations.view-all',
            'models.manage',
            'personas.manage-all',
            'training.manage',
            'users.manage',
            'admin.access',
        ]);

        $adminRole->syncPermissions($adminPermissions);

        $superAdminRole->givePermissionTo(Permission::all());
    }
}
