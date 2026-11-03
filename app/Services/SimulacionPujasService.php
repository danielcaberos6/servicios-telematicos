<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SimulacionPujasService
{
    public const CONTRASENA = 'Simulacion2026!';

    public function escenario(string $producto): array
    {
        return match ($producto) {
            'camara' => ['titulo' => 'Cámara fotográfica con accesorios', 'categoria' => 'Electrónica e informática',
                'foto' => 'camara.jpg', 'inicial' => 1000, 'incremento' => 50, 'vendedor' => ['Valeria', 'Rojas'],
                'turnos' => [0, 1, 2, 0, 3, 1, 2, 3, 0, 1]],
            'bicicleta' => ['titulo' => 'Bicicleta para paseos urbanos', 'categoria' => 'Deporte',
                'foto' => 'bicicleta.png', 'inicial' => 500, 'incremento' => 20, 'vendedor' => ['Diego', 'Soto'],
                'turnos' => [2, 0, 1, 3, 2, 1, 0, 3, 1, 2]],
            'guitarra' => ['titulo' => 'Guitarra para estudio musical', 'categoria' => 'Instrumentos musicales',
                'foto' => 'guitarra-firmada.jpg', 'inicial' => 250, 'incremento' => 10, 'vendedor' => ['Mariana', 'Paz'],
                'turnos' => [3, 2, 0, 1, 3, 0, 2, 1, 0, 3]],
            default => throw new \InvalidArgumentException('Elige camara, bicicleta o guitarra.'),
        };
    }

    public function preparar(string $producto, int $duracion): array
    {
        $this->exigirEntorno();
        $escenario = $this->escenario($producto);
        if ($duracion < 2 || $duracion > 1200) {
            throw new \InvalidArgumentException('La duración debe estar entre 2 y 1200 segundos.');
        }
        $categoria = Categoria::where('nombre_categoria', $escenario['categoria'])->where('habilitada', true)->firstOrFail();
        $administrador = DB::table('usuario as u')->join('usuario_rol as ur', 'ur.id_usuario', '=', 'u.id_usuario')
            ->join('rol as r', 'r.id_rol', '=', 'ur.id_rol')->where('u.activo', true)
            ->where('r.nombre_rol', 'Administrador')->orderBy('u.id_usuario')->value('u.id_usuario');
        if (! $administrador) {
            throw new \RuntimeException('Se necesita un administrador activo para aprobar la publicación.');
        }
        $contenido = file_get_contents(public_path('imagenes/ejemplos/'.$escenario['foto']));
        if ($contenido === false) {
            throw new \RuntimeException('No se encontró la foto del producto.');
        }
        $ruta = null;
        try {
            return DB::transaction(function () use ($producto, $duracion, $escenario, $categoria, $administrador, $contenido, &$ruta) {
                $vendedor = $this->cuenta($producto, 'vendedor', ...$escenario['vendedor']);
                $pujadores = [];
                foreach ([['Elisa', 'Morales'], ['Mateo', 'Flores'], ['Sofía', 'Ríos'], ['Bruno', 'Castro']] as $indice => [$nombres, $apellidos]) {
                    $usuario = $this->cuenta($producto, 'pujador'.($indice + 1), $nombres, $apellidos);
                    $fondos = app(FondosService::class);
                    $presupuesto = $escenario['inicial'] + 20 * $escenario['incremento'];
                    $faltante = (int) ceil($presupuesto - (float) $fondos->saldo($usuario->id_usuario)->disponible);
                    if ($faltante > 0) {
                        $recarga = $fondos->solicitar($usuario->id_usuario, (string) Str::uuid(), (string) max(5, $faltante));
                        $fondos->resolver($usuario->id_usuario, $recarga->id_recarga, 'Aprobada');
                    }
                    $pujadores[] = $usuario;
                }
                $ahora = Carbon::parse(DB::selectOne('select clock_timestamp() as ahora')->ahora);
                $subastas = app(SubastaService::class);
                $subasta = $subastas->crear($vendedor->id_usuario, [
                    'titulo' => $escenario['titulo'].' · '.$ahora->format('d/m H:i:s').' '.Str::lower(Str::random(4)),
                    'descripcion' => 'Artículo con sus accesorios, disponible para revisar y coordinar la entrega en La Paz.',
                    'ubicacion' => 'La Paz, Plaza Abaroa', 'estado_articulo' => 'Usado',
                    'id_categoria' => $categoria->id_categoria, 'monto_inicial' => $escenario['inicial'],
                    'incremento_puja' => $escenario['incremento'], 'fecha_fin' => $ahora->addSeconds($duracion)->toIso8601String(),
                ]);
                $ruta = 'subastas/'.$subasta->id_subasta.'/'.$escenario['foto'];
                if (! Storage::disk('public')->put($ruta, $contenido)) {
                    throw new \RuntimeException('No se pudo guardar la foto del producto.');
                }
                app(ImagenService::class)->guardar($vendedor->id_usuario, $subasta->id_subasta, $ruta, strlen($contenido));
                $subastas->resolverRevision($administrador, $subasta->id_subasta, 'Aceptada');

                return ['subasta' => $subasta->refresh(), 'vendedor' => $vendedor, 'pujadores' => $pujadores, 'escenario' => $escenario];
            });
        } catch (\Throwable $error) {
            if ($ruta !== null) {
                Storage::disk('public')->delete($ruta);
            }
            throw $error;
        }
    }

    public function ofertar(Subasta $subasta, User $usuario): object
    {
        $this->exigirEntorno();

        return DB::transaction(function () use ($subasta, $usuario) {
            // Leer el mínimo y registrar la oferta bajo el mismo bloqueo, incluso si alguien puja desde la web.
            $actual = Subasta::whereKey($subasta->id_subasta)->lockForUpdate()->firstOrFail();
            $mayor = $actual->pujas()->max('monto');
            $monto = $mayor === null ? (int) $actual->monto_inicial : (int) $mayor + $actual->incremento_puja;
            app(PujaService::class)->registrar($usuario->id_usuario, $actual->id_subasta, (string) $monto);

            return DB::table('puja')->where('id_subasta', $actual->id_subasta)->orderByDesc('id_puja')->first();
        });
    }

    public function segundosRestantes(Subasta $subasta): float
    {
        return (float) DB::selectOne('select extract(epoch from fecha_fin-clock_timestamp()) as segundos from subasta where id_subasta=?', [$subasta->id_subasta])->segundos;
    }

    public function finalizar(Subasta $subasta): object
    {
        $this->exigirEntorno();

        return DB::transaction(function () use ($subasta) {
            Subasta::whereKey($subasta->id_subasta)->lockForUpdate()->firstOrFail();
            if ($this->segundosRestantes($subasta) > 0) {
                throw new \RuntimeException('La subasta todavía no llegó a su hora de cierre.');
            }
            // El mismo finalizador que utiliza el planificador: sin modificar fechas ni saltarse triggers.
            DB::selectOne('select finalizar_subastas() as total');

            return app(OperacionService::class)->consulta()->where('o.id_subasta', $subasta->id_subasta)->firstOrFail();
        });
    }

    private function cuenta(string $producto, string $alias, string $nombres, string $apellidos): User
    {
        $correo = 'sim.'.$producto.'.'.$alias.'@subastaya.test';
        $usuario = User::where('correo', $correo)->first();
        if ($usuario === null) {
            $usuario = app(UsuarioService::class)->registrar([
                'nombre' => $nombres.' '.$apellidos, 'nombres' => $nombres, 'apellidos' => $apellidos,
                'correo' => $correo, 'password' => self::CONTRASENA, 'normas_version' => app(NormativaService::class)->version(),
            ]);
        }
        if (! $usuario->activo || $usuario->esAdministrador()) {
            throw new \RuntimeException('La cuenta '.$correo.' no puede participar. No se modificarán su estado ni sus roles.');
        }
        app(NormativaService::class)->aceptar($usuario->id_usuario, app(NormativaService::class)->version());

        return $usuario;
    }

    private function exigirEntorno(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Las simulaciones solo están disponibles en local o pruebas.');
        }
    }
}
