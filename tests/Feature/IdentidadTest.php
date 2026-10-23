<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VerificacionIdentidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdentidadTest extends TestCase
{
    use RefreshDatabase;

    protected function persona(bool $admin = false): User
    {
        $user = User::factory()->create();
        $user->forceFill(['nombres' => 'Ana María', 'apellidos' => 'Pérez Soto'])->save();
        if ($admin) {
            DB::table('usuario_rol')->insert(['id_usuario' => $user->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);
        }

        return $user->refresh();
    }

    protected function solicitud(User $user): VerificacionIdentidad
    {
        $this->actingAs($user)->post('/perfil/identidad', [
            'carnet' => UploadedFile::fake()->image('carnet.png'), 'confirmacion' => 1,
            'version_identidad' => $user->refresh()->version_identidad,
        ])->assertSessionHasNoErrors()->assertRedirect('/perfil');

        return VerificacionIdentidad::where('id_usuario', $user->id_usuario)->latest('id_verificacion')->firstOrFail();
    }

    public function test_optional_private_submission_notifies_owner_and_active_admins_once(): void
    {
        Storage::fake('identidad');
        $user = $this->persona();
        $admin = $this->persona(true);
        $solicitud = $this->solicitud($user);
        $this->assertFalse($user->refresh()->identidad_verificada);
        $this->assertSame('En revisión', $solicitud->estado);
        Storage::disk('identidad')->assertExists($solicitud->ruta_documento);
        $this->assertDatabaseHas('notificacion', ['id_usuario' => $admin->id_usuario, 'id_verificacion' => $solicitud->id_verificacion]);
        $this->post('/perfil/identidad', ['carnet' => UploadedFile::fake()->image('otra.png'), 'confirmacion' => 1, 'version_identidad' => $user->version_identidad])
            ->assertSessionHasErrors('identidad');
        $this->assertDatabaseCount('verificacion_identidad', 1);
        $this->assertCount(1, Storage::disk('identidad')->allFiles());
        $this->assertSame(2, DB::table('notificacion')->where('id_verificacion', $solicitud->id_verificacion)->count());
        $this->get('/perfil')->assertSee('En revisión')->assertSee('No verificado');
    }

    public function test_document_and_request_are_private_to_owner_and_active_admins(): void
    {
        Storage::fake('identidad');
        $user = $this->persona();
        $admin = $this->persona(true);
        $solicitud = $this->solicitud($user);
        $path = '/identidad/'.$solicitud->id_verificacion;
        $this->get($path.'/documento')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->persona())->get($path)->assertForbidden();
        $this->get($path.'/documento')->assertForbidden();
        $this->actingAs($admin)->get($path)->assertOk()->assertSee('Ana María');
        $this->get($path.'/documento')->assertOk();
        $this->assertArrayNotHasKey('ruta_documento', $solicitud->toArray());
        $admin->forceFill(['activo' => false])->save();
        $this->getJson($path.'/documento')->assertForbidden();
        $this->get($path.'/documento')->assertRedirect('/iniciar-sesion');
        $this->get('/storage/'.$solicitud->ruta_documento)->assertForbidden();
    }

    public function test_missing_document_file_returns_not_found_only_to_authorized_users(): void
    {
        Storage::fake('identidad');
        $user = $this->persona();
        $solicitud = $this->solicitud($user);
        Storage::disk('identidad')->delete($solicitud->ruta_documento);
        $path = '/identidad/'.$solicitud->id_verificacion.'/documento';

        $this->actingAs($user)->get($path)->assertNotFound();
        $this->actingAs($this->persona(true))->get($path)->assertNotFound();
        $this->actingAs($this->persona())->get($path)->assertForbidden();
    }

    public function test_invalid_files_confirmation_and_stale_profile_are_rejected_without_orphans(): void
    {
        Storage::fake('identidad');
        $user = $this->persona();
        $this->actingAs($user);
        foreach ([UploadedFile::fake()->create('falso.png', 1, 'text/plain'), UploadedFile::fake()->image('grande.png')->size(5121), UploadedFile::fake()->image('otro.webp')] as $file) {
            $this->post('/perfil/identidad', ['carnet' => $file, 'confirmacion' => 1, 'version_identidad' => $user->version_identidad])->assertSessionHasErrors('carnet');
        }
        $this->post('/perfil/identidad', ['carnet' => UploadedFile::fake()->image('carnet.png'), 'version_identidad' => $user->version_identidad])->assertSessionHasErrors('confirmacion');
        $this->post('/perfil/identidad', ['carnet' => UploadedFile::fake()->image('carnet.png'), 'confirmacion' => 1, 'version_identidad' => $user->version_identidad + 1])->assertSessionHasErrors('identidad');
        $this->assertDatabaseCount('verificacion_identidad', 0);
        $this->assertCount(0, Storage::disk('identidad')->allFiles());
    }

    public function test_name_or_avatar_changes_invalidate_pending_requests_and_keep_the_history(): void
    {
        Storage::fake('identidad');
        Storage::fake('public');
        $user = $this->persona();
        $solicitud = $this->solicitud($user);
        $this->put('/perfil', ['nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'correo' => $user->correo])->assertSessionHasNoErrors();
        $this->assertSame('Invalidada', $solicitud->refresh()->estado);
        $this->assertFalse($solicitud->vigente);
        $this->assertSame('Ana María', $solicitud->nombres);
        $otra = $this->solicitud($user->refresh());
        $this->put('/perfil', ['nombres' => 'Ana', 'apellidos' => 'Pérez Soto', 'correo' => $user->correo, 'foto' => UploadedFile::fake()->image('perfil.png')])->assertSessionHasNoErrors();
        $this->assertSame('Invalidada', $otra->refresh()->estado);
        Storage::disk('identidad')->assertExists($solicitud->ruta_documento);
        $this->assertDatabaseCount('verificacion_identidad', 2);
        $this->assertFalse($user->refresh()->identidad_verificada);
    }
}
