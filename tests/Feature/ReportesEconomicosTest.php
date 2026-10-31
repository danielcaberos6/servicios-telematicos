<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\SubastaService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportesEconomicosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create();
        DB::table('usuario_rol')->insert(['id_usuario' => $u->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);

        return $u;
    }

    private function orden(string $estado = 'Pendiente', bool $fondos = true, string $cierre = '2025-06-15 12:00:00-04', bool $aumentar = false): array
    {
        $c = User::factory()->conFondos(1000)->create();
        $v = User::factory()->create();
        $s = app(SubastaService::class)->crear($v->id_usuario, [
            'titulo' => 'Artículo para reporte económico', 'descripcion' => 'Descripción completa para comprobar los indicadores económicos.',
            'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => 500, 'incremento_puja' => 10,
            'id_categoria' => Categoria::firstOrFail()->id_categoria, 'fecha_fin' => now()->addDays(2)->toIso8601String(),
        ]);
        if (! $fondos) {
            DB::statement('alter table subasta disable trigger user');
            try {
                DB::table('subasta')->where('id_subasta', $s->id_subasta)->update(['requiere_fondos' => false]);
            } finally {
                DB::statement('alter table subasta enable trigger user');
            }
        }
        $s->forceFill(['estado_revision' => 'Aceptada'])->save();
        DB::select('select registrar_puja(?,?,?::numeric)', [$c->id_usuario, $s->id_subasta, 500]);
        if ($aumentar) {
            foreach ([510, 520] as $monto) {
                DB::select('select registrar_puja(?,?,?::numeric)', [$c->id_usuario, $s->id_subasta, $monto]);
            }
        }
        // Se prepara el cierre histórico únicamente dentro de subastaya_test.
        DB::statement('alter table subasta disable trigger user');
        try {
            DB::table('subasta')->where('id_subasta', $s->id_subasta)->update(['fecha_inicio' => Carbon::parse($cierre)->subDay(), 'fecha_fin' => $cierre]);
        } finally {
            DB::statement('alter table subasta enable trigger user');
        }
        $s->refresh()->forceFill(['estado_subasta' => $estado === 'Cancelada' ? 'Cancelada' : 'Finalizada'])->save();
        if ($estado === 'Cancelada') {
            return [null, $c, $v, $s];
        }
        $id = DB::selectOne('select crear_operacion_cierre(?) as id', [$s->id_subasta])->id;
        if ($estado === 'Completada') {
            foreach ([$c, $v] as $u) {
                DB::select('select confirmar_entrega(?,?)', [$u->id_usuario, $id]);
            }
        }
        if (in_array($estado, ['En revisión', 'Reembolsada'])) {
            $caso = DB::selectOne('select abrir_incidencia(?,?,?) as id', [$c->id_usuario, $id, 'El vendedor no asistió al encuentro.'])->id;
            if ($estado === 'Reembolsada') {
                $a = $this->admin();
                DB::select('select asignar_incidencia(?,?)', [$a->id_usuario, $caso]);
                DB::select('select resolver_incidencia(?,?,?,?)', [$a->id_usuario, $caso, 'Devolver', 'Se verificó que no hubo entrega.']);
            }
        }

        return [$id, $c, $v, $s];
    }

    private function filtros(array $extra = []): string
    {
        return http_build_query(array_merge(['desde' => '2025-06-01', 'hasta' => '2025-06-30'], $extra));
    }

    public function test_sales_distinguish_reserved_paid_refunded_and_effective_commissions_without_legacy_orders(): void
    {
        foreach (['Completada', 'Pendiente', 'En revisión', 'Reembolsada'] as $estado) {
            $this->orden($estado);
        }
        $this->orden('Completada', false);
        $this->orden('Cancelada');
        $this->actingAs($this->admin())->getJson('/reportes/ventas?'.$this->filtros())->assertOk()
            ->assertJsonPath('total', 4)->assertJsonPath('indicadores.retenido', 1000)
            ->assertJsonPath('indicadores.neto_liquidado', 475)->assertJsonPath('indicadores.comisiones', 25)
            ->assertJsonPath('indicadores.total_liquidado', 500)->assertJsonPath('indicadores.devuelto', 500)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/reportes/ventas?'.$this->filtros(['estado' => 'Pendiente']))->assertJsonPath('total', 1)->assertJsonPath('indicadores.comisiones', 0)->assertJsonPath('indicadores.retenido', 500);
        $this->get('/reportes/ventas?'.$this->filtros())->assertOk()->assertSee('Comisión prevista')->assertSee('Comisión registrada')->assertSee('demostración');
    }

    public function test_many_bids_do_not_multiply_sales_and_total_paid_equals_net_plus_commission(): void
    {
        [$id] = $this->orden('Completada', true, '2025-06-15 12:00:00-04', true);
        $json = $this->actingAs($this->admin())->getJson('/reportes/ventas?'.$this->filtros())->assertOk()->assertJsonPath('total', 1)->assertJsonCount(1, 'detalle')->json();
        $this->assertEquals(520, $json['indicadores']['total_liquidado']);
        $this->assertEquals(494, $json['indicadores']['neto_liquidado']);
        $this->assertEquals(26, $json['indicadores']['comisiones']);
        $this->assertEquals($json['indicadores']['total_liquidado'], $json['indicadores']['neto_liquidado'] + $json['indicadores']['comisiones']);
        $this->assertSame(3, DB::table('puja')->count());
    }

    public function test_period_uses_bolivia_closing_date_including_last_day_not_creation_date(): void
    {
        $this->orden('Pendiente', true, '2025-06-01 00:00:00-04');
        $this->orden('Pendiente', true, '2025-06-30 23:59:59.999999-04');
        $this->orden('Pendiente', true, '2025-07-01 00:00:00-04');
        $this->actingAs($this->admin())->getJson('/reportes/ventas?'.$this->filtros())->assertOk()->assertJsonPath('total', 2)->assertJsonPath('indicadores.retenido', 1000);
        $this->getJson('/reportes/ventas?'.$this->filtros(['desde' => '2025-07-01', 'hasta' => '2025-07-01']))->assertJsonPath('total', 1);
    }

    public function test_pagination_preserves_filters_and_aggregates_cover_all_filtered_rows(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->orden();
        }
        $this->actingAs($this->admin());
        $url = '/reportes/ventas?'.$this->filtros(['estado' => 'Pendiente', 'q' => 'Artículo']);
        $one = $this->getJson($url)->assertOk()->assertJsonCount(15, 'detalle')->assertJsonPath('total', 16)->assertJsonPath('indicadores.retenido', 8000)->json();
        $two = $this->getJson($url.'&page=2')->assertOk()->assertJsonCount(1, 'detalle')->assertJsonPath('indicadores.retenido', 8000)->json();
        $this->assertSame($one['indicadores'], $two['indicadores']);
        $this->assertStringContainsString('estado=Pendiente', $one['tabla']['next_page_url']);
        $this->assertEmpty(array_intersect(array_column($one['detalle'], 'id_operacion'), array_column($two['detalle'], 'id_operacion')));
    }

    public function test_movement_filters_detail_and_links_trace_actual_ledger_without_private_chat_or_contact_data(): void
    {
        [$id,$c,$v,$s] = $this->orden('Completada');
        $this->actingAs($this->admin());
        $params = ['desde' => '1900-01-01', 'hasta' => '2100-12-31', 'operacion' => $id];
        $this->getJson('/reportes/movimientos?'.http_build_query($params))->assertOk()->assertJsonPath('total', 2)->assertJsonPath('indicadores.pagado', 500);
        $this->getJson('/reportes/movimientos?'.http_build_query($params + ['tipo_movimiento' => 'Liquidación', 'usuario' => $v->id_usuario]))->assertJsonPath('total', 1)->assertJsonPath('detalle.0.importe', 475);
        foreach (DB::table('movimiento_fondos')->get() as $m) {
            $resp = $this->getJson('/reportes/movimientos/'.$m->id_movimiento)->assertOk()->assertJsonPath('referencia', $m->referencia)->assertJsonPath('estado', 'Registrado')->assertHeader('Cache-Control', 'no-store, private');
            $this->assertArrayNotHasKey('correo', $resp->json());
            $this->assertArrayNotHasKey('telefono', $resp->json());
            $this->assertArrayNotHasKey('mensajes', $resp->json());
            if ($m->tipo === 'Recarga') {
                $resp->assertJsonPath('estado_recarga', 'Aprobada');
            } else {
                $resp->assertJsonPath('estado_operacion', 'Completada');
            }
        }
        $pago = DB::table('movimiento_fondos')->where('tipo', 'Pago')->first();
        $this->get('/reportes/movimientos/'.$pago->id_movimiento)->assertOk()->assertSee('Registrado')->assertSee('Completada')->assertSee('Consumida');
        $this->getJson('/reportes/movimientos?'.http_build_query(['desde' => '1900-01-01', 'hasta' => '2100-12-31', 'subasta' => $s->id_subasta]))->assertJsonPath('total', 3);
        $this->getJson('/reportes/movimientos?'.http_build_query(['desde' => '1900-01-01', 'hasta' => '2100-12-31', 'q' => '%']))->assertJsonPath('total', 0);
    }

    public function test_refund_and_cancellation_movements_keep_their_references_and_do_not_charge_commission(): void
    {
        [$id] = $this->orden('Reembolsada');
        $this->orden('Cancelada');
        $this->actingAs($this->admin());
        foreach (['Devolución' => 'Reembolsada', 'Liberación' => null] as $tipo => $estado) {
            $m = DB::table('movimiento_fondos')->where('tipo', $tipo)->first();
            $this->getJson('/reportes/movimientos/'.$m->id_movimiento)->assertOk()->assertJsonPath('estado_operacion', $estado)->assertJsonPath('importe', 500)->assertJsonPath('estado_reserva_actual', 'Liberada');
        }
        $this->assertDatabaseCount('comision_operacion', 0);
    }

    public function test_empty_invalid_ranges_and_read_only_permissions(): void
    {
        $actor = User::factory()->create();
        $admin = $this->admin();
        foreach (['ventas', 'movimientos', 'movimientos/1'] as $tipo) {
            $this->get('/reportes/'.$tipo)->assertRedirect('/iniciar-sesion');
            $this->actingAs($actor)->getJson('/reportes/'.$tipo.'?actor='.$admin->id_usuario)->assertForbidden();
            auth()->logout();
        }
        foreach (['reporte_ventas', 'reporte_movimientos'] as $fn) {
            try {
                DB::transaction(fn () => DB::select('select '.$fn.'(?,?::jsonb)', [$actor->id_usuario, json_encode(['desde' => '2025-06-01', 'hasta' => '2025-06-30'])]));
                $this->fail('Debe exigir administrador también en SQL.');
            } catch (QueryException $e) {
                $this->assertSame('42501', $e->errorInfo[0]);
            }
        }
        $this->actingAs($admin)->getJson('/reportes/ventas?'.$this->filtros())->assertOk()->assertJsonPath('indicadores.retenido', 0)->assertJsonCount(0, 'detalle');
        $this->getJson('/reportes/movimientos?'.$this->filtros())->assertJsonPath('indicadores.recargado', 0)->assertJsonCount(0, 'detalle');
        $this->getJson('/reportes/ventas?desde=2025-07-01&hasta=2025-06-01')->assertUnprocessable()->assertJsonValidationErrors('hasta');
        $this->getJson('/reportes/ventas?desde=2025-02-31&hasta=2025-03-01')->assertUnprocessable();
        $this->getJson('/reportes/ventas?estado=Activa')->assertJsonValidationErrors('estado');
        $this->getJson('/reportes/movimientos?tipo_movimiento=Inventado')->assertJsonValidationErrors('tipo_movimiento');
        $this->getJson('/reportes/movimientos/999999')->assertNotFound();
        $this->post('/reportes/ventas')->assertMethodNotAllowed();
        $this->delete('/reportes/movimientos/1')->assertMethodNotAllowed();
    }

    public function test_consulting_reports_does_not_mutate_balances_or_orders(): void
    {
        [$id,$c,$v] = $this->orden('Completada');
        $this->actingAs($this->admin());
        $before = DB::table('movimiento_fondos')->orderBy('id_movimiento')->get()->toJson();
        for ($i = 0; $i < 2; $i++) {
            $this->get('/reportes/ventas?'.$this->filtros())->assertOk();
            $this->get('/reportes/movimientos')->assertOk();
            $this->get('/reportes/movimientos/'.DB::table('movimiento_fondos')->value('id_movimiento'))->assertOk();
        }
        $this->assertSame($before,DB::table('movimiento_fondos')->orderBy('id_movimiento')->get()->toJson());
        $this->assertDatabaseCount('comision_operacion',1);
        $this->assertDatabaseHas('operacion_subasta',['id_operacion' => $id, 'estado' => 'Completada']);
    }
}
