<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrearAdministradorTest extends TestCase
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

    public function test_creates_an_administrator_who_can_log_in_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('subastas:crear-admin', ['correo' => ' Jefa@Ejemplo.com '])
            ->expectsQuestion('Contraseña (8 a 72 caracteres)', 'ClaveSegura2026')
            ->expectsQuestion('Repite la contraseña', 'ClaveSegura2026')
            ->expectsOutput('Administrador jefa@ejemplo.com creado. Ya puedes iniciar sesión.')
            ->assertSuccessful();

        $this->app['env'] = 'testing';
        $usuario = User::where('correo', 'jefa@ejemplo.com')->firstOrFail();
        $this->assertTrue($usuario->esAdministrador());
        $this->assertDatabaseCount('usuario', 1);
        $this->post('/iniciar-sesion', ['correo' => 'jefa@ejemplo.com', 'password' => 'ClaveSegura2026'])
            ->assertRedirect('/admin/usuarios');
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_reads_email_and_password_from_environment_without_prompting(): void
    {
        $this->definirVariable('ADMIN_CORREO', 'admin@empresa.com');
        $this->definirVariable('ADMIN_CONTRASENA', 'OtraClave2026');

        $this->artisan('subastas:crear-admin', ['--no-interaction' => true])->assertSuccessful();

        $usuario = User::where('correo', 'admin@empresa.com')->firstOrFail();
        $this->assertTrue($usuario->esAdministrador());
        $this->assertTrue(Hash::check('OtraClave2026', $usuario->contrasena));
    }

    public function test_promotes_and_reactivates_an_existing_account_without_changing_its_password(): void
    {
        $usuario = User::create(['nombre' => 'Ana', 'correo' => 'ana@ejemplo.com', 'contrasena' => 'ClaveDeAna2026']);
        $usuario->forceFill(['activo' => false])->save();
        $this->definirVariable('ADMIN_CONTRASENA', 'NoDebeUsarse2026');

        $this->artisan('subastas:crear-admin', ['correo' => 'ANA@ejemplo.com', '--no-interaction' => true])
            ->expectsOutput('La cuenta ana@ejemplo.com ya existía: tiene el rol Administrador y conserva su contraseña.')
            ->expectsOutput('La cuenta estaba desactivada y se reactivó.')
            ->assertSuccessful();
        // Repetirlo no duplica el rol ni falla.
        $this->artisan('subastas:crear-admin', ['correo' => 'ana@ejemplo.com', '--no-interaction' => true])->assertSuccessful();

        $usuario->refresh();
        $this->assertTrue($usuario->activo);
        $this->assertTrue($usuario->esAdministrador());
        $this->assertTrue(Hash::check('ClaveDeAna2026', $usuario->contrasena));
        $this->assertDatabaseCount('usuario', 1);
        $this->assertSame(1, DB::table('usuario_rol')->join('rol', 'rol.id_rol', '=', 'usuario_rol.id_rol')
            ->where('id_usuario', $usuario->id_usuario)->where('nombre_rol', 'Administrador')->count());
    }

    public function test_rejects_invalid_data_without_creating_accounts(): void
    {
        $this->artisan('subastas:crear-admin', ['correo' => 'no-es-correo', '--no-interaction' => true])->assertFailed();

        $this->artisan('subastas:crear-admin', ['correo' => 'admin@empresa.com', '--no-interaction' => true])
            ->expectsOutput('Define ADMIN_CONTRASENA o ejecuta el comando en una terminal interactiva.')
            ->assertFailed();

        $this->artisan('subastas:crear-admin', ['correo' => 'admin@empresa.com'])
            ->expectsQuestion('Contraseña (8 a 72 caracteres)', 'ClaveSegura2026')
            ->expectsQuestion('Repite la contraseña', 'OtraDistinta2026')
            ->expectsOutput('Las contraseñas no coinciden.')
            ->assertFailed();

        $this->definirVariable('ADMIN_CONTRASENA', 'corta');
        $this->artisan('subastas:crear-admin', ['correo' => 'admin@empresa.com', '--no-interaction' => true])->assertFailed();

        $this->assertDatabaseCount('usuario', 0);
    }
}
