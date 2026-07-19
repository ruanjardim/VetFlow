<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InitialAdminSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_admin_command_creates_admin_user_with_permissions(): void
    {
        $exitCode = Artisan::call('vetflow:admin:create', [
            '--name' => 'Admin VetFlow',
            '--email' => 'admin@example.com',
            '--password' => 'SenhaForte123!',
        ]);

        $this->assertSame(0, $exitCode);

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue($user->active);
        $this->assertTrue($user->hasPermission('financial.manage'));
    }

    public function test_initial_admin_command_rejects_weak_password(): void
    {
        $exitCode = Artisan::call('vetflow:admin:create', [
            '--name' => 'Admin VetFlow',
            '--email' => 'admin@example.com',
            '--password' => 'fraca',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseMissing('users', [
            'email' => 'admin@example.com',
        ]);
    }

    public function test_database_seeder_does_not_create_demo_user_by_default(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('users', [
            'email' => 'test@example.com',
        ]);
        $this->assertDatabaseHas('roles', [
            'slug' => 'administrador',
        ]);
    }
}
