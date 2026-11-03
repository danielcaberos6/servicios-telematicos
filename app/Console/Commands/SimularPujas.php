<?php

namespace App\Console\Commands;

use App\Services\FondosService;
use App\Services\SimulacionPujasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class SimularPujas extends Command
{
    protected $signature = 'subastas:simular {producto : camara, bicicleta o guitarra}
        {--intervalo=5 : Segundos entre ofertas (1 a 60)}
        {--preparacion=15 : Segundos para abrir el navegador (0 a 120)}
        {--cierre=10 : Segundos finales sin ofertas del script (2 a 60)}';

    protected $description = 'Simula diez pujas de cuatro usuarios y el cierre de un producto con reservas de billetera';

    public function handle(SimulacionPujasService $simulacion): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Las simulaciones solo están disponibles en local o pruebas.');

            return self::FAILURE;
        }
        $producto = $this->argument('producto');
        try {
            $escenario = $simulacion->escenario($producto);
        } catch (\InvalidArgumentException $error) {
            $this->error($error->getMessage());

            return self::INVALID;
        }
        foreach (['intervalo' => [1, 60], 'preparacion' => [0, 120], 'cierre' => [2, 60]] as $opcion => [$min, $max]) {
            if (filter_var($this->option($opcion), FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]) === false) {
                $this->error("--{$opcion} debe ser un entero entre {$min} y {$max}.");

                return self::INVALID;
            }
        }
        $lock = Cache::lock('simulacion-pujas:'.$producto, 1800);
        if (! $lock->get()) {
            $this->error('Ya se está ejecutando una simulación de este producto.');

            return self::FAILURE;
        }
        try {
            $intervalo = (int) $this->option('intervalo');
            $preparacion = (int) $this->option('preparacion');
            $duracion = $preparacion + (count($escenario['turnos']) - 1) * $intervalo + (int) $this->option('cierre');
            $datos = $simulacion->preparar($producto, $duracion);
            $subasta = $datos['subasta'];
            $this->info('Producto: '.$subasta->titulo);
            $this->line('Abre: '.url('/subastas/'.$subasta->id_subasta));
            $this->line('Para ver el ranking, inicia sesión como vendedor: '.$datos['vendedor']->correo);
            $this->line('Contraseña inicial de estas cuentas: '.SimulacionPujasService::CONTRASENA);
            $this->line('Las contraseñas de cuentas existentes se conservan.');
            $this->table(['Pujador', 'Cuenta', 'Disponible (Bs)'], array_map(fn ($usuario) => [
                $usuario->nombre, $usuario->correo, number_format(app(FondosService::class)->saldo($usuario->id_usuario)->disponible, 2, ',', '.'),
            ], $datos['pujadores']));
            $this->line('Cierra a las '.$subasta->fecha_fin->timezone('America/La_Paz')->format('H:i:s').' (Bolivia).');
            $this->line("Empieza en {$preparacion} segundos. La página actualiza las ofertas automáticamente.");
            if ($preparacion > 0) {
                sleep($preparacion);
            }
            foreach ($escenario['turnos'] as $turno => $indice) {
                if ($turno > 0) {
                    sleep($intervalo);
                }
                $usuario = $datos['pujadores'][$indice];
                $puja = $simulacion->ofertar($subasta, $usuario);
                $saldo = app(FondosService::class)->saldo($usuario->id_usuario);
                $this->line(sprintf('#%02d · %s · %s ofrece Bs %s · reservado Bs %s', $turno + 1,
                    now()->timezone('America/La_Paz')->format('H:i:s'), $usuario->nombre,
                    number_format($puja->monto, 2, ',', '.'), number_format($saldo->reservado, 2, ',', '.')));
            }
            $this->info('Últimas ofertas del script registradas. Esperando la hora de cierre…');
            while (($restantes = $simulacion->segundosRestantes($subasta)) > 0) {
                usleep((int) min(500000, max(1000, $restantes * 1000000)));
            }
            $operacion = $simulacion->finalizar($subasta);
            $this->info('Subasta finalizada. Ganador: '.$operacion->comprador.' · Bs '.number_format($operacion->importe, 2, ',', '.'));
            $this->line('El importe ganador queda reservado hasta resolver la entrega. Los demás pujadores recuperan su disponible.');
            $this->line('Operación: '.url('/operaciones/'.$operacion->id_operacion));

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error instanceof ValidationException ? implode(' ', $error->validator->errors()->all()) : $error->getMessage());
            $this->warn('Los registros ya confirmados se conservan. El planificador cerrará la subasta a su hora.');

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
