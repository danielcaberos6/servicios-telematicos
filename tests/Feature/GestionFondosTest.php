<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\FondosService;
use App\Services\NormativaService;
use App\Services\SubastaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GestionFondosTest extends TestCase
{
    use RefreshDatabase;

    private function recargar(User $u, int $monto = 500): object
    {
        $f = app(FondosService::class);
        $r = $f->solicitar($u->id_usuario, (string) Str::uuid(), (string) $monto);

        return $f->resolver($u->id_usuario, $r->id_recarga, 'Aprobada');
    }

    private function subasta()
    {
        $s = app(SubastaService::class)->crear(User::factory()->create()->id_usuario, [
            'titulo' => 'Artículo con fondos', 'descripcion' => 'Artículo para comprobar las reservas de fondos.',
            'ubicacion' => 'La Paz, Plaza Abaroa', 'estado_articulo' => 'Usado', 'monto_inicial' => 100,
            'incremento_puja' => 10, 'id_categoria' => Categoria::firstOrFail()->id_categoria, 'fecha_fin' => now()->addDays(2)->toIso8601String(),
        ]);
        $s->forceFill(['estado_revision' => 'Aceptada'])->save();

        return $s->refresh();
    }

    public function test_wallet_starts_empty_and_only_posted_approval_credits_once(): void
    {
        $u = User::factory()->create();
        $f = app(FondosService::class);
        $this->assertEquals(0, $f->saldo($u->id_usuario)->total);
        $id = (string) Str::uuid();
        $this->actingAs($u)->post('/mi-billetera/recargas', ['referencia' => $id, 'monto' => 500])->assertRedirect('/mi-billetera/recargas/'.$id);
        $this->post('/mi-billetera/recargas', ['referencia' => $id, 'monto' => 500])->assertRedirect();
        $this->assertDatabaseCount('recarga', 1);
        $this->get('/mi-billetera/recargas/'.$id.'?resultado=Aprobada')->assertOk();
        $this->assertEquals(0, $f->saldo($u->id_usuario)->total);
        $url = '/mi-billetera/recargas/'.$id;
        $this->post($url, ['resultado' => 'Aprobada', 'monto' => 10000, 'id_usuario' => 999])->assertRedirect();
        $this->post($url, ['resultado' => 'Aprobada'])->assertRedirect();
        $this->post($url, ['resultado' => 'Cancelada'])->assertRedirect();
        $this->assertEquals(500, $f->saldo($u->id_usuario)->disponible);
        $this->assertDatabaseCount('movimiento_fondos', 1);
        $this->assertDatabaseHas('recarga', ['id_recarga' => $id, 'estado' => 'Aprobada']);
        $this->get('/mi-billetera/saldo')->assertOk()->assertJsonPath('disponible', '500.00')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_rejected_cancelled_and_invalid_recharges_do_not_credit(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        foreach ([0, 4, '5.50', 10001, 'texto'] as $monto) {
            $this->post('/mi-billetera/recargas', ['referencia' => (string) Str::uuid(), 'monto' => $monto])->assertSessionHasErrors('monto');
        }
        foreach (['Rechazada', 'Cancelada'] as $estado) {
            $r = app(FondosService::class)->solicitar($u->id_usuario, (string) Str::uuid(), '100');
            $this->post('/mi-billetera/recargas/'.$r->id_recarga, ['resultado' => $estado])->assertRedirect();
            $this->post('/mi-billetera/recargas/'.$r->id_recarga, ['resultado' => 'Aprobada'])->assertRedirect();
        }
        $this->assertEquals(0, app(FondosService::class)->saldo($u->id_usuario)->total);
        $this->assertDatabaseCount('movimiento_fondos', 0);
    }

    public function test_bids_reserve_release_and_only_take_the_leaders_difference(): void
    {
        $uno = User::factory()->create();
        $dos = User::factory()->create();
        $this->recargar($uno);
        $this->recargar($dos);
        $s = $this->subasta();
        $url = '/subastas/'.$s->id_subasta.'/pujas';
        $this->actingAs($uno)->post($url, ['monto' => 100])->assertSessionHasNoErrors();
        $this->assertEquals(400, app(FondosService::class)->saldo($uno->id_usuario)->disponible);
        $this->actingAs($dos)->post($url, ['monto' => 110])->assertSessionHasNoErrors();
        $this->assertEquals(500, app(FondosService::class)->saldo($uno->id_usuario)->disponible);
        $this->assertEquals(0, app(FondosService::class)->saldo($uno->id_usuario)->reservado);
        $this->post($url, ['monto' => 120])->assertSessionHasNoErrors();
        $saldo = app(FondosService::class)->saldo($dos->id_usuario);
        $this->assertEquals(380, $saldo->disponible);
        $this->assertEquals(120, $saldo->reservado);
        $this->assertEquals(500, $saldo->total);
        $this->assertDatabaseHas('movimiento_fondos', ['id_usuario' => $dos->id_usuario, 'tipo' => 'Reserva', 'importe' => 10]);
        $this->assertDatabaseHas('reserva_fondos', ['id_subasta' => $s->id_subasta, 'id_usuario' => $dos->id_usuario, 'monto' => 120]);
    }

    public function test_insufficient_funds_and_tampered_bids_leave_everything_unchanged(): void
    {
        $u = User::factory()->create();
        $otro = User::factory()->create();
        $this->recargar($u, 100);
        $s = $this->subasta();
        $otra = $this->subasta();
        $this->actingAs($u)->post('/subastas/'.$s->id_subasta.'/pujas', ['monto' => 100])->assertSessionHasNoErrors();
        $avisos = DB::table('notificacion')->count();
        $movimientos = DB::table('movimiento_fondos')->count();
        $this->post('/subastas/'.$otra->id_subasta.'/pujas', ['monto' => 100])->assertSessionHasErrors('monto');
        $this->actingAs($otro)->post('/subastas/'.$s->id_subasta.'/pujas', ['monto' => 110])->assertSessionHasErrors('monto');
        $this->assertSame(1, DB::table('puja')->count());
        $this->assertSame($avisos, DB::table('notificacion')->count());
        $this->assertSame($movimientos, DB::table('movimiento_fondos')->count());
        $this->assertEquals(100, app(FondosService::class)->saldo($u->id_usuario)->reservado);
        // Ni siquiera una inserción directa puede saltarse la reserva y el saldo.
        try {
            DB::transaction(fn () => $otra->pujas()->create(['id_usuario' => $otro->id_usuario, 'monto' => 100]));
            $this->fail('Debe exigir fondos también en PostgreSQL.');
        } catch (QueryException $e) {
            $this->assertSame('P0001', $e->errorInfo[0]);
        }
    }

    public function test_closing_retains_funds_and_cancellation_releases_once(): void
    {
        $u = User::factory()->create();
        $this->recargar($u);
        foreach (['Finalizada', 'Cancelada'] as $estado) {
            $s = $this->subasta();
            $this->actingAs($u)->post('/subastas/'.$s->id_subasta.'/pujas', ['monto' => 100])->assertSessionHasNoErrors();
            $s->forceFill(['estado_subasta' => $estado])->save();
            $s->save();
            $this->assertDatabaseHas('reserva_fondos', ['id_subasta' => $s->id_subasta, 'estado' => $estado === 'Finalizada' ? 'Retenida' : 'Liberada']);
        }
        $this->assertEquals(100, app(FondosService::class)->saldo($u->id_usuario)->reservado);
        $this->assertEquals(400, app(FondosService::class)->saldo($u->id_usuario)->disponible);
        $this->assertSame(1, DB::table('movimiento_fondos')->where('tipo', 'Liberación')->count());
    }

    public function test_wallets_are_private_and_admin_can_only_read_control(): void
    {
        $u = User::factory()->create();
        $otro = User::factory()->create();
        $r = $this->recargar($u);
        $this->get('/mi-billetera')->assertRedirect('/iniciar-sesion');
        $this->actingAs($otro)->get('/mi-billetera/recargas/'.$r->id_recarga)->assertNotFound();
        $this->post('/mi-billetera/recargas/'.$r->id_recarga, ['resultado' => 'Aprobada'])->assertNotFound();
        $this->getJson('/admin/fondos/'.$u->id_usuario)->assertForbidden();
        $this->get('/mi-billetera?id_usuario='.$u->id_usuario)->assertOk()->assertDontSee($r->id_recarga);
        $admin = User::factory()->create();
        DB::table('usuario_rol')->insert(['id_usuario' => $admin->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);
        $this->actingAs($admin)->get('/admin/fondos/'.$u->id_usuario)->assertOk()->assertSee($r->id_recarga)->assertDontSee('Continuar recarga');
        $this->post('/mi-billetera/recargas/'.$r->id_recarga, ['resultado' => 'Aprobada'])->assertForbidden();
        $this->get('/admin/fondos?q='.$u->nombre)->assertOk()->assertSee($u->correo);
    }

    public function test_history_paginates_and_posted_movements_are_immutable(): void
    {
        $u = User::factory()->create();
        for ($i = 0; $i < 16; $i++) {
            $this->recargar($u, 5);
        }
        $this->actingAs($u)->get('/mi-billetera?movimientos=2&recargas=2')->assertOk()
            ->assertViewHas('movimientos', fn ($p) => $p->total() === 16 && $p->count() === 1)
            ->assertViewHas('recargas', fn ($p) => $p->total() === 16 && $p->count() === 1);
        $this->assertEquals(80, app(FondosService::class)->saldo($u->id_usuario)->total);
        foreach (['update movimiento_fondos set importe=1000', 'delete from movimiento_fondos'] as $sql) {
            try {
                DB::transaction(fn () => DB::statement($sql));
                $this->fail('El historial no se modifica ni borra.');
            } catch (QueryException $e) {
                $this->assertSame('P0001', $e->errorInfo[0]);
            }
        }
        $this->assertSame(16, DB::table('movimiento_fondos')->count());
    }

    public function test_failed_transaction_rolls_back_bids_balances_and_notices_together(): void
    {
        $u = User::factory()->create();
        $this->recargar($u);
        $s = $this->subasta();
        $antes = DB::table('notificacion')->count();
        try {
            DB::transaction(function () use ($u, $s) {
                DB::select('select registrar_puja(?,?,?::numeric)', [$u->id_usuario, $s->id_subasta, 100]);
                throw new \RuntimeException('Fallo posterior a reservar');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo posterior a reservar', $e->getMessage());
        }
        $this->assertSame(0, DB::table('puja')->count());
        $this->assertSame(0, DB::table('reserva_fondos')->count());
        $this->assertSame($antes, DB::table('notificacion')->count());
        $this->assertEquals(500, app(FondosService::class)->saldo($u->id_usuario)->disponible);
        $this->assertSame(1, DB::table('movimiento_fondos')->count());
    }

    public function test_recarga_requires_current_terms_and_cannot_change_amount_or_owner(): void
    {
        $u = User::factory()->sinAceptarNormas()->create();
        $this->actingAs($u);
        $this->post('/mi-billetera/recargas', ['referencia' => (string) Str::uuid(), 'monto' => 100])->assertRedirectContains('volver=%2Fmi-billetera');
        $this->assertDatabaseCount('recarga', 0);
        app(NormativaService::class)->aceptar($u->id_usuario, app(NormativaService::class)->version());
        $r = app(FondosService::class)->solicitar($u->id_usuario, (string) Str::uuid(), '100');
        $this->post('/mi-billetera/recargas', ['referencia' => $r->id_recarga, 'monto' => 200])->assertSessionHasErrors('monto');
        $otro = User::factory()->create();
        $this->actingAs($otro)->post('/mi-billetera/recargas', ['referencia' => $r->id_recarga, 'monto' => 100])->assertSessionHasErrors('monto');
        $this->assertDatabaseCount('recarga',1);
        $this->assertDatabaseCount('movimiento_fondos',0);
    }
}
