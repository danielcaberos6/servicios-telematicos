<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\SubastaService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrenciaReputacionTest extends TestCase
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
            $pdo->exec("set application_name='hu25_concurrencia'; set statement_timeout='8s'");
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
                $espera = DB::selectOne("select exists(select 1 from pg_stat_activity where datname='subastaya_test' and application_name='hu25_concurrencia' and wait_event_type='Lock') as espera")->espera;
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

    public function test_simultaneous_ratings_create_one_per_author_and_release_both_reviews(): void
    {
        [$id,$c,$v] = $this->operacion();
        foreach ([$c, $v] as $u) {
            DB::select('select confirmar_entrega(?,?)', [$u->id_usuario, $id]);
        }
        $salida = $this->competir(fn () => DB::select('select valorar_operacion(?,?,5,?)', [$c->id_usuario, $id, 'Vendedor puntual.']),
            'select valorar_operacion(?,?,4,?)', [$v->id_usuario, $id, 'Comprador puntual.']);
        $this->assertSame('aceptada', $salida);
        $this->assertDatabaseCount('resenas', 2);
        $this->assertSame(2, DB::table('resenas_publicadas')->count());
        $this->assertDatabaseCount('comision_operacion', 1);
    }

    public function test_same_author_simultaneous_retries_do_not_duplicate_the_review(): void
    {
        [$id,$c,$v] = $this->operacion();
        foreach ([$c, $v] as $u) {
            DB::select('select confirmar_entrega(?,?)', [$u->id_usuario, $id]);
        }
        $salida = $this->competir(fn () => DB::select('select valorar_operacion(?,?,5,?)', [$c->id_usuario, $id, 'Vendedor puntual.']),
            'select valorar_operacion(?,?,5,?)', [$c->id_usuario, $id, 'Vendedor puntual.']);
        $this->assertSame('aceptada', $salida);
        $this->assertDatabaseCount('resenas', 1);
        $this->assertSame(0, DB::table('resenas_publicadas')->count());
    }
}
