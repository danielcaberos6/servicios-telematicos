<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Models\VerificacionIdentidad;
use App\Services\IdentidadService;
use App\Services\SubastaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RevisionIdentidadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        DB::table('usuario_rol')->insert(['id_usuario' => $user->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);

        return $user;
    }

    private function solicitud(): VerificacionIdentidad
    {
        Storage::fake('identidad');
        $user = User::factory()->create();
        $user->forceFill(['nombres' => 'Juan José', 'apellidos' => 'Pérez Soto'])->save();
        $user->imagen()->create(['ruta' => 'perfiles/prueba.png', 'tamano' => 100]);
        $id = app(IdentidadService::class)->solicitar($user->id_usuario, $user->refresh()->version_identidad, UploadedFile::fake()->image('carnet.png'));

        return VerificacionIdentidad::findOrFail($id);
    }

    public function test_approval_sets_badge_and_notifies_once_and_repeated_decisions_are_rejected(): void
    {
        $admin = $this->admin();
        $solicitud = $this->solicitud();
        $url = '/admin/identidad/'.$solicitud->id_verificacion;
        $this->actingAs($admin)->get('/admin/identidad')->assertOk()->assertSee('Juan José Pérez Soto');
        $this->get('/identidad/'.$solicitud->id_verificacion)->assertSee('Revisión manual')->assertSee('Carnet privado');
        $this->patch($url, ['decision' => 'Aprobada'])->assertSessionHasErrors('revision_confirmada');
        $this->patch($url, ['decision' => 'Aprobada', 'revision_confirmada' => 1])->assertSessionHasNoErrors();
        $this->assertTrue($solicitud->usuario->refresh()->identidad_verificada);
        $this->assertSame($admin->id_usuario, $solicitud->refresh()->id_revisor);
        $this->assertNotNull($solicitud->fecha_decision);
        $this->patch($url, ['decision' => 'Rechazada', 'motivo' => 'Otra decisión'])->assertSessionHasErrors('identidad');
        $this->assertSame(1, DB::table('notificacion')->where('id_verificacion', $solicitud->id_verificacion)->where('titulo', 'Identidad verificada')->count());
        $this->actingAs($solicitud->usuario)->get('/perfil')->assertSee('✓ Verificado');
        $this->put('/perfil', ['nombres' => 'Juan', 'apellidos' => 'Pérez Soto', 'correo' => $solicitud->usuario->correo])->assertSessionHasNoErrors();
        $this->assertFalse($solicitud->usuario->refresh()->identidad_verificada);
        $this->assertSame('Aprobada', $solicitud->refresh()->estado);
        $this->assertFalse($solicitud->vigente);
        $this->assertNotNull($solicitud->fecha_invalidacion);
    }

    public function test_rejection_requires_reason_and_allows_private_resubmission(): void
    {
        $admin = $this->admin();
        $solicitud = $this->solicitud();
        $url = '/admin/identidad/'.$solicitud->id_verificacion;
        $this->actingAs($admin);
        foreach (['', '   ', str_repeat('x', 1001)] as $motivo) {
            $this->patch($url, ['decision' => 'Rechazada', 'motivo' => $motivo])->assertSessionHasErrors('motivo');
        }
        $this->patch($url, ['decision' => 'Rechazada', 'motivo' => 'La imagen es ilegible'])->assertSessionHasNoErrors();
        $this->assertFalse($solicitud->usuario->refresh()->identidad_verificada);
        $this->actingAs($solicitud->usuario)->get('/perfil')->assertSee('La imagen es ilegible');
        $this->post('/perfil/identidad', ['carnet' => UploadedFile::fake()->image('nuevo.png'), 'confirmacion' => 1, 'version_identidad' => $solicitud->usuario->version_identidad])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('verificacion_identidad', 2);
        $this->assertSame('Rechazada', $solicitud->refresh()->estado);
    }

    public function test_ordinary_and_inactive_admins_cannot_review_and_changed_profiles_cannot_be_approved(): void
    {
        $admin = $this->admin();
        $solicitud = $this->solicitud();
        $url = '/admin/identidad/'.$solicitud->id_verificacion;
        $this->actingAs($solicitud->usuario)->getJson('/admin/identidad')->assertForbidden();
        $this->patchJson($url, ['decision' => 'Aprobada', 'revision_confirmada' => 1])->assertForbidden();
        $solicitud->usuario->forceFill(['nombres' => 'Nombre cambiado'])->save();
        $this->actingAs($admin)->patch($url, ['decision' => 'Aprobada', 'revision_confirmada' => 1])->assertSessionHasErrors('identidad');
        $admin->forceFill(['activo' => false])->save();
        $this->getJson('/admin/identidad')->assertForbidden();
        $this->assertFalse($solicitud->usuario->refresh()->identidad_verificada);
    }

    public function test_avatar_changes_invalidate_an_approval_without_removing_its_decision(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $solicitud = $this->solicitud();
        app(IdentidadService::class)->resolver($admin->id_usuario, $solicitud->id_verificacion, 'Aprobada', null);
        $user = $solicitud->usuario;
        $this->actingAs($user)->put('/perfil', ['nombres' => $user->nombres, 'apellidos' => $user->apellidos, 'correo' => $user->correo, 'foto' => UploadedFile::fake()->image('nueva.png')])->assertSessionHasNoErrors();
        $this->assertFalse($user->refresh()->identidad_verificada);
        $this->assertFalse($solicitud->refresh()->vigente);
        $this->assertSame($admin->id_usuario, $solicitud->id_revisor);
        $this->assertNotNull($solicitud->fecha_decision);
    }

    public function test_public_seller_badge_does_not_expose_the_document_or_its_private_url(): void
    {
        $admin = $this->admin();
        $solicitud = $this->solicitud();
        app(IdentidadService::class)->resolver($admin->id_usuario, $solicitud->id_verificacion, 'Aprobada', null);
        $auction = app(SubastaService::class)->crear($solicitud->id_usuario, [
            'titulo' => 'Artículo de usuario verificado', 'descripcion' => 'Artículo de prueba con descripción completa.',
            'ubicacion' => 'La Paz, Plaza Abaroa', 'id_categoria' => Categoria::first()->id_categoria,
            'estado_articulo' => 'Usado', 'monto_inicial' => 100, 'fecha_fin' => now()->addDays(3)->toDateTimeString(),
        ]);
        $auction->forceFill(['estado_revision' => 'Aceptada'])->save();
        $this->get('/subastas/'.$auction->id_subasta)->assertOk()->assertSee('✓ Verificado')
            ->assertDontSee($solicitud->ruta_documento)->assertDontSee(route('identidad.documento', $solicitud));
        $solicitud->usuario->forceFill(['nombres' => 'Nombre actualizado'])->save();
        $this->get('/subastas/'.$auction->id_subasta)->assertSee('No verificado')->assertDontSee('✓ Verificado');
    }
}
