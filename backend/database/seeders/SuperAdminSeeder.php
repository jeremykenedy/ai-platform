<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('SUPER_ADMIN_NAME');
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (empty($name) || empty($email) || empty($password)) {
            $this->command->warn('SuperAdminSeeder: SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL, and SUPER_ADMIN_PASSWORD must all be set in .env. Skipping.');

            return;
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

        $this->command->info("Super admin seeded: {$user->name} <{$user->email}>");
    }
}
