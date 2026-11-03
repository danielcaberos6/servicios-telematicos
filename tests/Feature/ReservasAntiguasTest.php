<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\FondosService;
use App\Services\PujaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservasAntiguasTest extends TestCase
{
    use RefreshDatabase;

    private function antigua(User $lider)
    {
        $s = User::factory()->create()->subastas()->forceCreate([
            'titulo' => 'Subasta anterior', 'descripcion' => 'Artículo de prueba.', 'ubicacion' => 'La Paz',
            'estado_articulo' => 'Usado', 'monto_inicial' => 100, 'modelo_puja' => 1,
            'id_categoria' => Categoria::firstOrFail()->id_categoria, 'estado_revision' => 'Aceptada',
            'fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->addDays(2),
        ]);
        app(PujaService::class)->registrar($lider->id_usuario, $s->id_subasta, '500');
        $s->forceFill(['activar_fondos_al_pujar' => true])->save();

        return $s;
    }

    public function test_next_bid_reserves_full_amount_then_only_difference_and_releases_when_outbid(): void
    {
        $uno = User::factory()->conFondos(2000)->create();
        $dos = User::factory()->conFondos(2000)->create();
        $s = $this->antigua($uno);
        $f = app(FondosService::class);
        $this->assertEquals(0, $f->saldo($uno->id_usuario)->reservado);
        $url = '/subastas/'.$s->id_subasta.'/pujas';
        $this->actingAs($uno)->post($url, ['monto' => 505])->assertSessionHasNoErrors();
        $this->assertEquals(505, $f->saldo($uno->id_usuario)->reservado);
        $this->assertEquals(1495, $f->saldo($uno->id_usuario)->disponible);
        $this->assertTrue((bool) $s->fresh()->requiere_fondos);
        $this->post($url, ['monto' => 510])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('movimiento_fondos', ['id_usuario' => $uno->id_usuario, 'tipo' => 'Reserva', 'importe' => 5]);
        $this->actingAs($dos)->post($url, ['monto' => 520])->assertSessionHasNoErrors();
        $this->assertEquals(0, $f->saldo($uno->id_usuario)->reservado);
        $this->assertEquals(2000, $f->saldo($uno->id_usuario)->disponible);
        $this->assertEquals(520, $f->saldo($dos->id_usuario)->reservado);
        $s->refresh()->forceFill(['estado_subasta' => 'Finalizada'])->save();
        $this->assertDatabaseHas('reserva_fondos', ['id_subasta' => $s->id_subasta, 'estado' => 'Retenida']);
        DB::select('select crear_operacion_cierre(?)', [$s->id_subasta]);
        $this->assertDatabaseHas('operacion_subasta', ['id_subasta' => $s->id_subasta, 'con_fondos' => true]);
    }

    public function test_insufficient_funds_preserve_previous_bid_and_do_not_activate_funding(): void
    {
        $u = User::factory()->create();
        $s = $this->antigua($u);
        $this->actingAs($u)->post('/subastas/'.$s->id_subasta.'/pujas', ['monto' => 505])->assertSessionHasErrors('monto');
        $this->assertFalse((bool) $s->fresh()->requiere_fondos);
        $this->assertSame(1, $s->pujas()->count());
        $this->assertDatabaseCount('reserva_fondos', 0);
        $this->assertDatabaseCount('movimiento_fondos', 0);
    }

    public function test_header_balance_is_only_visible_to_normal_accounts(): void
    {
        $this->withoutExceptionHandling();
        $this->get('/')->assertDontSee('data-saldo-cabecera', false);
        $u = User::factory()->conFondos(2000)->create();
        $this->actingAs($u)->get('/')->assertSee('data-saldo-cabecera', false)->assertSee('2.000,00');
        DB::table('usuario_rol')->insert(['id_usuario' => $u->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);
        $this->actingAs($u->fresh())->get('/admin/usuarios')->assertOk()->assertDontSee('data-saldo-cabecera', false);
    }
}
