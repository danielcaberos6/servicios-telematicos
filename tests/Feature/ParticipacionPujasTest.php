<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use App\Services\PujaService;
use App\Services\SubastaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ParticipacionPujasTest extends TestCase
{
    use RefreshDatabase;

    private function subasta(User $vendedor): Subasta
    {
        $subasta = app(SubastaService::class)->crear($vendedor->id_usuario, [
            'titulo' => 'Artículo para pujar', 'descripcion' => 'Descripción del artículo de prueba.',
            'ubicacion' => 'Plaza Abaroa, La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => '100.00', 'incremento_puja' => 10,
            'id_categoria' => Categoria::first()->id_categoria, 'fecha_fin' => now()->addDays(2)->toIso8601String(),
        ]);
        DB::table('subasta')->where('id_subasta', $subasta->id_subasta)->update(['estado_revision' => 'Aceptada']);

        return $subasta->refresh();
    }

    public function test_historial_muestra_cada_oferta_y_fecha_incluso_del_mismo_postor(): void
    {
        $vendedor = User::factory()->create();
        $uno = User::factory()->create();
        $dos = User::factory()->create();
        $subasta = $this->subasta($vendedor);
        $servicio = app(PujaService::class);
        foreach ([[$uno, '100.00'], [$dos, '110.00'], [$uno, '120.00']] as [$postor, $monto]) {
            $servicio->registrar($postor->id_usuario, $subasta->id_subasta, $monto);
        }
        $historial = $servicio->historial($subasta, $dos);
        $this->assertSame(['120.00', '110.00', '100.00'], $historial->pluck('monto')->all());
        $this->assertNotNull($historial->first()->fecha_puja);
        foreach ([$vendedor, $uno, $dos] as $usuario) {
            $this->actingAs($usuario)->get('/subastas/'.$subasta->id_subasta)->assertOk()
                ->assertSee('Historial de ofertas')->assertSeeInOrder(['Bs 120,00', 'Bs 110,00', 'Bs 100,00'])
                ->assertDontSee($uno->correo)->assertDontSee($dos->correo);
        }
    }

    public function test_historial_vacio_y_paginacion_conservan_todas_las_ofertas(): void
    {
        $vendedor = User::factory()->create();
        $subasta = $this->subasta($vendedor);
        $url = '/subastas/'.$subasta->id_subasta;
        $this->actingAs($vendedor)->get($url)->assertSee('Todavía no hay ofertas.');
        $postor = User::factory()->create();
        for ($monto = 100; $monto <= 250; $monto += 10) {
            app(PujaService::class)->registrar($postor->id_usuario, $subasta->id_subasta, (string) $monto);
        }
        $this->get($url)->assertViewHas('historialOfertas', fn ($p) => $p->total() === 16 && $p->count() === 15)
            ->assertSee('ofertas=2', false);
        $this->get($url.'?ofertas=2')->assertViewHas('historialOfertas', fn ($p) => $p->count() === 1 && $p->first()->monto === '100.00');
    }

    public function test_terceros_y_visitantes_no_reciben_el_historial_ni_consultandolo_directamente(): void
    {
        $subasta = $this->subasta(User::factory()->create());
        $postor = User::factory()->create(['telefono' => '71234567']);
        app(PujaService::class)->registrar($postor->id_usuario, $subasta->id_subasta, '100.00');
        $url = '/subastas/'.$subasta->id_subasta.'?ofertas=1';
        $this->get($url)->assertDontSee('Historial de ofertas')->assertDontSee($postor->correo)->assertDontSee($postor->telefono);
        $ajeno = User::factory()->create();
        $this->actingAs($ajeno)->get($url)->assertDontSee('Historial de ofertas')->assertViewHas('historialOfertas', null);
        foreach ([null, $ajeno] as $usuario) {
            try {
                app(PujaService::class)->historial($subasta, $usuario);
                $this->fail('El historial debe ser privado.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_ofertas_rechazadas_no_cambian_monto_ni_generan_avisos(): void
    {
        $vendedor = User::factory()->create();
        $uno = User::factory()->create();
        $dos = User::factory()->create();
        $subasta = $this->subasta($vendedor);
        $url = '/subastas/'.$subasta->id_subasta.'/pujas';
        $this->actingAs($uno)->post($url, ['monto' => '100.00'])->assertSessionHasNoErrors();
        $this->actingAs($dos)->post($url, ['monto' => '110.00'])->assertSessionHasNoErrors();
        $avisos = DB::table('notificacion')->count();
        foreach (['100.00', '110.00', '0', '-1', '111.001', 'texto'] as $monto) {
            $this->post($url, compact('monto'))->assertSessionHasErrors('monto');
        }
        $this->actingAs($vendedor)->post($url, ['monto' => '120.00'])->assertForbidden();
        $this->assertSame(2, $subasta->pujas()->count());
        $this->assertSame('110.00', (string) $subasta->pujas()->max('monto'));
        $this->assertSame($avisos, DB::table('notificacion')->count());
        $this->assertSame(0, $vendedor->notificaciones()->where('titulo', 'Nueva oferta')->count());
        $this->assertSame(1, $uno->notificaciones()->where('titulo', 'Superaron tu oferta')->count());
        $this->assertSame(0, $dos->notificaciones()->where('titulo', 'Superaron tu oferta')->count());
    }

    public function test_las_pujas_avisan_solo_al_postor_desplazado_y_conservan_los_avisos_anteriores(): void
    {
        $vendedor = User::factory()->create();
        $uno = User::factory()->create();
        $dos = User::factory()->create();
        $subasta = $this->subasta($vendedor);
        $anterior = $vendedor->notificaciones()->create([
            'id_subasta' => $subasta->id_subasta,
            'titulo' => 'Nueva oferta', 'contenido' => 'Aviso anterior al ajuste.',
        ]);
        $this->artisan('subastas:instalar-funciones')->assertSuccessful();
        foreach ([[$uno, '100.00'], [$dos, '110.00'], [$dos, '120.00'], [$uno, '130.00']] as [$postor, $monto]) {
            app(PujaService::class)->registrar($postor->id_usuario, $subasta->id_subasta, $monto);
        }
        $this->assertSame(4, $subasta->pujas()->count());
        $this->assertSame(1, $vendedor->notificaciones()->where('titulo', 'Nueva oferta')->count());
        $this->assertNotNull($anterior->fresh());
        $this->assertSame(1, $uno->notificaciones()->where('titulo', 'Superaron tu oferta')->count());
        $this->assertSame(1, $dos->notificaciones()->where('titulo', 'Superaron tu oferta')->count());
    }
}
