<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CuentasDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CuentasDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_can_log_in_with_their_expected_roles(): void
    {
        $this->app['env'] = 'local';
        $this->seed(CuentasDemoSeeder::class);
        $this->app['env'] = 'testing';

        foreach ([
            ['admin@subastaya.test', 'Admin2026!', true],
            ['demo@subastaya.test', 'SubastaYA2026!', false],
            ['vendedor@subastaya.test', 'SubastaYA2026!', false],
        ] as [$correo, $password, $admin]) {
            $this->post('/iniciar-sesion', compact('correo', 'password'))->assertRedirect('/');
            $user = User::where('correo', $correo)->firstOrFail();
            $this->assertAuthenticatedAs($user);
            $this->assertSame($admin, $user->esAdministrador());
            $this->post('/cerrar-sesion');
        }
        $this->assertDatabaseCount('usuario', 3);
        $this->assertDatabaseCount('subasta', 0);
    }

    public function test_repeated_seeding_preserves_existing_accounts_and_administrative_decisions(): void
    {
        $this->seed(CuentasDemoSeeder::class);
        $admin = User::where('correo', 'admin@subastaya.test')->firstOrFail();
        $admin->forceFill(['nombre' => 'Nombre cambiado', 'contrasena' => 'OtraClave2026!', 'activo' => false])->save();
        DB::table('usuario_rol')->where('id_usuario', $admin->id_usuario)->delete();
        $before = $admin->fresh()->getAttributes();

        $this->seed(CuentasDemoSeeder::class);

        $this->assertSame($before, $admin->fresh()->getAttributes());
        $this->assertFalse($admin->esAdministrador());
        $this->assertDatabaseCount('usuario', 3);
    }

    public function test_production_does_not_create_demo_accounts(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('db:seed', ['--class' => CuentasDemoSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('usuario', 0);
    }
}
