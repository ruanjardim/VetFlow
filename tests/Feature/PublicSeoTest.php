<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_is_public_and_has_indexable_metadata(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="pt-BR">', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<link rel="canonical" href="https://vetflowsys.com.br/">', false)
            ->assertSee('<title>VetFlow | Sistema para Clínica Veterinária, Pet Shop e Banho e Tosa</title>', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('"@type": "SoftwareApplication"', false)
            ->assertSee('"@type": "Organization"', false)
            ->assertSee('"email": "contato@vetflowsys.com.br"', false)
            ->assertSee('"email": "comercial@vetflowsys.com.br"', false)
            ->assertSee('mailto:comercial@vetflowsys.com.br?subject=Demonstra%C3%A7%C3%A3o%20do%20VetFlow', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('id="recursos"', false)
            ->assertSee('id="para-quem"', false)
            ->assertSee('images/auth-malinois-square.webp', false)
            ->assertSee('images/auth-pintabian-horse-square.png', false)
            ->assertSee('images/auth-beagle-square.png', false)
            ->assertSee('images/auth-gray-cat-square.png', false)
            ->assertSee('images/auth-white-kitten-square.png', false);
    }

    public function test_login_and_reset_screens_have_noindex(): void
    {
        $this->get('/login')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/forgot-password')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/reset-password/test-token')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_dashboard_remains_protected_at_new_path(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));

        $user = User::factory()->create(['active' => true]);
        $permission = Permission::query()->create([
            'name' => 'Visualizar dashboard', 'slug' => 'dashboard.view', 'group' => 'Testes', 'active' => true,
        ]);
        $role = Role::query()->create([
            'name' => 'Acesso ao dashboard', 'slug' => 'seo-test-dashboard', 'active' => true,
        ]);
        $role->permissions()->attach($permission->id);
        DB::table('user_roles')->insert([
            'ulid' => (string) Str::ulid(), 'user_id' => $user->id, 'role_id' => $role->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_robots_and_sitemap_only_advertise_public_home(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));
        $sitemap = file_get_contents(public_path('sitemap.xml'));

        $this->assertStringContainsString('Allow: /', $robots);
        $this->assertStringContainsString('Sitemap: https://vetflowsys.com.br/sitemap.xml', $robots);
        $this->assertStringContainsString('<loc>https://vetflowsys.com.br/</loc>', $sitemap);
        $this->assertSame(1, substr_count($sitemap, '<loc>'));
        $this->assertStringNotContainsString('/login', $sitemap);
        $this->assertStringNotContainsString('/dashboard', $sitemap);
        $this->assertNotFalse(simplexml_load_string($sitemap));
    }
}
