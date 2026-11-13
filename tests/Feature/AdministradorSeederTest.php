<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdministradorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministradorSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (['ADMIN_CORREO', 'ADMIN_CONTRASENA'] as $variable) {
            unset($_SERVER[$variable], $_ENV[$variable]);
            putenv($variable);
        }
        parent::tearDown();
    }

    private function definirVariable(string $nombre, string $valor): void
    {
        $_SERVER[$nombre] = $_ENV[$nombre] = $valor;
        putenv("{$nombre}={$valor}");
    }

    private function sembrarEnProduccion(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $this->app['env'] = 'testing';
    }

    public function test_production_seed_creates_the_default_administrator_who_can_log_in(): void
    {
        $this->sembrarEnProduccion();

        $admin = User::where('correo', 'admin@subastaya.test')->firstOrFail();
        $this->assertTrue($admin->esAdministrador());
        $this->assertDatabaseCount('usuario', 1);
        $this->post('/iniciar-sesion', ['correo' => 'admin@subastaya.test', 'password' => 'Admin2026!'])
            ->assertRedirect('/admin/usuarios');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_environment_variables_replace_the_default_credentials(): void
    {
        $this->definirVariable('ADMIN_CORREO', 'Jefa@Empresa.com');
        $this->definirVariable('ADMIN_CONTRASENA', 'ClavePropia2026');

        $this->sembrarEnProduccion();

        $admin = User::where('correo', 'jefa@empresa.com')->firstOrFail();
        $this->assertTrue($admin->esAdministrador());
        $this->assertTrue(Hash::check('ClavePropia2026', $admin->contrasena));
        $this->assertDatabaseMissing('usuario', ['correo' => 'admin@subastaya.test']);
    }

    public function test_reseeding_keeps_a_changed_password_and_administrative_decisions(): void
    {
        $this->sembrarEnProduccion();
        $admin = User::where('correo', 'admin@subastaya.test')->firstOrFail();
        $admin->forceFill(['contrasena' => 'NuevaClave2026', 'activo' => false])->save();
        $antes = $admin->fresh()->getAttributes();

        $this->sembrarEnProduccion();

        $this->assertSame($antes, $admin->fresh()->getAttributes());
        $this->assertDatabaseCount('usuario', 1);
    }

    public function test_local_and_testing_leave_the_administrator_to_the_demo_accounts(): void
    {
        $this->seed(AdministradorSeeder::class);

        $this->assertDatabaseCount('usuario', 0);
    }
}
