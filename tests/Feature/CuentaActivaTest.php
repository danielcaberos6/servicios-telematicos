<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CuentaActivaTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_accounts_cannot_log_in_and_can_return_after_reactivation(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->refresh()->activo);
        $user->forceFill(['activo' => false])->save();
        $credentials = ['correo' => $user->correo, 'password' => 'password'];

        $this->post('/iniciar-sesion', $credentials)->assertSessionHasErrors('correo');
        $this->assertGuest();
        $user->forceFill(['activo' => true])->save();
        $this->post('/iniciar-sesion', $credentials)->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_deactivation_ends_an_existing_session_before_profile_changes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/perfil')->assertOk();
        $notifications = DB::table('notificacion')->where('id_usuario', $user->id_usuario)->count();
        DB::table('usuario')->where('id_usuario', $user->id_usuario)->update(['activo' => false]);

        $this->put('/perfil', ['nombre' => 'Cambio prohibido', 'correo' => $user->correo])
            ->assertRedirect('/iniciar-sesion')->assertSessionHasErrors('correo');
        $this->assertGuest();
        $this->assertDatabaseHas('usuario', ['id_usuario' => $user->id_usuario, 'nombre' => $user->nombre, 'activo' => false]);
        $this->assertSame($notifications, DB::table('notificacion')->where('id_usuario', $user->id_usuario)->count());
    }

    public function test_inactive_administrators_lose_access_to_reports_in_existing_sessions(): void
    {
        $user = User::factory()->create();
        DB::table('usuario_rol')->insert([
            'id_usuario' => $user->id_usuario,
            'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol'),
        ]);
        $this->actingAs($user)->get('/reportes/subastas')->assertOk();
        DB::table('usuario')->where('id_usuario', $user->id_usuario)->update(['activo' => false]);

        $this->getJson('/reportes/usuarios')->assertForbidden()->assertJsonPath('message', 'Tu cuenta está desactivada. Contacta con un administrador.');
        $this->assertGuest();
    }

    public function test_public_registration_cannot_set_account_state_or_admin_role(): void
    {
        $this->post('/registro', [
            'nombres' => 'Cuenta', 'apellidos' => 'de prueba', 'correo' => 'cuenta@example.test',
            'password' => 'ClaveSegura123', 'password_confirmation' => 'ClaveSegura123',
            'activo' => false, 'rol' => 'Administrador',
            'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol'),
        ])->assertRedirect('/');

        $user = User::where('correo', 'cuenta@example.test')->firstOrFail();
        $this->assertTrue($user->activo);
        $this->assertFalse($user->esAdministrador());
        $this->assertDatabaseHas('usuario_rol', [
            'id_usuario' => $user->id_usuario,
            'id_rol' => DB::table('rol')->where('nombre_rol', 'Usuario')->value('id_rol'),
        ]);
    }
}
