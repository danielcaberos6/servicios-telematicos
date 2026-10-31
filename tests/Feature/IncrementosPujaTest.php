<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use App\Services\PujaService;
use App\Services\SubastaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IncrementosPujaTest extends TestCase
{
    use RefreshDatabase;

    private function datos(array $cambios = []): array
    {
        return array_replace([
            'titulo' => 'Artículo con incremento', 'descripcion' => 'Descripción del artículo con sus condiciones.',
            'ubicacion' => 'La Paz, Plaza Abaroa', 'estado_articulo' => 'Usado', 'monto_inicial' => 100,
            'incremento_puja' => 10, 'id_categoria' => Categoria::firstOrFail()->id_categoria,
            'fecha_fin' => now()->addDays(3)->toIso8601String(),
        ], $cambios);
    }

    private function publicada(int $incremento = 10): Subasta
    {
        $subasta = app(SubastaService::class)->crear(User::factory()->conFondos()->create()->id_usuario, $this->datos(['incremento_puja' => $incremento]));
        $subasta->forceFill(['estado_revision' => 'Aceptada'])->save();

        return $subasta->refresh();
    }

    public function test_every_increment_accepts_only_the_next_exact_bid(): void
    {
        $postor = User::factory()->conFondos()->create();
        foreach (Subasta::INCREMENTOS as $incremento) {
            $subasta = $this->publicada($incremento);
            $url = '/subastas/'.$subasta->id_subasta;
            $this->actingAs($postor)->get($url)->assertOk()->assertSee('Confirmar oferta de Bs 100')->assertDontSee('id="monto"', false);
            $this->post($url.'/pujas', ['monto' => 100])->assertSessionHasNoErrors();
            $this->post($url.'/pujas', ['monto' => 100 + $incremento])->assertSessionHasNoErrors();
            $this->get($url)->assertSee('Confirmar oferta de Bs '.(100 + 2 * $incremento));
            $this->assertSame(2, $subasta->pujas()->count());
        }
    }

    public function test_invalid_amounts_are_rejected_without_bids_or_notifications(): void
    {
        $subasta = $this->publicada();
        $postor = User::factory()->conFondos()->create();
        $url = '/subastas/'.$subasta->id_subasta.'/pujas';
        $this->actingAs($postor)->post($url, ['monto' => 101])->assertSessionHasErrors('monto');
        $this->post($url, ['monto' => 100])->assertSessionHasNoErrors();
        $avisos = DB::table('notificacion')->count();
        foreach (['100', '100.50', '101', '109', '111', '120', '110.001', '9999999999.99'] as $monto) {
            $this->post($url, compact('monto'))->assertSessionHasErrors('monto');
        }
        $this->assertSame(1, $subasta->pujas()->count());
        $this->assertSame($avisos, DB::table('notificacion')->count());
        try {
            app(PujaService::class)->registrar($postor->id_usuario, $subasta->id_subasta, '110.001');
            $this->fail('La función SQL debe rechazar centavos antes de que PostgreSQL redondee.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('monto', $e->errors());
        }
        foreach ([120, '110.50'] as $monto) {
            try {
                DB::transaction(fn () => $subasta->pujas()->create(['id_usuario' => $postor->id_usuario, 'monto' => $monto]));
                $this->fail('La inserción directa debe respetar el incremento.');
            } catch (QueryException $e) {
                $this->assertSame('P0001', $e->errorInfo[0]);
            }
        }
    }

    public function test_creation_and_editing_validate_and_preserve_the_increment(): void
    {
        Storage::fake('public');
        $owner = User::factory()->conFondos()->create();
        $this->actingAs($owner)->get('/subastas/crear')->assertOk()->assertSee('Incremento de puja (Bs)');
        $this->post('/subastas', [...$this->datos(), 'imagenes' => [UploadedFile::fake()->image('foto.jpg')]])->assertSessionHasNoErrors();
        $subasta = $owner->subastas()->sole();
        $this->assertSame(10, $subasta->incremento_puja);
        $this->put('/subastas/'.$subasta->id_subasta, $this->datos(['incremento_puja' => 50]))->assertSessionHasNoErrors();
        $this->assertSame(50, $subasta->fresh()->incremento_puja);
        foreach ([0, 7, 15, 10.5] as $incremento) {
            $this->put('/subastas/'.$subasta->id_subasta, $this->datos(['incremento_puja' => $incremento]))->assertSessionHasErrors('incremento_puja');
        }
        $this->put('/subastas/'.$subasta->id_subasta, $this->datos(['monto_inicial' => '100.50']))->assertSessionHasErrors('monto_inicial');
        $this->assertSame(50, $subasta->fresh()->incremento_puja);
        $this->assertSame('100.00', $subasta->fresh()->monto_inicial);
    }

    public function test_database_prevents_invalid_increments_and_changing_them_after_a_bid(): void
    {
        $subasta = app(SubastaService::class)->crear(User::factory()->conFondos()->create()->id_usuario, $this->datos());
        foreach ([0, 7] as $incremento) {
            try {
                DB::transaction(fn () => $subasta->update(['incremento_puja' => $incremento]));
                $this->fail('La base de datos debe limitar los incrementos permitidos.');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
        $subasta->refresh()->forceFill(['estado_revision' => 'Aceptada'])->save();
        app(PujaService::class)->registrar(User::factory()->conFondos()->create()->id_usuario, $subasta->id_subasta, '100');
        try {
            DB::transaction(fn () => $subasta->update(['incremento_puja' => 20]));
            $this->fail('Una subasta con pujas debe conservar su incremento.');
        } catch (QueryException $e) {
            $this->assertSame('P0001', $e->errorInfo[0]);
        }
        $this->assertSame(10, $subasta->fresh()->incremento_puja);
    }

    public function test_legacy_auctions_keep_decimal_bids_and_their_original_rules(): void
    {
        $subasta = User::factory()->conFondos()->create()->subastas()->forceCreate([
            ...$this->datos(), 'modelo_puja' => 1, 'monto_inicial' => '100.50',
            'fecha_inicio' => now()->subHour(), 'estado_revision' => 'Aceptada',
        ])->refresh();
        $postor = User::factory()->conFondos()->create();
        $this->artisan('subastas:instalar-funciones')->assertSuccessful();
        $this->actingAs($postor)->get('/subastas/'.$subasta->id_subasta)->assertSee('Modelo anterior');
        foreach (['100.50', '100.51'] as $monto) {
            $this->post('/subastas/'.$subasta->id_subasta.'/pujas', compact('monto'))->assertSessionHasNoErrors();
        }
        $this->assertSame(['100.50', '100.51'], $subasta->pujas()->orderBy('id_puja')->pluck('monto')->all());
        $this->assertSame('100.50', $subasta->fresh()->monto_inicial);
        $this->assertSame(1, $subasta->fresh()->modelo_puja);
    }

    public function test_conditions_remain_frozen_since_first_approval_without_bids(): void
    {
        $subasta = $this->publicada();
        $this->assertTrue($subasta->condiciones_fijadas);
        $url = '/subastas/'.$subasta->id_subasta;
        $subasta->imagenes()->create(['ruta' => 'prueba.jpg', 'tamano' => 1]);
        $this->actingAs($subasta->usuario)->put($url, $this->datos(['incremento_puja' => 20]))->assertSessionHasErrors('subasta');
        $this->put($url, $this->datos(['descripcion' => 'Descripción corregida con más información.']))->assertSessionHasNoErrors();
        $this->assertSame('En revisión', $subasta->fresh()->estado_revision);
        $this->put($url, $this->datos(['monto_inicial' => 105]))->assertSessionHasErrors('subasta');
        $this->assertSame(10, $subasta->fresh()->incremento_puja);
        $this->assertSame('100.00', $subasta->fresh()->monto_inicial);
    }
}
