<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\OperacionService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\CloudBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AlmacenamientoNubeTest extends TestCase
{
    use RefreshDatabase;

    /** Un bucket no tiene rutas locales: cualquier uso de path() hace fallar la prueba. */
    private function discoSinRutas(string $nombre): void
    {
        $base = Storage::fake($nombre);
        Storage::set($nombre, new class($base->getDriver(), $base->getAdapter(), $base->getConfig()) extends FilesystemAdapter
        {
            public function path($path)
            {
                throw new \LogicException('Un bucket no ofrece rutas locales.');
            }
        });
    }

    private function subasta(User $vendedor, string $revision): Subasta
    {
        return $vendedor->subastas()->forceCreate(['titulo' => 'Artículo de prueba', 'descripcion' => 'Artículo de prueba.',
            'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => 100,
            'id_categoria' => Categoria::firstOrFail()->id_categoria, 'modelo_puja' => 1,
            'fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->addDay(), 'estado_revision' => $revision]);
    }

    private function foto(Subasta $subasta): string
    {
        $ruta = 'subastas/'.$subasta->id_subasta.'/foto.jpg';
        Storage::disk('public')->put($ruta, UploadedFile::fake()->image('foto.jpg', 20, 20)->getContent());

        return $subasta->imagenes()->create(['ruta' => $ruta, 'tamano' => Storage::disk('public')->size($ruta)])->url;
    }

    public function test_published_photo_is_served_from_a_bucket_with_public_cache(): void
    {
        $this->discoSinRutas('public');
        $url = $this->foto($this->subasta(User::factory()->create(), 'Aceptada'));

        $respuesta = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringContainsString('public', $respuesta->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=86400', $respuesta->headers->get('Cache-Control'));
        $this->assertNotSame('', $respuesta->streamedContent());
    }

    public function test_unpublished_photo_from_a_bucket_stays_private(): void
    {
        $this->discoSinRutas('public');
        $vendedor = User::factory()->create();
        $url = $this->foto($this->subasta($vendedor, 'En revisión'));

        $this->actingAs($vendedor)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
    }

    public function test_chat_audio_from_a_bucket_supports_ranges_and_leaves_no_temporary_copy(): void
    {
        $this->discoSinRutas('conversaciones');
        $vendedor = User::factory()->create();
        $comprador = User::factory()->create();
        $subasta = $this->subasta($vendedor, 'Aceptada');
        $subasta->pujas()->create(['id_usuario' => $comprador->id_usuario, 'monto' => 100]);
        $subasta->forceFill(['estado_subasta' => 'Finalizada'])->save();
        $operacion = app(OperacionService::class)->ejecutarAccion('crear', [$subasta->id_subasta])[0]->id;
        $muestras = str_repeat("\0\0", 8000);
        $wav = 'RIFF'.pack('V', 36 + strlen($muestras)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($muestras)).$muestras;

        $this->actingAs($comprador)->postJson('/operaciones/'.$operacion.'/mensaje', [
            'referencia' => (string) Str::uuid(), 'archivo' => UploadedFile::fake()->createWithContent('audio.wav', $wav),
        ])->assertOk();
        $mensaje = DB::table('mensaje_operacion')->first();
        $url = '/operaciones/'.$operacion.'/mensajes/'.$mensaje->id_mensaje.'/archivo';

        $parcial = $this->get($url, ['Range' => 'bytes=0-15'])->assertStatus(206);
        unlink($parcial->baseResponse->getFile()->getPathname());

        $completa = $this->get($url)->assertOk()->assertHeader('Content-Type', 'audio/wav')->assertHeader('Cache-Control', 'no-store, private');
        $copia = $completa->baseResponse->getFile()->getPathname();
        $this->assertSame($wav, file_get_contents($copia));
        ob_start();
        $completa->baseResponse->sendContent();
        ob_end_clean();
        $this->assertFileDoesNotExist($copia);
    }

    public function test_cloud_disks_keep_throwing_on_failed_writes(): void
    {
        $_SERVER['LARAVEL_CLOUD_DISK_CONFIG'] = json_encode(array_map(fn ($disco) => [
            'disk' => $disco, 'access_key_id' => 'clave', 'access_key_secret' => 'secreto', 'bucket' => $disco,
            'url' => null, 'endpoint' => 'https://bucket.invalid',
        ], ['public', 'identidad', 'conversaciones']));
        try {
            CloudBootstrapper::configureDisks($this->app);
        } finally {
            unset($_SERVER['LARAVEL_CLOUD_DISK_CONFIG']);
        }
        $this->assertFalse(config('filesystems.disks.public.throw'));

        (new AppServiceProvider($this->app))->register();

        foreach (['public', 'identidad', 'conversaciones'] as $disco) {
            $this->assertSame('s3', config("filesystems.disks.{$disco}.driver"));
            $this->assertTrue(config("filesystems.disks.{$disco}.throw"));
        }
    }
}
