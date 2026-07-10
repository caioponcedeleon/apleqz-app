<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\JobAlertsTier;
use App\Services\TranslationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@apleqz.test'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'job_alerts_tier' => JobAlertsTier::Ai->value,
                'locale' => 'en',
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'demo@apleqz.test'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'is_admin' => false,
                'locale' => 'en',
                'email_verified_at' => now(),
            ]
        );

        $translations = app(TranslationService::class);
        $translations->seedFromFiles('en');
        $translations->seedFromFiles('pt');
        $translations->seedFromFiles('de');

        $this->command->info('Admin: admin@apleqz.test / password');
        $this->command->info('Demo:  demo@apleqz.test / password');
    }
}
