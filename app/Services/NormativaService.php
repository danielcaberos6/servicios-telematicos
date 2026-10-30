<?php

namespace App\Services;

use App\Services\Concerns\EjecutaFuncionesSql;
use Illuminate\Support\Facades\DB;

class NormativaService
{
    use EjecutaFuncionesSql;

    public function version(): string
    {
        return DB::selectOne('select normas_version_vigente() as version')->version;
    }

    public function aceptada(int $usuario): bool
    {
        return DB::table('aceptacion_normas')->where('id_usuario', $usuario)->where('version', $this->version())->exists();
    }

    public function aceptar(int $usuario, string $version): void
    {
        $this->ejecutar('select aceptar_normas(?,?)', [$usuario, $version], 'version');
    }

    public function destino(?string $ruta): string
    {
        // Lista cerrada de rutas locales: nunca redirigir a una URL del formulario.
        return $ruta && preg_match('~^/(?:subastas/crear|subastas/[1-9][0-9]*(?:/editar)?)$~D', $ruta) ? $ruta : '/';
    }
}
