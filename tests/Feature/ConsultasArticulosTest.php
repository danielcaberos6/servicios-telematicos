<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use App\Services\ConsultaArticuloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ConsultasArticulosTest extends TestCase
{
    use RefreshDatabase;

    private function subasta(array $cambios = []): Subasta
    {
        return User::factory()->create()->subastas()->forceCreate(array_replace([
            'titulo' => 'Cámara para consultas', 'descripcion' => 'Cámara con accesorios de prueba.', 'ubicacion' => 'La Paz',
            'estado_articulo' => 'Usado', 'monto_inicial' => 100, 'id_categoria' => Categoria::firstOrFail()->id_categoria,
            'modelo_puja' => 1, 'fecha_inicio' => now()->subHour(), 'fecha_fin' => now()->addDays(2), 'estado_revision' => 'Aceptada',
        ], $cambios))->refresh();
    }

    private function preguntar(Subasta $subasta, ?User $user = null, string $texto = '¿Incluye su cargador?'): int
    {
        app(ConsultaArticuloService::class)->preguntar(($user ?? User::factory()->create())->id_usuario, $subasta->id_subasta, $texto, (string) Str::uuid());

        return DB::table('consulta_articulo')->max('id_consulta');
    }

    private function administrador(): User
    {
        $admin = User::factory()->create();
        DB::table('usuario_rol')->insert(['id_usuario' => $admin->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);

        return $admin;
    }

    public function test_public_questions_and_answers_are_ordered_paginated_and_escape_html(): void
    {
        $s = $this->subasta();
        $author = User::factory()->create(['nombre' => 'Persona interesada', 'correo' => 'privado@example.test', 'telefono' => '77777777']);
        for ($i = 1; $i <= 16; $i++) {
            $id = $this->preguntar($s, $author, sprintf('Pregunta número %02d', $i));
        }
        app(ConsultaArticuloService::class)->responder($s->id_usuario, $id, '<script>alert("prueba")</script>');
        $url = '/subastas/'.$s->id_subasta;
        $this->get($url)->assertOk()->assertSee('Pregunta número 01')->assertSee('Pregunta número 15')->assertDontSee('Pregunta número 16')
            ->assertSee('Inicia sesión')->assertDontSee($author->correo)->assertDontSee($author->telefono)
            ->assertViewHas('consultas', fn ($p) => $p->perPage() === 15 && $p->total() === 16);
        $this->get($url.'?preguntas=2')->assertOk()->assertSee('Pregunta número 16')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert', false)->assertSee('Vendedor');
    }

    public function test_question_is_linked_to_the_authenticated_actor_and_retries_do_not_duplicate_notifications(): void
    {
        $s = $this->subasta();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $payload = ['pregunta' => '¿La batería funciona?', 'clave_envio' => (string) Str::uuid(), 'id_usuario' => $other->id_usuario];
        $url = '/subastas/'.$s->id_subasta.'/preguntas';
        $this->actingAs($user)->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect('/subastas/'.$s->id_subasta.'#preguntas-articulo');
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('consulta_articulo', 1);
        $this->assertDatabaseHas('consulta_articulo', ['id_usuario' => $user->id_usuario, 'id_subasta' => $s->id_subasta]);
        $this->assertSame(1, DB::table('notificacion')->where('titulo', 'Nueva pregunta sobre tu artículo')->where('id_usuario', $s->id_usuario)->count());
        $this->assertDatabaseCount('puja', 0);
        $this->post($url, [...$payload, 'pregunta' => 'Otra pregunta usando la misma solicitud'])->assertSessionHasErrors('pregunta');
    }

    public function test_only_active_non_owner_users_can_ask_about_public_active_auctions(): void
    {
        $s = $this->subasta();
        $url = '/subastas/'.$s->id_subasta.'/preguntas';
        $data = ['pregunta' => '¿Es original?', 'clave_envio' => (string) Str::uuid()];
        $this->post($url, $data)->assertRedirect('/iniciar-sesion');
        $this->actingAs($s->usuario)->post($url, $data)->assertForbidden();
        $this->actingAs($this->administrador())->post($url, $data)->assertForbidden();
        $this->actingAs(User::factory()->create(['activo' => false]))->post($url, $data)->assertRedirect('/iniciar-sesion');
        $user = User::factory()->create();
        foreach ([['estado_revision' => 'En revisión'], ['estado_revision' => 'Rechazada', 'motivo_rechazo' => 'Publicación de prueba rechazada'], ['estado_subasta' => 'Cancelada'], ['estado_subasta' => 'Finalizada'], ['fecha_fin' => now()->subMinute()], ['fecha_inicio' => now()->addHour()]] as $state) {
            $other = $this->subasta($state);
            $response = $this->actingAs($user)->post('/subastas/'.$other->id_subasta.'/preguntas', $data);
            if (isset($state['estado_revision'])) {
                $response->assertNotFound();
            } else {
                $response->assertSessionHasErrors('pregunta')->assertStatus(302);
            }
        }
        $s->usuario->forceFill(['activo' => false])->save();
        $this->actingAs($user)->post($url, $data)->assertSessionHasErrors('pregunta');
        $this->assertDatabaseCount('consulta_articulo', 0);
    }

    public function test_response_belongs_to_the_seller_and_cannot_be_overwritten_or_duplicated(): void
    {
        $s = $this->subasta();
        $author = User::factory()->create();
        $id = $this->preguntar($s, $author);
        $url = '/consultas/'.$id.'/respuesta';
        $this->actingAs($author)->post($url, ['respuesta' => 'Respuesta ajena'])->assertForbidden();
        $this->actingAs($s->usuario)->post($url, ['respuesta' => 'Sí, incluye su cargador.'])->assertSessionHasNoErrors();
        $date = DB::table('consulta_articulo')->value('fecha_respuesta');
        $this->post($url, ['respuesta' => 'Sí, incluye su cargador.'])->assertSessionHasNoErrors();
        $this->assertSame($date, DB::table('consulta_articulo')->value('fecha_respuesta'));
        $this->post($url, ['respuesta' => 'Reemplazar la respuesta'])->assertSessionHasErrors('respuesta');
        $this->assertDatabaseHas('consulta_articulo', ['id_consulta' => $id, 'respuesta' => 'Sí, incluye su cargador.', 'id_responde' => $s->id_usuario]);
        $this->assertSame(1, DB::table('notificacion')->where('titulo', 'Respondieron tu pregunta')->where('id_usuario', $author->id_usuario)->count());
    }

    public function test_closed_and_cancelled_auctions_keep_readonly_history(): void
    {
        $user = User::factory()->create();
        foreach (['Finalizada', 'Cancelada', 'vencida'] as $state) {
            $s = $this->subasta();
            $id = $this->preguntar($s, $user);
            $s->forceFill($state === 'vencida' ? ['fecha_fin' => now()->subSecond()] : ['estado_subasta' => $state])->save();
            $this->actingAs($s->usuario)->post('/consultas/'.$id.'/respuesta', ['respuesta' => 'Respuesta tardía'])->assertSessionHasErrors('respuesta');
            $this->get('/subastas/'.$s->id_subasta)->assertOk()->assertSee('¿Incluye su cargador?')->assertDontSee('Enviar pregunta')->assertDontSee('Publicar respuesta');
        }
        $this->assertSame(0, DB::table('consulta_articulo')->whereNotNull('respuesta')->count());
    }

    public function test_blank_long_and_private_contact_content_is_rejected_in_http_and_sql(): void
    {
        $s = $this->subasta();
        $user = User::factory()->create();
        foreach ([' ', "\n\t", str_repeat('a', 1001), 'Escríbeme a persona@example.com', 'Llámame al 77777777', 'WhatsApp +591 7777-7777', 'Contáctame en https://wa.me/59177777777'] as $text) {
            $this->actingAs($user)->post('/subastas/'.$s->id_subasta.'/preguntas', ['pregunta' => $text, 'clave_envio' => (string) Str::uuid()])->assertSessionHasErrors('pregunta');
            try {
                app(ConsultaArticuloService::class)->preguntar($user->id_usuario, $s->id_subasta, $text, (string) Str::uuid());
                $this->fail('SQL debe rechazar texto inválido y contactos privados.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('pregunta', $e->errors());
            }
        }
        $id = $this->preguntar($s, $user);
        foreach ([' ', str_repeat('a', 2001), 'Mi correo es vendedor@example.com'] as $text) {
            $this->actingAs($s->usuario)->post('/consultas/'.$id.'/respuesta', ['respuesta' => $text])->assertSessionHasErrors('respuesta');
        }
        $this->assertNull(DB::table('consulta_articulo')->value('respuesta'));
    }

    public function test_moderation_hides_only_reported_content_and_preserves_a_private_audit(): void
    {
        $s = $this->subasta();
        $author = User::factory()->create();
        $id = $this->preguntar($s, $author, 'Pregunta que permanecerá visible');
        app(ConsultaArticuloService::class)->responder($s->id_usuario, $id, 'Respuesta reportada original');
        $this->actingAs($author)->post('/consultas/'.$id.'/reportes', ['tipo' => 'respuesta', 'motivo' => 'Motivo privado para administración'])->assertSessionHasNoErrors();
        $this->post('/consultas/'.$id.'/reportes', ['tipo' => 'respuesta', 'motivo' => 'Reenvío'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('reporte_consulta', 1);
        $report = DB::table('reporte_consulta')->value('id_reporte');
        $this->getJson('/admin/consultas')->assertForbidden();
        $this->patchJson('/admin/consultas/reportes/'.$report, ['accion' => 'ocultar', 'motivo_resolucion' => 'Motivo falso'])->assertForbidden();
        $admin = $this->administrador();
        $this->actingAs($admin)->get('/admin/consultas')->assertOk()->assertSee('Motivo privado para administración');
        $url = '/admin/consultas/reportes/'.$report;
        $this->patch($url, ['accion' => 'ocultar'])->assertSessionHasErrors('motivo_resolucion');
        $this->patch($url, ['accion' => 'ocultar', 'motivo_resolucion' => 'Contenido inapropiado'])->assertSessionHasNoErrors();
        $this->patch($url, ['accion' => 'desestimar', 'motivo_resolucion' => 'No reemplazar'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reporte_consulta', ['id_reporte' => $report, 'id_moderador' => $admin->id_usuario, 'motivo_resolucion' => 'Contenido inapropiado', 'accion' => 'ocultar']);
        $this->assertNotNull(DB::table('reporte_consulta')->value('fecha_resolucion'));
        $this->assertDatabaseHas('consulta_articulo', ['id_consulta' => $id, 'respuesta' => 'Respuesta reportada original']);
        $this->flushSession();
        $this->get('/subastas/'.$s->id_subasta)->assertOk()->assertSee('Pregunta que permanecerá visible')->assertSee('ocultada por moderación')->assertDontSee('Respuesta reportada original')->assertDontSee('Motivo privado');
        $this->get('/admin/consultas?estado=resueltos')->assertOk()->assertSee('Contenido inapropiado');
    }

    public function test_hiding_a_question_removes_its_answer_publicly_and_reports_remain_auditable(): void
    {
        $s = $this->subasta();
        $user = User::factory()->create();
        $id = $this->preguntar($s, $user, 'Contenido para ocultar');
        app(ConsultaArticuloService::class)->responder($s->id_usuario, $id, 'Respuesta del hilo oculto');
        $servicio = app(ConsultaArticuloService::class);
        $servicio->reportar($user->id_usuario, $id, 'pregunta', 'Spam');
        $servicio->reportar(User::factory()->create()->id_usuario, $id, 'pregunta', 'Contenido repetido');
        $servicio->moderar($this->administrador()->id_usuario, DB::table('reporte_consulta')->min('id_reporte'), 'ocultar', 'Spam confirmado');
        $this->assertSame(2, DB::table('reporte_consulta')->where('accion', 'ocultar')->count());
        $this->get('/subastas/'.$s->id_subasta)->assertOk()->assertDontSee('Contenido para ocultar')->assertDontSee('Respuesta del hilo oculto')->assertViewHas('consultas', fn ($p) => $p->total() === 0);
        $this->assertDatabaseCount('consulta_articulo', 1);
    }

    public function test_private_requests_and_missing_or_hidden_replies_cannot_be_reported(): void
    {
        $s = $this->subasta();
        $user = User::factory()->create();
        $id = $this->preguntar($s, $user);
        $this->actingAs($user)->post('/consultas/'.$id.'/reportes', ['tipo' => 'respuesta', 'motivo' => 'No existe'])->assertNotFound();
        $s->forceFill(['estado_revision' => 'En revisión'])->save();
        $this->post('/consultas/'.$id.'/reportes', ['tipo' => 'pregunta', 'motivo' => 'Privada'])->assertNotFound();
        $this->get('/subastas/'.$s->id_subasta)->assertNotFound();
        $this->actingAs($s->usuario)->get('/subastas/'.$s->id_subasta)->assertOk()->assertDontSee('¿Incluye su cargador?');
        $this->assertDatabaseCount('reporte_consulta', 0);
    }
}
