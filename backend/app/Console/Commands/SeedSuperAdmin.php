<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedSuperAdmin extends Command
{
    protected $signature = 'app:seed-super-admin';

    protected $description = 'Create the super admin user from .env variables';

    public function handle(): int
    {
        $name = config('app.super_admin.name');
        $email = config('app.super_admin.email');
        $password = config('app.super_admin.password');

        if (empty($name) || empty($email) || empty($password)) {
            $this->error('Set SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL, and SUPER_ADMIN_PASSWORD in .env before running this command.');

            return self::FAILURE;
        }

        /** @var string $email */
        /** @var string $name */
        /** @var string $password */
        $existing = User::where('email', $email)->first();

        if ($existing !== null && $existing->hasRole('super-admin')) {
            $this->info("Super admin already exists: {$existing->name} <{$existing->email}>");

            return self::SUCCESS;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'password'          => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $user->assignRole('super-admin');

        UserSetting::firstOrCreate(['user_id' => $user->id]);

        $this->info("Super admin created: {$user->name} <{$user->email}>");

        return self::SUCCESS;
    }
}
