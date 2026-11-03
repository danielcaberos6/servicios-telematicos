<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\OperacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatMultimediaTest extends TestCase
{
    use RefreshDatabase;

    private function operacion(): array
    {
        Storage::fake('conversaciones');
        $v = User::factory()->create();
        $c = User::factory()->create();
        $s = $v->subastas()->forceCreate(['titulo' => 'Artículo entregado', 'descripcion' => 'Artículo de prueba.',
            'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => 100,
            'id_categoria' => Categoria::firstOrFail()->id_categoria, 'modelo_puja' => 1,
            'fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->addDay(), 'estado_revision' => 'Aceptada']);
        $s->pujas()->create(['id_usuario' => $c->id_usuario, 'monto' => 100]);
        $s->forceFill(['estado_subasta' => 'Finalizada'])->save();
        $id = app(OperacionService::class)->ejecutarAccion('crear', [$s->id_subasta])[0]->id;

        return [$id, $c, $v];
    }

    private function wav(): UploadedFile
    {
        $samples = str_repeat("\0\0", 8000);
        $data = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($samples)).$samples;

        return UploadedFile::fake()->createWithContent('audio.wav', $data);
    }

    public function test_photo_is_private_accessible_to_both_parties_and_idempotent(): void
    {
        [$id, $c, $v] = $this->operacion();
        $ref = (string) Str::uuid();
        $photo = UploadedFile::fake()->image('articulo.jpg', 200, 150);
        $url = '/operaciones/'.$id.'/mensaje';
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($c)->postJson($url, ['referencia' => $ref, 'texto' => 'Así está el artículo.', 'archivo' => $photo])->assertOk();
        }
        $this->assertDatabaseCount('mensaje_operacion', 1);
        $this->assertCount(1, Storage::disk('conversaciones')->allFiles());
        $result = $this->getJson('/operaciones/'.$id.'/mensajes')->assertOk()->assertJsonPath('data.0.tipo', 'Foto');
        $this->assertArrayNotHasKey('archivo_ruta', $result->json('data.0'));
        $file = $result->json('data.0.archivo_url');
        $this->actingAs($v)->get($file)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->create())->get($file)->assertNotFound();
        $this->postJson($url, ['referencia' => (string) Str::uuid(), 'archivo' => $photo])->assertNotFound();
        $this->assertCount(1, Storage::disk('conversaciones')->allFiles());
    }

    public function test_audio_has_native_playback_and_failed_send_cleans_up_file(): void
    {
        [$id, $c] = $this->operacion();
        $this->actingAs($c)->postJson('/operaciones/'.$id.'/mensaje', ['referencia' => (string) Str::uuid(), 'archivo' => $this->wav()])->assertOk();
        $this->get('/operaciones/'.$id)->assertOk()->assertSee('<audio controls', false)->assertSee('Grabar audio')->assertDontSee('Solo texto');
        $message = DB::table('mensaje_operacion')->first();
        $this->get('/operaciones/'.$id.'/mensajes/'.$message->id_mensaje.'/archivo')->assertOk()->assertHeader('Content-Type', 'audio/wav');
        $this->get('/operaciones/'.$id.'/mensajes/'.$message->id_mensaje.'/archivo', ['Range' => 'bytes=0-15'])->assertStatus(206);
        DB::table('operacion_subasta')->where('id_operacion', $id)->update(['estado' => 'Reembolsada']);
        $this->postJson('/operaciones/'.$id.'/mensaje', ['referencia' => (string) Str::uuid(), 'archivo' => $this->wav()])->assertUnprocessable();
        $this->assertDatabaseCount('mensaje_operacion', 1);
        $this->assertCount(1, Storage::disk('conversaciones')->allFiles());
    }

    public function test_rejects_documents_empty_messages_oversized_and_disguised_files(): void
    {
        [$id, $c] = $this->operacion();
        $this->actingAs($c);
        foreach ([UploadedFile::fake()->createWithContent('foto.jpg', '<script>alert(1)</script>'),
            UploadedFile::fake()->create('documento.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->create('grande.mp3', 5121, 'audio/mpeg')] as $file) {
            $this->postJson('/operaciones/'.$id.'/mensaje', ['referencia' => (string) Str::uuid(), 'archivo' => $file])->assertUnprocessable()->assertJsonValidationErrors('archivo');
        }
        $this->postJson('/operaciones/'.$id.'/mensaje', ['referencia' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertDatabaseCount('mensaje_operacion', 0);
        $this->assertCount(0, Storage::disk('conversaciones')->allFiles());
    }

    public function test_only_assigned_admin_can_read_attachments_with_an_audit_record(): void
    {
        [$id, $c] = $this->operacion();
        $this->actingAs($c)->postJson('/operaciones/'.$id.'/mensaje', ['referencia' => (string) Str::uuid(), 'archivo' => $this->wav()])->assertOk();
        $message = DB::table('mensaje_operacion')->first();
        $case = app(OperacionService::class)->ejecutarAccion('incidencia', [$c->id_usuario, $id, 'Necesitamos revisar el artículo.']);
        $incidencia = DB::table('incidencia_operacion')->value('id_incidencia');
        $admin = User::factory()->create();
        DB::table('usuario_rol')->insert(['id_usuario' => $admin->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);
        $url = '/admin/operaciones/incidencias/'.$incidencia.'/mensajes/'.$message->id_mensaje.'/archivo';
        $this->actingAs($admin)->get($url)->assertForbidden();
        app(OperacionService::class)->ejecutarAccion('asignar', [$admin->id_usuario, $incidencia]);
        $this->get($url)->assertOk();
        $this->assertDatabaseHas('acceso_conversacion', ['id_incidencia' => $incidencia, 'id_administrador' => $admin->id_usuario]);
        $this->get('/admin/operaciones/incidencias/'.$incidencia.'/conversacion')->assertOk()->assertSee('audio controls', false);
    }
}
