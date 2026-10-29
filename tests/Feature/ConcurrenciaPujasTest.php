<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\SubastaService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrenciaPujasTest extends TestCase
{
    // Las dos conexiones necesitan ver datos confirmados; no usar la transacción de RefreshDatabase.
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        // Recrear solo subastaya_test, conservando categorías iniciales y evitando down() irreversibles.
        RefreshDatabaseState::$migrated = false;
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_ofertas_simultaneas_revalidan_el_monto_despues_del_bloqueo(): void
    {
        foreach (['100.00' => 'P0001', '99.00' => 'P0001', '105.00' => 'P0001', '110.00' => 'aceptada'] as $monto => $resultado) {
            $vendedor = User::factory()->create();
            $uno = User::factory()->create();
            $dos = User::factory()->create();
            $subasta = app(SubastaService::class)->crear($vendedor->id_usuario, [
                'titulo' => 'Pujas simultáneas', 'descripcion' => 'Artículo para comprobar concurrencia.',
                'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => '100.00', 'incremento_puja' => 10,
                'id_categoria' => Categoria::first()->id_categoria, 'fecha_fin' => now()->addDays(2)->toIso8601String(),
            ]);
            DB::table('subasta')->where('id_subasta', $subasta->id_subasta)->update(['estado_revision' => 'Aceptada']);
            $config = config('database.connections.pgsql');
            $this->assertSame('subastaya_test', $config['database']);

            // La configuración se entrega por stdin, nunca en argumentos ni en la salida del proceso.
            $codigo = <<<'PHP'
            $c = json_decode(stream_get_contents(STDIN), true);
            $pdo = new PDO($c['dsn'], $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("set application_name = 'hu13_concurrencia'; set statement_timeout = '8s'");
            try {
                $stmt = $pdo->prepare('select registrar_puja(?,?,?::numeric)');
                $stmt->execute($c['params']);
                echo 'aceptada';
            } catch (PDOException $e) {
                echo $e->getCode();
            }
            PHP;
            DB::beginTransaction();
            $proceso = null;
            try {
                DB::select('select registrar_puja(?,?,?::numeric)', [$uno->id_usuario, $subasta->id_subasta, '100.00']);
                $proceso = proc_open([PHP_BINARY, '-r', $codigo], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $tubos);
                $this->assertIsResource($proceso);
                fwrite($tubos[0], json_encode([
                    'dsn' => "pgsql:host={$config['host']};port={$config['port']};dbname=subastaya_test",
                    'username' => $config['username'], 'password' => $config['password'],
                    'params' => [$dos->id_usuario, $subasta->id_subasta, $monto],
                ], JSON_THROW_ON_ERROR));
                fclose($tubos[0]);
                $esperando = false;
                $limite = microtime(true) + 4;
                do {
                    DB::select('select pg_stat_clear_snapshot()');
                    $esperando = DB::selectOne("select exists(select 1 from pg_stat_activity where datname = 'subastaya_test' and application_name = 'hu13_concurrencia' and wait_event_type = 'Lock') as esperando")->esperando;
                    if (! $esperando) {
                        usleep(20000);
                    }
                } while (! $esperando && microtime(true) < $limite);
                $this->assertTrue($esperando, 'La segunda oferta debe esperar el bloqueo de la primera.');
                DB::commit();
                $salida = stream_get_contents($tubos[1]);
                fclose($tubos[1]);
                // El proceso solo devuelve el resultado, sin SQL ni credenciales.
                fclose($tubos[2]);
                $this->assertSame($resultado, $salida);
            } finally {
                if (DB::transactionLevel()) {
                    DB::rollBack();
                }
                if (is_resource($proceso)) {
                    proc_close($proceso);
                }
            }
            $aceptadas = $resultado === 'aceptada' ? 2 : 1;
            $this->assertSame($aceptadas, $subasta->pujas()->count());
            $this->assertSame(0, $vendedor->notificaciones()->where('titulo', 'Nueva oferta')->count());
            $this->assertSame($aceptadas - 1, $uno->notificaciones()->where('titulo', 'Superaron tu oferta')->count());
        }
    }
}
