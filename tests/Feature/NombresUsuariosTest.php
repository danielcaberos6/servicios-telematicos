<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NombresUsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_names_and_surnames_and_composes_the_display_name(): void
    {
        $data = ['correo' => 'nombres@example.test', 'password' => 'ClaveSegura123', 'password_confirmation' => 'ClaveSegura123'];
        $this->post('/registro', $data + ['nombre' => 'Sin separación'])->assertSessionHasErrors(['nombres', 'apellidos']);
        $this->post('/registro', $data + ['nombres' => ' Ana María ', 'apellidos' => ' Pérez Soto ', 'nombre' => 'Valor ignorado'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('usuario', ['correo' => $data['correo'], 'nombres' => 'Ana María', 'apellidos' => 'Pérez Soto', 'nombre' => 'Ana María Pérez Soto']);
    }

    public function test_legacy_accounts_keep_their_name_until_the_owner_completes_both_fields(): void
    {
        $user = User::factory()->create(['nombre' => 'Nombre heredado']);
        $this->actingAs($user)->get('/perfil')->assertSee('Nombre heredado');
        $this->put('/perfil', ['nombres' => 'Juan José', 'apellidos' => 'Salvatierra Humaza', 'correo' => $user->correo])->assertSessionHasNoErrors();
        $this->assertSame('Juan José Salvatierra Humaza', $user->refresh()->nombre);
        $this->assertSame('Juan José', $user->nombres);
        $this->put('/perfil', ['nombres' => 'Juan', 'apellidos' => '', 'correo' => $user->correo])->assertSessionHasErrors('apellidos');
    }
}
