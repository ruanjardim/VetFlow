<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if ($this->shouldSeedDemoUser()) {
            User::query()->firstOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'password' => Hash::make('password'),
                    'active' => true,
                ]
            );
        }

        $this->call(AuthorizationSeeder::class);
    }

    private function shouldSeedDemoUser(): bool
    {
        if (! app()->environment(['local', 'testing'])) {
            return false;
        }

        return filter_var(
            env('VETFLOW_SEED_DEMO_USER', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
