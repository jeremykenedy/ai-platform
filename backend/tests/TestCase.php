<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected bool $seed = false;

    /**
     * Override migrate:fresh to use explicit --path, skipping pgvector vendor migrations.
     * The pgvector migration uses CREATE EXTENSION which fails on SQLite.
     */
    protected function migrateFreshUsing()
    {
        return [
            '--drop-views' => false,
            '--drop-types' => false,
            '--path'       => [database_path('migrations')],
            '--realpath'   => true,
            '--seed'       => false,
        ];
    }

    protected function setUpRoles(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    }
}
