<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\FondosService;
use App\Services\OperacionService;
use App\Services\SubastaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GestionOperacionesTest extends TestCase
{
    use RefreshDatabase;

    private function operacion(bool $conFondos = true, bool $vencida = false, bool $cerrar = true): array
    {
        $vendedor = User::factory()->create();
        $comprador = User::factory()->conFondos(1000)->create();
        $s = app(SubastaService::class)->crear($vendedor->id_usuario, [
            'titulo' => 'Cámara para entregar', 'descripcion' => 'Descripción completa del artículo para la entrega.',
            'ubicacion' => 'La Paz, Plaza Abaroa', 'estado_articulo' => 'Usado', 'monto_inicial' => 500,
            'incremento_puja' => 10, 'id_categoria' => Categoria::firstOrFail()->id_categoria, 'fecha_fin' => now()->addDays(2)->toIso8601String(),
        ]);
        // La variante anterior a HU22 se prepara antes de aprobar y ofertar.
        if (! $conFondos || $vencida) {
            DB::statement('alter table subasta disable trigger user');
            try {
                DB::table('subasta')->where('id_subasta', $s->id_subasta)->update(array_filter(['requiere_fondos' => $conFondos, 'fecha_inicio' => $vencida ? now()->subDays(6) : null, 'fecha_fin' => $vencida ? now()->subDays(4) : null], fn ($v) => $v !== null));
            } finally {
                DB::statement('alter table subasta enable trigger user');
            }
        }
        $s->forceFill(['estado_revision' => 'Aceptada'])->save();
        if ($vencida) {
            // Se prepara una puja histórica sin retrotraer el reloj real de PostgreSQL.
            DB::statement('alter table puja disable trigger user');
            try {
                $id = DB::table('puja')->insertGetId(['id_subasta' => $s->id_subasta, 'id_usuario' => $comprador->id_usuario, 'monto' => 500], 'id_puja');
            } finally {
                DB::statement('alter table puja enable trigger user');
            }
            if ($conFondos) {
                DB::select('select reservar_fondos_puja(?,?,?,?)', [$s->id_subasta, $comprador->id_usuario, 500, $id]);
            }
        } else {
            $this->actingAs($comprador)->post('/subastas/'.$s->id_subasta.'/pujas', ['monto' => 500])->assertSessionHasNoErrors();
        }
        if (! $cerrar) {
            return [null, $comprador, $vendedor, $s];
        }
        $s->forceFill(['estado_subasta' => 'Finalizada'])->save();
        $id = app(OperacionService::class)->ejecutarAccion('crear', [$s->id_subasta])[0]->id;

        return [$id, $comprador, $vendedor, $s];
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        DB::table('usuario_rol')->insert(['id_usuario' => $u->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);

        return $u;
    }

    public function test_delivery_snapshot_is_private_and_changes_on_confirmation_without_mutating_funds(): void
    {
        [$id,$c,$v] = $this->operacion();
        $url = '/operaciones/'.$id.'/estado';
        $a = $this->actingAs($c)->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
        $this->getJson($url)->assertJsonPath('firma', $a['firma']);
        $this->accion($v, $id, 'confirmar')->assertSessionHasNoErrors();
        $b = $this->actingAs($c)->getJson($url)->assertOk()->assertJsonPath('estado', 'Pendiente')->json();
        $this->assertNotSame($a['firma'], $b['firma']);
        $this->assertNotNull($b['vendedor_confirmo']);
        $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
        $this->actingAs($this->admin())->getJson($url)->assertForbidden();
        $this->assertDatabaseCount('comision_operacion', 0);
    }

    public function test_operations_filter_buys_and_sales_preserves_state_and_does_not_expose_other_accounts(): void
    {
        [$id,$c,$v] = $this->operacion();
        $this->actingAs($c)->get('/mis-operaciones?rol=compras&estado=Pendiente')->assertOk()->assertViewHas('operaciones', fn ($p) => $p->total() === 1);
        $this->get('/mis-operaciones?rol=ventas')->assertViewHas('operaciones', fn ($p) => $p->total() === 0);
        $this->actingAs($v)->get('/mis-operaciones?rol=ventas')->assertViewHas('operaciones', fn ($p) => $p->total() === 1);
        $this->get('/mis-operaciones?rol=otro')->assertSessionHasErrors('rol');
    }

    public function test_operation_notifications_open_the_exact_owned_operation_or_admin_incident(): void
    {
        [$id,$c,$v,$s] = $this->operacion();
        $n = DB::table('notificacion')->where('id_usuario', $c->id_usuario)->where('titulo', 'Entrega por coordinar')->first();
        $this->actingAs($c)->get('/notificaciones/'.$n->id_notificacion.'/destino')->assertRedirect('/operaciones/'.$id);
        $this->actingAs($v)->get('/notificaciones/'.$n->id_notificacion.'/destino')->assertNotFound();
        $admin = $this->admin();
        $this->accion($c, $id, 'incidencia', ['motivo' => 'No se realizó el encuentro acordado.']);
        $caso = DB::table('incidencia_operacion')->where('id_operacion', $id)->first();
        $aviso = DB::table('notificacion')->where('id_usuario', $admin->id_usuario)->where('titulo', 'Incidencia de entrega')->first();
        $this->actingAs($admin)->get('/notificaciones/'.$aviso->id_notificacion.'/destino')->assertRedirect('/admin/operaciones/incidencias/'.$caso->id_incidencia);
    }

    public function test_auction_detail_does_not_offer_coordination_before_the_scheduler_records_closing(): void
    {
        [$id,$c,$v,$s] = $this->operacion(true, true, false);
        $this->actingAs($c)->get('/subastas/'.$s->id_subasta)->assertOk()->assertSee('Se está procesando el cierre')->assertDontSee('>Coordinar entrega</button>', false);
        DB::select('select finalizar_subastas()');
        $this->get('/subastas/'.$s->id_subasta)->assertOk()->assertSee('>Coordinar entrega</button>', false);
    }

    public function test_full_delivery_flow_coordinates_settles_rates_and_reconciles_the_report(): void
    {
        [$id,$c,$v] = $this->operacion();
        $this->accion($c, $id, 'proponer', ['lugar' => 'Plaza Abaroa, ingreso principal', 'fecha' => now('America/La_Paz')->addDay()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $acuerdo = DB::table('acuerdo_operacion')->where('id_operacion', $id)->first();
        $this->accion($v, $id, 'responder', ['acuerdo' => $acuerdo->id_acuerdo, 'aceptar' => 1])->assertSessionHasNoErrors();
        $this->accion($c, $id, 'mensaje', ['referencia' => (string) Str::uuid(), 'texto' => 'De acuerdo, nos vemos en el lugar.'])->assertSessionHasNoErrors();
        $this->accion($c, $id, 'confirmar');
        $this->accion($v, $id, 'confirmar')->assertSessionHasNoErrors();
        $this->actingAs($c)->getJson('/operaciones/'.$id.'/estado')->assertJsonPath('estado', 'Completada');
        $this->post('/operaciones/'.$id.'/valoracion', ['estrellas' => 5, 'comentario' => 'Entrega puntual.'])->assertSessionHasNoErrors();
        $this->actingAs($v)->post('/operaciones/'.$id.'/valoracion', ['estrellas' => 4])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('resenas_publicadas')->where('id_operacion', $id)->count());
        $this->actingAs($this->admin())->getJson('/reportes/ventas?desde=1900-01-01&hasta=2100-12-31')
            ->assertOk()->assertJsonPath('indicadores.total_liquidado', 500)->assertJsonPath('indicadores.neto_liquidado', 475)->assertJsonPath('indicadores.comisiones', 25);
    }

    public function test_full_incident_flow_refunds_and_excludes_ratings_and_commission(): void
    {
        [$id,$c,$v] = $this->operacion();
        $admin = $this->admin();
        $this->accion($c, $id, 'incidencia', ['motivo' => 'El artículo no coincide con las fotografías.'])->assertSessionHasNoErrors();
        $caso = DB::table('incidencia_operacion')->where('id_operacion', $id)->value('id_incidencia');
        $this->actingAs($admin)->post('/admin/operaciones/incidencias/'.$caso, ['accion' => 'asignar'])->assertSessionHasNoErrors();
        $this->post('/admin/operaciones/incidencias/'.$caso, ['accion' => 'resolver', 'decision' => 'Devolver', 'motivo' => 'Se comprobó la diferencia y corresponde devolución.'])->assertSessionHasNoErrors();
        $this->actingAs($c)->getJson('/operaciones/'.$id.'/estado')->assertJsonPath('estado', 'Reembolsada');
        $this->post('/operaciones/'.$id.'/valoracion', ['estrellas' => 1])->assertSessionHasErrors('valoracion');
        $this->actingAs($admin)->getJson('/reportes/ventas?desde=1900-01-01&hasta=2100-12-31')
            ->assertJsonPath('indicadores.devuelto', 500)->assertJsonPath('indicadores.comisiones', 0)->assertJsonPath('indicadores.total_liquidado', 0);
    }

    private function accion(User $u, int $id, string $accion, array $data = [])
    {
        return $this->actingAs($u)->post('/operaciones/'.$id.'/'.$accion, $data);
    }

    public function test_two_confirmations_settle_once_with_five_percent_and_conserve_all_funds(): void
    {
        [$id,$c,$v,$subasta] = $this->operacion();
        $this->actingAs($c)->post('/operaciones/subasta/'.$subasta->id_subasta)->assertRedirect('/operaciones/'.$id);
        $this->assertDatabaseCount('operacion_subasta', 1);
        $this->accion($v, $id, 'confirmar')->assertSessionHasNoErrors();
        $this->assertEquals(500, app(FondosService::class)->saldo($c->id_usuario)->reservado);
        $this->assertDatabaseCount('comision_operacion', 0);
        $this->accion($c, $id, 'confirmar')->assertSessionHasNoErrors();
        $this->accion($c, $id, 'confirmar')->assertSessionHasNoErrors();
        $this->accion($v, $id, 'confirmar')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('operacion_subasta', ['id_operacion' => $id, 'estado' => 'Completada', 'comision' => 25, 'neto' => 475]);
        $this->assertDatabaseHas('reserva_fondos', ['id_subasta' => $subasta->id_subasta, 'estado' => 'Consumida']);
        $f = app(FondosService::class);
        $this->assertEquals(500, $f->saldo($c->id_usuario)->disponible);
        $this->assertEquals(0, $f->saldo($c->id_usuario)->reservado);
        $this->assertEquals(475, $f->saldo($v->id_usuario)->disponible);
        $this->assertEquals(1000, DB::table('movimiento_fondos')->sum(DB::raw('disponible+reservado')) + DB::table('comision_operacion')->sum('importe'));
        $this->assertSame(1, DB::table('movimiento_fondos')->where('tipo', 'Pago')->count());
        $this->assertDatabaseCount('comision_operacion', 1);
        $this->get('/operaciones/'.$id)->assertOk()->assertSee('Pago registrado');
    }

    public function test_incident_holds_funds_and_admin_can_refund_once_with_a_reason(): void
    {
        [$id,$c,$v] = $this->operacion();
        $this->accion($c, $id, 'incidencia', ['motivo' => 'El vendedor no asistió al encuentro.'])->assertSessionHasNoErrors();
        $this->accion($c, $id, 'confirmar');
        $this->accion($v, $id, 'confirmar');
        $this->assertDatabaseCount('comision_operacion', 0);
        $this->assertEquals(500, app(FondosService::class)->saldo($c->id_usuario)->reservado);
        $caso = DB::table('incidencia_operacion')->first();
        $admin = $this->admin();
        $url = '/admin/operaciones/incidencias/'.$caso->id_incidencia;
        $this->actingAs($admin)->post($url, ['accion' => 'resolver', 'decision' => 'Devolver', 'motivo' => 'No se realizó la entrega acordada.'])->assertForbidden();
        $this->post($url, ['accion' => 'asignar'])->assertSessionHasNoErrors();
        $this->post($url, ['accion' => 'resolver', 'decision' => 'Devolver', 'motivo' => ''])->assertSessionHasErrors('motivo');
        for ($i = 0; $i < 2; $i++) {
            $this->post($url, ['accion' => 'resolver', 'decision' => 'Devolver', 'motivo' => 'No se realizó la entrega acordada.'])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('operacion_subasta', ['id_operacion' => $id, 'estado' => 'Reembolsada']);
        $this->assertEquals(1000, app(FondosService::class)->saldo($c->id_usuario)->disponible);
        $this->assertEquals(0, app(FondosService::class)->saldo($v->id_usuario)->total);
        $this->assertDatabaseCount('comision_operacion', 0);
        $this->assertSame(1, DB::table('movimiento_fondos')->where('tipo', 'Devolución')->count());
    }

    public function test_admin_can_authorize_payment_but_direct_settlement_without_confirmations_is_rejected(): void
    {
        [$id,$c,$v] = $this->operacion();
        try {
            DB::transaction(fn () => DB::select('select liquidar_operacion(?,?,false)', [$c->id_usuario, $id]));
            $this->fail('No debe pagar sin confirmación.');
        } catch (QueryException $e) {
            $this->assertSame('P0001', $e->errorInfo[0]);
        }
        $this->accion($v, $id, 'incidencia', ['motivo' => 'Necesitamos revisión del encuentro.']);
        $caso = DB::table('incidencia_operacion')->first();
        $a = $this->admin();
        $url = '/admin/operaciones/incidencias/'.$caso->id_incidencia;
        $this->actingAs($a)->post($url, ['accion' => 'asignar']);
        $this->post($url, ['accion' => 'resolver', 'decision' => 'Liquidar', 'motivo' => 'Se comprobó la entrega con ambas partes.'])->assertSessionHasNoErrors();
        $this->assertEquals(475, app(FondosService::class)->saldo($v->id_usuario)->disponible);
        $this->assertDatabaseHas('incidencia_operacion', ['id_incidencia' => $caso->id_incidencia, 'estado' => 'Resuelta', 'id_administrador' => $a->id_usuario]);
    }

    public function test_chat_is_private_paginated_idempotent_and_escapes_html(): void
    {
        [$id,$c,$v] = $this->operacion();
        $outsider = User::factory()->create();
        $admin = $this->admin();
        $this->actingAs($outsider)->get('/operaciones/'.$id)->assertNotFound();
        $this->getJson('/operaciones/'.$id.'/mensajes')->assertNotFound();
        $this->accion($outsider, $id, 'mensaje', ['referencia' => (string) Str::uuid(), 'texto' => 'No autorizado'])->assertNotFound();
        $this->actingAs($admin)->getJson('/operaciones/'.$id.'/mensajes')->assertForbidden();
        $ref = (string) Str::uuid();
        $texto = '<script>alert("xss")</script>';
        for ($i = 0; $i < 2; $i++) {
            $this->accion($c, $id, 'mensaje', ['referencia' => $ref, 'texto' => $texto])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('mensaje_operacion', 1);
        $this->accion($c, $id, 'mensaje', ['referencia' => $ref, 'texto' => 'Otro contenido'])->assertSessionHasErrors('operacion');
        $this->accion($c, $id, 'mensaje', ['referencia' => (string) Str::uuid(), 'texto' => str_repeat('x', 2001)])->assertSessionHasErrors('texto');
        $this->actingAs($v)->get('/operaciones/'.$id)->assertOk()->assertSee(e($texto), false)->assertDontSee($texto, false);
        $this->get('/mis-operaciones')->assertSee('1 mensaje(s) sin leer');
        $this->accion($v, $id, 'leer', ['hasta' => 0]);
        $this->assertDatabaseHas('operacion_subasta', ['id_operacion' => $id, 'leido_vendedor' => 0]);
        $hasta = DB::table('mensaje_operacion')->max('id_mensaje');
        $this->accion($v, $id, 'leer', ['hasta' => $hasta]);
        $this->get('/mis-operaciones')->assertDontSee('1 mensaje(s) sin leer');
        for ($i = 0; $i < 16; $i++) {
            app(OperacionService::class)->ejecutarAccion('mensaje', [$c->id_usuario, $id, (string) Str::uuid(), 'Mensaje '.$i]);
        }
        $this->actingAs($c)->getJson('/operaciones/'.$id.'/mensajes')->assertJsonCount(15, 'data')->assertJsonPath('total', 17);
        $this->getJson('/operaciones/'.$id.'/mensajes?mensajes=2')->assertJsonCount(2, 'data');
    }

    public function test_admin_chat_requires_assigned_open_case_and_each_access_is_audited(): void
    {
        [$id,$c] = $this->operacion();
        $a = $this->admin();
        $other = $this->admin();
        $this->accion($c, $id, 'incidencia', ['motivo' => 'El artículo es distinto a la publicación.']);
        $caso = DB::table('incidencia_operacion')->first();
        $url = '/admin/operaciones/incidencias/'.$caso->id_incidencia;
        $this->actingAs($a)->get($url.'/conversacion')->assertForbidden();
        $this->post($url, ['accion' => 'asignar']);
        $this->actingAs($other)->get($url.'/conversacion')->assertForbidden();
        $this->actingAs($a)->get($url.'/conversacion')->assertOk();
        $this->get($url.'/conversacion')->assertOk();
        $this->assertDatabaseCount('acceso_conversacion', 2);
        $this->post($url, ['accion' => 'resolver', 'decision' => 'Devolver', 'motivo' => 'Se verificó la diferencia del artículo.']);
        $this->get($url.'/conversacion')->assertForbidden();
    }

    public function test_meeting_proposal_needs_counterparty_response_and_does_not_settle(): void
    {
        [$id,$c,$v] = $this->operacion();
        $this->accion($c, $id, 'proponer', ['lugar' => 'Plaza Abaroa, puerta principal', 'fecha' => now('America/La_Paz')->addDay()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $a = DB::table('acuerdo_operacion')->first();
        $this->accion($c, $id, 'responder', ['acuerdo' => $a->id_acuerdo, 'aceptar' => 1])->assertSessionHasErrors('operacion');
        $this->accion($v, $id, 'responder', ['acuerdo' => $a->id_acuerdo, 'aceptar' => 0, 'motivo' => ''])->assertSessionHasErrors('operacion');
        $this->accion($v, $id, 'responder', ['acuerdo' => $a->id_acuerdo, 'aceptar' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('acuerdo_operacion', ['id_acuerdo' => $a->id_acuerdo, 'estado' => 'Aceptado']);
        $this->assertEquals(500, app(FondosService::class)->saldo($c->id_usuario)->reservado);
        $this->assertDatabaseCount('comision_operacion', 0);
        $this->accion($c, $id, 'proponer', ['lugar' => 'Otro lugar público', 'fecha' => now()->subDay()->format('Y-m-d\TH:i')])->assertSessionHasErrors('operacion');
    }

    public function test_seventy_two_hours_alert_once_and_never_releases_funds(): void
    {
        [$id,$c] = $this->operacion(true, true);
        $this->admin();
        $this->assertEquals(1, DB::selectOne('select revisar_operaciones_vencidas() as total')->total);
        $avisos = DB::table('notificacion')->count();
        $this->assertEquals(0, DB::selectOne('select revisar_operaciones_vencidas() as total')->total);
        $this->assertSame($avisos, DB::table('notificacion')->count());
        $this->assertDatabaseCount('incidencia_operacion', 1);
        $this->assertEquals(500, app(FondosService::class)->saldo($c->id_usuario)->reservado);
        $this->assertDatabaseCount('comision_operacion', 0);
    }

    public function test_legacy_conversation_preserves_history_without_inventing_reserves(): void
    {
        [$id,$c,$v] = $this->operacion(false);
        $this->assertDatabaseCount('reserva_fondos', 0);
        $this->accion($v, $id, 'confirmar');
        $this->accion($c, $id, 'confirmar')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('comision_operacion', 0);
        $this->assertEquals(1000, app(FondosService::class)->saldo($c->id_usuario)->total);
        $this->assertEquals(0, app(FondosService::class)->saldo($v->id_usuario)->total);
    }

    public function test_history_and_financial_conditions_cannot_be_edited(): void
    {
        [$id,$c] = $this->operacion();
        $this->accion($c, $id, 'mensaje', ['referencia' => (string) Str::uuid(), 'texto' => 'Historial permanente.']);
        foreach (['update mensaje_operacion set texto=\'Cambio\'', 'delete from evento_operacion', 'update operacion_subasta set comision=0,neto=importe'] as $sql) {
            try {
                DB::transaction(fn () => DB::statement($sql));
                $this->fail('Debe conservar la evidencia.');
            } catch (QueryException $e) {
                $this->assertSame('P0001', $e->errorInfo[0]);
            }
        }
    }

    public function test_scheduled_closing_creates_one_order_and_no_order_without_bids(): void
    {
        [$id,$c,$v,$s] = $this->operacion(true, true, false);
        $this->assertEquals(1, DB::selectOne('select finalizar_subastas() as total')->total);
        $this->assertEquals(0, DB::selectOne('select finalizar_subastas() as total')->total);
        $this->assertDatabaseCount('operacion_subasta', 1);
        $sinPujas = app(SubastaService::class)->crear($v->id_usuario, [
            'titulo' => 'Sin participantes', 'descripcion' => 'Artículo sin ofertas al finalizar el período.',
            'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => 100, 'incremento_puja' => 10,
            'id_categoria' => Categoria::firstOrFail()->id_categoria, 'fecha_fin' => now()->addDays(2)->toIso8601String(),
        ]);
        $sinPujas->forceFill(['estado_revision' => 'Aceptada', 'estado_subasta' => 'Finalizada'])->save();
        $this->assertNull(DB::selectOne('select crear_operacion_cierre(?) as id', [$sinPujas->id_subasta])->id);
        $this->assertDatabaseCount('operacion_subasta', 1);
        $this->assertDatabaseCount('comision_operacion', 0);
    }
}
