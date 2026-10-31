<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\FondosService;
use App\Services\SubastaService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcurrenciaFondosTest extends TestCase
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
            $pdo->exec("set application_name='hu22_concurrencia'; set statement_timeout='8s'");
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
                $espera = DB::selectOne("select exists(select 1 from pg_stat_activity where datname='subastaya_test' and application_name='hu22_concurrencia' and wait_event_type='Lock') as espera")->espera;
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

    public function test_two_confirmations_of_one_recharge_credit_only_once(): void
    {
        $u = User::factory()->create();
        $f = app(FondosService::class);
        $r = $f->solicitar($u->id_usuario, (string) Str::uuid(), '500');
        $salida = $this->competir(fn () => DB::select('select resolver_recarga(?,?::uuid,?)', [$u->id_usuario, $r->id_recarga, 'Aprobada']),
            'select resolver_recarga(?,?::uuid,?)', [$u->id_usuario, $r->id_recarga, 'Aprobada']);
        $this->assertSame('aceptada', $salida);
        $this->assertEquals(500, $f->saldo($u->id_usuario)->disponible);
        $this->assertSame(1, DB::table('movimiento_fondos')->count());
    }

    public function test_simultaneous_bids_in_different_auctions_cannot_spend_the_same_balance(): void
    {
        $u = User::factory()->conFondos(100)->create();
        $seller = User::factory()->create();
        $crear = function () use ($seller) {
            $s = app(SubastaService::class)->crear($seller->id_usuario, [
                'titulo' => 'Artículo para concurrencia', 'descripcion' => 'Descripción de prueba para las reservas concurrentes.',
                'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => 100, 'incremento_puja' => 10,
                'id_categoria' => Categoria::firstOrFail()->id_categoria, 'fecha_fin' => now()->addDays(2)->toIso8601String(),
            ]);
            $s->forceFill(['estado_revision' => 'Aceptada'])->save();

            return $s;
        };
        $uno = $crear();
        $dos = $crear();
        $salida = $this->competir(fn () => DB::select('select registrar_puja(?,?,?::numeric)', [$u->id_usuario, $uno->id_subasta, 100]),
            'select registrar_puja(?,?,?::numeric)', [$u->id_usuario, $dos->id_subasta, 100]);
        $this->assertSame('P0001', $salida);
        $this->assertSame(1, DB::table('puja')->count());
        $saldo = app(FondosService::class)->saldo($u->id_usuario);
        $this->assertEquals(0, $saldo->disponible);
        $this->assertEquals(100, $saldo->reservado);
        $this->assertSame(1, DB::table('reserva_fondos')->count());
    }
}
