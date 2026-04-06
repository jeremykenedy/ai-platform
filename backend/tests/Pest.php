<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class)
    ->beforeEach(function (): void {
        // Skip pgvector vendor migration that fails on SQLite (:memory:)
        Migrator::withoutMigrations(['2022_08_03_000000_create_vector_extension']);

        // Reset permission cache so roles/permissions are fresh each test
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

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

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $userRole->syncPermissions([
            'conversations.create',
            'conversations.delete',
            'models.view',
            'personas.create',
            'personas.manage-own',
            'projects.create',
            'projects.manage-own',
            'training.view',
            'settings.manage-own',
        ]);

        $adminRole = Role::findByName('admin');
        $adminRole->syncPermissions($permissions);

        $superAdminRole = Role::findByName('super-admin');
        $superAdminRole->syncPermissions($permissions);
    })
    ->in('Feature');

uses(TestCase::class)->in('Unit');
