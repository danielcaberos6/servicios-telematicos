<?php

namespace Database\Seeders\Concerns;

use App\Models\Subasta;
use Illuminate\Support\Facades\Storage;

trait CargaFotosDemo
{
    private function asegurarFoto(Subasta $subasta, string $archivo): void
    {
        if ($subasta->imagenes()->exists()) {
            return;
        }

        $contenido = file_get_contents(public_path('imagenes/ejemplos/'.$archivo));
        if ($contenido === false) {
            throw new \RuntimeException('No se encontró la foto de ejemplo: '.$archivo);
        }
        $ruta = 'subastas/'.$subasta->id_subasta.'/'.$archivo;
        Storage::disk('public')->put($ruta, $contenido);
        $subasta->imagenes()->create(['ruta' => $ruta, 'tamano' => strlen($contenido)]);
    }
}
