<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrimerAdministradorTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_account_becomes_the_first_admin_and_the_decision_is_recorded(): void
    {
        $usuario = User::factory()->create(['correo' => 'primera@subastaya.test']);

        $this->artisan('subastas:primer-admin', ['correo' => ' Primera@SubastaYA.test '])->assertExitCode(0);

        $this->assertTrue($usuario->esAdministrador());
        $this->assertDatabaseHas('decision_usuario', ['id_actor' => $usuario->id_usuario, 'id_usuario' => $usuario->id_usuario,
            'operacion' => 'administrador', 'valor_anterior' => false, 'valor_nuevo' => true]);
        $this->actingAs($usuario)->get('/admin/usuarios')->assertOk();
    }

    public function test_command_refuses_when_an_active_admin_already_exists(): void
    {
        $primera = User::factory()->create();
        $segunda = User::factory()->create();
        $this->artisan('subastas:primer-admin', ['correo' => $primera->correo])->assertExitCode(0);

        $this->artisan('subastas:primer-admin', ['correo' => $segunda->correo])->assertExitCode(1);

        $this->assertFalse($segunda->esAdministrador());
        $this->assertDatabaseCount('decision_usuario', 1);
    }

    public function test_unknown_or_inactive_accounts_are_refused(): void
    {
        $inactiva = User::factory()->create();
        DB::table('usuario')->where('id_usuario', $inactiva->id_usuario)->update(['activo' => false]);

        $this->artisan('subastas:primer-admin', ['correo' => 'nadie@subastaya.test'])->assertExitCode(1);
        $this->artisan('subastas:primer-admin', ['correo' => $inactiva->correo])->assertExitCode(1);

        $this->assertFalse($inactiva->esAdministrador());
        $this->assertDatabaseCount('decision_usuario', 0);
    }
}
