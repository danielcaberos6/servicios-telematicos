<?php

namespace App\Services;

use App\Models\VerificacionIdentidad;
use App\Services\Concerns\EjecutaFuncionesSql;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class IdentidadService
{
    use EjecutaFuncionesSql;

    public function resolver(int $actor, int $id, string $decision, ?string $motivo): void
    {
        $this->ejecutar('select resolver_verificacion(?,?,?,?)', [$actor, $id, $decision, $motivo], 'identidad');
    }

    public function solicitar(int $actor, int $version, UploadedFile $documento): int
    {
        $ruta = $documento->store((string) $actor, 'identidad');
        try {
            return $this->ejecutar('select solicitar_verificacion(?,?,?,?) as id', [$actor, $version, $ruta, $documento->getSize()], 'identidad')[0]->id;
        } catch (\Throwable $exception) {
            Storage::disk('identidad')->delete($ruta);
            throw $exception;
        }
    }

    public function historial(int $usuario)
    {
        return VerificacionIdentidad::where('id_usuario', $usuario)->orderByDesc('id_verificacion')->paginate(5);
    }
}
