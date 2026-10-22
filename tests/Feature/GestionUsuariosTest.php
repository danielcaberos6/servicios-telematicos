<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        DB::table('usuario_rol')->insert([
            'id_usuario' => $user->id_usuario,
            'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol'),
        ]);

        return $user;
    }

    public function test_only_active_administrators_can_list_accounts(): void
    {
        $this->get('/admin/usuarios')->assertRedirect('/iniciar-sesion');
        $ordinary = User::factory()->create();
        $this->actingAs($ordinary)->get('/admin/usuarios')->assertRedirect('/');
        $this->getJson('/admin/usuarios')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/usuarios')->assertOk()->assertSee('Gestión de usuarios');
    }

    public function test_search_combines_name_or_email_with_state_and_preserves_pagination(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 17; $i++) {
            User::factory()->create(['nombre' => sprintf('Persona %02d', $i), 'correo' => 'persona'.$i.'@example.test']);
        }
        User::factory()->create(['nombre' => 'Persona desactivada'])->forceFill(['activo' => false])->save();

        $this->actingAs($admin)->getJson('/admin/usuarios?q=PERSONA&estado=activo&page=2')
            ->assertOk()->assertJsonPath('total', 17)->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nombre', 'Persona 15');
        $this->get('/admin/usuarios?q=PERSONA&estado=activo')->assertOk()
            ->assertSee('Página 1 de 2')->assertSee('q=PERSONA', false)->assertSee('estado=activo', false);
        $this->getJson('/admin/usuarios?q=PERSONA&estado=inactivo')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.activo', false);
        $this->getJson('/admin/usuarios?q=PERSONA16%40EXAMPLE.TEST')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.correo', 'persona16@example.test');
    }

    public function test_list_exposes_only_allowed_fields_and_handles_empty_or_invalid_filters(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->getJson('/admin/usuarios')->assertOk();
        $keys = array_keys($response->json('data.0'));
        sort($keys);
        $this->assertSame(['activo', 'administrador', 'correo', 'fecha_registro', 'id_usuario', 'nombre'], $keys);
        $this->get('/admin/usuarios')->assertDontSee($admin->contrasena);
        $this->get('/admin/usuarios?q=inexistente')->assertSee('No se encontraron usuarios con estos filtros.');
        $this->getJson('/admin/usuarios?q=%25')->assertJsonPath('total', 0);
        $this->getJson('/admin/usuarios?estado=desconocido&page=0')->assertUnprocessable()
            ->assertJsonValidationErrors(['estado', 'page']);
    }

    public function test_database_rejects_ordinary_and_inactive_admin_actors(): void
    {
        $ordinary = User::factory()->create();
        $admin = $this->admin()->forceFill(['activo' => false]);
        $admin->save();
        foreach ([$ordinary, $admin] as $actor) {
            try {
                DB::transaction(fn () => DB::select('select listar_usuarios_gestion(?, ?::jsonb)', [$actor->id_usuario, '{}']));
                $this->fail('La consulta debe exigir un administrador activo.');
            } catch (QueryException $exception) {
                $this->assertSame('42501', $exception->errorInfo[0]);
            }
        }
    }
}
