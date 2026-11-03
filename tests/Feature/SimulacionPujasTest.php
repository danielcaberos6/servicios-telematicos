<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FondosService;
use App\Services\SimulacionPujasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SimulacionPujasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $admin = User::factory()->create();
        DB::table('usuario_rol')->insert(['id_usuario' => $admin->id_usuario,
            'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);
    }

    public static function productos(): array
    {
        return [['camara', 1450], ['bicicleta', 680], ['guitarra', 340]];
    }

    #[DataProvider('productos')]
    public function test_four_bidders_reserve_release_and_finish_each_product(int|string $producto, int $importe): void
    {
        $servicio = app(SimulacionPujasService::class);
        $datos = $servicio->preparar($producto, 3);
        $s = $datos['subasta'];
        $this->assertSame('Aceptada', $s->estado_revision);
        $this->assertEquals(2, $s->modelo_puja);
        $this->assertTrue((bool) $s->requiere_fondos);
        Storage::disk('public')->assertExists($s->imagenes()->firstOrFail()->ruta);
        $this->assertDatabaseHas('decision_subasta', ['id_subasta' => $s->id_subasta, 'estado' => 'Aceptada']);
        $anterior = null;
        foreach ($datos['escenario']['turnos'] as $indice) {
            $usuario = $datos['pujadores'][$indice];
            $p = $servicio->ofertar($s, $usuario);
            $this->assertEquals($p->monto, app(FondosService::class)->saldo($usuario->id_usuario)->reservado);
            if ($anterior !== null) {
                $this->assertEquals(0, app(FondosService::class)->saldo($anterior->id_usuario)->reservado);
            }
            $anterior = $usuario;
        }
        $this->assertSame(10, $s->pujas()->count());
        $this->assertEquals(4, $s->pujas()->distinct()->count('id_usuario'));
        $this->actingAs($datos['vendedor'])->get('/subastas/'.$s->id_subasta)->assertOk()->assertSee('Ranking de participantes');
        $limite = microtime(true) + 4;
        while ($servicio->segundosRestantes($s) > 0 && microtime(true) < $limite) {
            usleep(100000);
        }
        $op = $servicio->finalizar($s);
        $this->assertSame('Finalizada', $s->refresh()->estado_subasta);
        $this->assertEquals($importe, $op->importe);
        $this->assertEquals($anterior->id_usuario, $op->id_comprador);
        $this->assertSame('Pendiente', $op->estado);
        $this->assertDatabaseHas('reserva_fondos', ['id_subasta' => $s->id_subasta, 'estado' => 'Retenida', 'monto' => $importe]);
        $this->assertDatabaseHas('notificacion', ['id_usuario' => $anterior->id_usuario, 'id_subasta' => $s->id_subasta, 'titulo' => '¡Ganaste una subasta!']);
        $this->assertDatabaseHas('notificacion', ['id_usuario' => $datos['vendedor']->id_usuario, 'id_subasta' => $s->id_subasta, 'titulo' => 'Subasta finalizada']);
        // El cierre es idempotente incluso si también interviene el planificador.
        $this->assertEquals($op->id_operacion, $servicio->finalizar($s)->id_operacion);
        $this->assertDatabaseCount('comision_operacion', 0);
    }

    public function test_repeating_a_product_preserves_accounts_passwords_and_previous_reserves(): void
    {
        $servicio = app(SimulacionPujasService::class);
        $primera = $servicio->preparar('camara', 30);
        $usuario = $primera['pujadores'][0];
        $usuario->contrasena = 'ClaveCambiada2026!';
        $usuario->save();
        $hash = $usuario->contrasena;
        $servicio->ofertar($primera['subasta'], $usuario);
        $segunda = $servicio->preparar('camara', 30);
        $this->assertNotEquals($primera['subasta']->id_subasta, $segunda['subasta']->id_subasta);
        $this->assertEquals($usuario->id_usuario, $segunda['pujadores'][0]->id_usuario);
        $this->assertSame($hash, $usuario->refresh()->contrasena);
        $this->assertEquals(1000, app(FondosService::class)->saldo($usuario->id_usuario)->reservado);
        $this->assertEquals(2000, app(FondosService::class)->saldo($usuario->id_usuario)->disponible);
        $this->assertSame(1, $primera['subasta']->pujas()->count());
    }

    public function test_command_rejects_invalid_product_or_timing_without_creating_records(): void
    {
        $cuentas = User::count();
        $this->artisan('subastas:simular moto')->assertExitCode(2);
        $this->artisan('subastas:simular camara --intervalo=0')->assertExitCode(2);
        $this->artisan('subastas:simular camara --preparacion=-1')->assertExitCode(2);
        $this->artisan('subastas:simular camara --cierre=0')->assertExitCode(2);
        $this->assertSame($cuentas, User::count());
        $this->assertDatabaseCount('subasta', 0);
    }

    public function test_command_and_service_refuse_production(): void
    {
        app()->detectEnvironment(fn () => 'production');
        try {
            $this->artisan('subastas:simular camara')->assertFailed();
            $this->expectException(\RuntimeException::class);
            app(SimulacionPujasService::class)->preparar('camara', 30);
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    }
}
