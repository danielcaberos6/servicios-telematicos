<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\FondosService;
use App\Services\ReputacionService;
use App\Services\SubastaService;
use App\Services\UsuarioService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReputacionParticipantesTest extends TestCase
{
    use RefreshDatabase;

    private function operacion(bool $completar = true): array
    {
        $v = User::factory()->create();
        $c = User::factory()->conFondos(1000)->create();
        $s = app(SubastaService::class)->crear($v->id_usuario, [
            'titulo' => 'Cámara para valorar', 'descripcion' => 'Artículo para comprobar las experiencias verificadas.',
            'ubicacion' => 'La Paz, Plaza Abaroa', 'estado_articulo' => 'Usado', 'monto_inicial' => 500, 'incremento_puja' => 10,
            'id_categoria' => Categoria::firstOrFail()->id_categoria, 'fecha_fin' => now()->addDays(2)->toIso8601String(),
        ]);
        $s->forceFill(['estado_revision' => 'Aceptada'])->save();
        DB::select('select registrar_puja(?,?,?::numeric)', [$c->id_usuario, $s->id_subasta, 500]);
        $s->forceFill(['estado_subasta' => 'Finalizada'])->save();
        $id = DB::selectOne('select crear_operacion_cierre(?) as id', [$s->id_subasta])->id;
        if ($completar) {
            foreach ([$c, $v] as $u) {
                DB::select('select confirmar_entrega(?,?)', [$u->id_usuario, $id]);
            }
        }

        return [$id, $c, $v, $s];
    }

    private function valorar(User $u, int $id, int|string $estrellas = 5, string $comentario = 'Experiencia puntual y amable.')
    {
        return $this->actingAs($u)->post('/operaciones/'.$id.'/valoracion', ['estrellas' => $estrellas, 'comentario' => $comentario]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        DB::table('usuario_rol')->insert(['id_usuario' => $u->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);

        return $u;
    }

    public function test_only_counterparties_of_completed_deliveries_can_rate(): void
    {
        [$id,$c,$v] = $this->operacion(false);
        $extra = User::factory()->create();
        $this->valorar($c, $id)->assertSessionHasErrors('valoracion');
        $this->valorar($extra, $id)->assertNotFound();
        $this->valorar($this->admin(), $id)->assertForbidden();
        $this->assertDatabaseCount('resenas', 0);
        foreach ([$c, $v] as $u) {
            DB::select('select confirmar_entrega(?,?)', [$u->id_usuario, $id]);
        }
        $this->actingAs($c)->post('/operaciones/'.$id.'/valoracion', ['estrellas' => 5, 'id_usuario_resenado' => $c->id_usuario, 'comentario' => 'Muy buena entrega.'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('resenas', ['id_operacion' => $id, 'id_usuario_resenado' => $v->id_usuario, 'id_usuario_resenador' => $c->id_usuario, 'rol_resenado' => 'Vendedor']);
    }

    public function test_reserved_review_is_hidden_from_counterparty_profiles_and_averages_until_both_rate(): void
    {
        [$id,$c,$v] = $this->operacion();
        $this->valorar($c, $id, 1, 'Contenido privado antes de publicar.')->assertSessionHasNoErrors();
        $this->actingAs($v)->get('/operaciones/'.$id)->assertOk()->assertDontSee('Contenido privado antes de publicar.');
        $this->get('/participantes/'.$v->id_usuario)->assertOk()->assertDontSee('Contenido privado antes de publicar.')->assertSee('Sin valoraciones');
        $this->get('/perfil')->assertOk()->assertDontSee('Contenido privado antes de publicar.');
        $this->assertEmpty(app(UsuarioService::class)->resenas($v->id_usuario));
        $this->assertEquals(0, app(UsuarioService::class)->valoraciones($v->id_usuario)->sum('cantidad'));
        $r = DB::table('resenas')->first();
        $this->post('/resenas/'.$r->id_resena.'/reportes', ['categoria' => 'Acoso', 'detalle' => 'No debe poder ver el contenido.'])->assertNotFound();
        $this->valorar($v, $id, 4, 'Comprador cumplió el acuerdo.')->assertSessionHasNoErrors();
        $this->get('/participantes/'.$v->id_usuario)->assertSee('Contenido privado antes de publicar.')->assertSee('1,00 / 5');
        $this->assertEquals(4, app(ReputacionService::class)->resumen($c->id_usuario)[0]->promedio);
        $this->assertEquals(1, app(ReputacionService::class)->resumen($v->id_usuario)[1]->promedio);
    }

    public function test_deadline_automatically_publishes_the_single_review_and_rejects_late_reviews(): void
    {
        [$id,$c,$v] = $this->operacion();
        $this->valorar($c, $id, 3, 'Experiencia reservada hasta el plazo.');
        DB::statement("update operacion_subasta set fecha_resolucion=clock_timestamp()-interval '14 days'+interval '5 minutes' where id_operacion=?", [$id]);
        $this->assertEquals(0, DB::table('resenas_publicadas')->count());
        DB::statement("update operacion_subasta set fecha_resolucion=clock_timestamp()-interval '14 days' where id_operacion=?", [$id]);
        $this->assertEquals(1, DB::table('resenas_publicadas')->count());
        $this->valorar($v, $id)->assertSessionHasErrors('valoracion');
        $this->assertDatabaseCount('resenas', 1);
        $this->assertEquals(3, app(ReputacionService::class)->resumen($v->id_usuario)[1]->promedio);
    }

    public function test_rating_validation_idempotency_and_database_integrity(): void
    {
        [$id,$c,$v] = $this->operacion();
        foreach ([0, 6, '2.5'] as $stars) {
            $this->valorar($c, $id, $stars)->assertSessionHasErrors('estrellas');
        }
        $this->valorar($c, $id, 5, str_repeat('x', 1001))->assertSessionHasErrors('comentario');
        $this->valorar($c, $id, 5, '')->assertSessionHasNoErrors();
        $this->valorar($c, $id, 5, '')->assertSessionHasNoErrors();
        $this->valorar($c, $id, 4, 'Cambio de opinión.')->assertSessionHasErrors('valoracion');
        $this->assertDatabaseCount('resenas', 1);
        foreach (['update resenas set calificacion=1', 'delete from resenas'] as $sql) {
            try {
                DB::transaction(fn () => DB::statement($sql));
                $this->fail('La valoración debe conservarse.');
            } catch (QueryException $e) {
                $this->assertSame('P0001', $e->errorInfo[0]);
            }
        }
        try {
            DB::transaction(fn () => DB::table('resenas')->insert(['id_operacion' => $id, 'id_usuario_resenador' => $v->id_usuario, 'id_usuario_resenado' => User::factory()->create()->id_usuario, 'rol_resenado' => 'Comprador', 'calificacion' => 5]));
            $this->fail('No debe valorar a terceros.');
        } catch (QueryException $e) {
            $this->assertSame('P0001', $e->errorInfo[0]);
        }
    }

    public function test_report_moderation_preserves_score_and_operation_and_records_the_reason(): void
    {
        [$id,$c,$v] = $this->operacion();
        $this->valorar($c, $id, 1, 'Texto que revela datos personales.');
        $this->valorar($v, $id);
        $resena = DB::table('resenas')->where('id_usuario_resenador', $c->id_usuario)->first();
        $url = '/resenas/'.$resena->id_resena.'/reportes';
        $this->actingAs($v)->post($url, ['categoria' => 'Puntuación baja', 'detalle' => 'No me gusta una estrella.'])->assertSessionHasErrors('categoria');
        for ($i = 0; $i < 2; $i++) {
            $this->post($url, ['categoria' => 'Datos personales', 'detalle' => 'El texto revela información privada.'])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('reporte_resena', 1);
        $reporte = DB::table('reporte_resena')->first();
        $this->postJson('/admin/resenas/reportes/'.$reporte->id_reporte, ['accion' => 'Ocultar comentario', 'motivo' => 'Contiene información personal.'])->assertForbidden();
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/resenas/reportes')->assertOk()->assertSee('Texto que revela datos personales.');
        $this->post('/admin/resenas/reportes/'.$reporte->id_reporte, ['accion' => 'Ocultar comentario', 'motivo' => ''])->assertSessionHasErrors('motivo');
        for ($i = 0; $i < 2; $i++) {
            $this->post('/admin/resenas/reportes/'.$reporte->id_reporte, ['accion' => 'Ocultar comentario', 'motivo' => 'Se retiró contenido con datos personales.'])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('moderacion_resena', 1);
        $this->assertDatabaseHas('moderacion_resena', ['id_administrador' => $admin->id_usuario, 'motivo' => 'Se retiró contenido con datos personales.']);
        $this->get('/participantes/'.$v->id_usuario)->assertDontSee('Texto que revela datos personales.')->assertSee('Comentario oculto')->assertSee('1,00 / 5');
        $this->assertEquals(1, app(UsuarioService::class)->resenas($v->id_usuario)->first()->calificacion);
        $this->assertNull(app(UsuarioService::class)->resenas($v->id_usuario)->first()->comentario);
        $this->assertDatabaseHas('operacion_subasta', ['id_operacion' => $id, 'estado' => 'Completada']);
        $this->assertEquals(475, app(FondosService::class)->saldo($v->id_usuario)->disponible);
    }

    public function test_public_projection_excludes_private_data_and_city_needs_consent(): void
    {
        $u = User::factory()->create(['nombre' => 'Persona Pública', 'ciudad' => 'Ciudad reservada', 'telefono' => '77712345', 'biografia' => 'Texto privado del perfil', 'correo' => 'correo-privado@example.test']);
        $perfil = app(ReputacionService::class)->perfil($u->id_usuario);
        $this->assertSame(['id_usuario', 'nombre', 'ciudad', 'identidad_verificada', 'id_imagen'], array_keys((array) $perfil));
        $url = '/participantes/'.$u->id_usuario;
        $this->get($url)->assertOk()->assertSee('Persona Pública')->assertDontSee('Ciudad reservada')->assertDontSee($u->correo)->assertDontSee($u->telefono)->assertDontSee($u->biografia);
        $this->actingAs($u)->put('/perfil', ['nombre' => $u->nombre, 'correo' => $u->correo, 'ciudad_publica' => 1])->assertSessionHasNoErrors();
        $this->get($url)->assertSee('Ciudad reservada');
        $this->put('/perfil', ['nombre' => $u->nombre, 'correo' => $u->correo, 'ciudad_publica' => 0])->assertSessionHasNoErrors();
        $this->get($url)->assertDontSee('Ciudad reservada');
        DB::table('usuario')->where('id_usuario', $u->id_usuario)->update(['activo' => false]);
        $this->get($url)->assertRedirect(); // El middleware cierra la sesión desactivada.
        $this->get($url)->assertNotFound()->assertSee('Cuenta no disponible')->assertDontSee($u->nombre);
    }

    public function test_public_catalog_excludes_pending_and_rejected_even_for_owner_and_admin(): void
    {
        [$id,$c,$v,$s] = $this->operacion();
        foreach (['En revisión', 'Rechazada'] as $estado) {
            $pendiente = app(SubastaService::class)->crear($v->id_usuario, [
                'titulo' => 'Solicitud privada '.$estado, 'descripcion' => 'Descripción para comprobar privacidad del catálogo.',
                'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => 100, 'incremento_puja' => 10,
                'id_categoria' => Categoria::firstOrFail()->id_categoria, 'fecha_fin' => now()->addDays(2)->toIso8601String(),
            ]);
            if ($estado === 'Rechazada') {
                $pendiente->forceFill(['estado_revision' => 'Rechazada', 'motivo_rechazo' => 'Descripción insuficiente para publicarla.'])->save();
            }
        }
        foreach ([$v, $this->admin()] as $visitante) {
            $this->actingAs($visitante)->get('/participantes/'.$v->id_usuario)->assertOk()->assertSee($s->titulo)->assertDontSee('Solicitud privada');
        }
    }

    public function test_historical_reviews_remain_separate_and_paginated_and_html_is_escaped(): void
    {
        $u = User::factory()->create();
        $autor = User::factory()->create();
        for ($i = 0; $i < 17; $i++) {
            DB::table('resenas')->insert(['id_usuario_resenado' => $u->id_usuario, 'id_usuario_resenador' => $autor->id_usuario, 'calificacion' => 4.5, 'comentario' => 'Histórica '.$i]);
        }
        $this->assertSame(17, DB::table('resenas')->count());
        $this->assertEquals(0, collect(app(ReputacionService::class)->resumen($u->id_usuario))->sum('cantidad'));
        $this->assertCount(15, app(ReputacionService::class)->resenas($u->id_usuario, true));
        $this->get('/participantes/'.$u->id_usuario.'?historicas=2')->assertOk()->assertSee('Histórica 0')->assertSee('sin operación verificada');
        [$id,$c,$v] = $this->operacion();
        $xss = '<script>alert("prueba")</script>';
        $this->valorar($c, $id, 5, $xss);
        $this->valorar($v, $id);
        $this->get('/participantes/'.$v->id_usuario)->assertSee(e($xss), false)->assertDontSee($xss, false);
    }
}
