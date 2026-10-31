<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\FondosService;
use App\Services\SubastaService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrenciaOperacionesTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        RefreshDatabaseState::$migrated = false;
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }

    private function competir(callable $primera, string $sql, array $parametros): string
    {
        $config = config('database.connections.pgsql');
        $this->assertSame('subastaya_test', $config['database']);
        $codigo = <<<'PHP'
            $c = json_decode(stream_get_contents(STDIN), true);
            $pdo = new PDO($c['dsn'], $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("set application_name='hu23_concurrencia'; set statement_timeout='8s'");
            try { $s=$pdo->prepare($c['sql']); $s->execute($c['params']); echo 'aceptada'; }
            catch (PDOException $e) { echo $e->getCode(); }
            PHP;
        $proceso = null;
        DB::beginTransaction();
        try {
            $primera();
            $proceso = proc_open([PHP_BINARY, '-r', $codigo], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            $this->assertIsResource($proceso);
            fwrite($pipes[0], json_encode([
                'dsn' => "pgsql:host={$config['host']};port={$config['port']};dbname=subastaya_test",
                'username' => $config['username'], 'password' => $config['password'], 'sql' => $sql, 'params' => $parametros,
            ], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $limite = microtime(true) + 4;
            do {
                DB::select('select pg_stat_clear_snapshot()');
                $espera = DB::selectOne("select exists(select 1 from pg_stat_activity where datname='subastaya_test' and application_name='hu23_concurrencia' and wait_event_type='Lock') as espera")->espera;
                if (! $espera) {
                    usleep(20000);
                }
            } while (! $espera && microtime(true) < $limite);
            $this->assertTrue($espera, 'La segunda conexión debe esperar los fondos de la primera.');
            DB::commit();
            $salida = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            return $salida;
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            if (is_resource($proceso)) {
                proc_close($proceso);
            }
        }
    }

    private function operacion(): array
    {
        $comprador = User::factory()->conFondos(1000)->create();
        $vendedor = User::factory()->create();
        $s = app(SubastaService::class)->crear($vendedor->id_usuario, [
            'titulo' => 'Artículo para entregar', 'descripcion' => 'Descripción para verificar liquidaciones concurrentes.',
            'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => 500, 'incremento_puja' => 10,
            'id_categoria' => Categoria::firstOrFail()->id_categoria, 'fecha_fin' => now()->addDays(2)->toIso8601String(),
        ]);
        $s->forceFill(['estado_revision' => 'Aceptada'])->save();
        DB::select('select registrar_puja(?,?,?::numeric)', [$comprador->id_usuario, $s->id_subasta, 500]);
        $s->forceFill(['estado_subasta' => 'Finalizada'])->save();
        $id = DB::selectOne('select crear_operacion_cierre(?) as id', [$s->id_subasta])->id;

        return [$id, $comprador, $vendedor];
    }

    public function test_simultaneous_confirmations_settle_exactly_once(): void
    {
        [$id,$c,$v] = $this->operacion();
        $salida = $this->competir(fn () => DB::select('select confirmar_entrega(?,?)', [$c->id_usuario, $id]),
            'select confirmar_entrega(?,?)', [$v->id_usuario, $id]);
        $this->assertSame('aceptada', $salida);
        $this->assertDatabaseHas('operacion_subasta', ['id_operacion' => $id, 'estado' => 'Completada']);
        $this->assertDatabaseCount('comision_operacion', 1);
        $this->assertSame(1, DB::table('movimiento_fondos')->where('tipo', 'Pago')->count());
        $this->assertEquals(475, app(FondosService::class)->saldo($v->id_usuario)->disponible);
    }

    public function test_incident_racing_confirmation_holds_reserve_without_payment(): void
    {
        [$id,$c,$v] = $this->operacion();
        DB::select('select confirmar_entrega(?,?)', [$c->id_usuario, $id]);
        $salida = $this->competir(fn () => DB::select('select abrir_incidencia(?,?,?)', [$c->id_usuario, $id, 'El artículo no coincide con las fotografías.']),
            'select confirmar_entrega(?,?)', [$v->id_usuario, $id]);
        $this->assertSame('aceptada', $salida);
        $this->assertDatabaseHas('operacion_subasta', ['id_operacion' => $id, 'estado' => 'En revisión']);
        $this->assertDatabaseCount('comision_operacion', 0);
        $this->assertEquals(500, app(FondosService::class)->saldo($c->id_usuario)->reservado);
    }
}
