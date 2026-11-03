<?php

namespace App\Services;

use App\Services\Concerns\EjecutaFuncionesSql;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdjuntoConversacionService
{
    use EjecutaFuncionesSql;

    public function enviar(int $actor, int $operacion, string $referencia, string $texto, ?UploadedFile $archivo): void
    {
        $adjunto = null;
        if ($archivo) {
            $adjunto = $this->analizar($archivo);
            $adjunto['ruta'] = 'operaciones/'.$operacion.'/'.Str::uuid().'.'.$adjunto['extension'];
            unset($adjunto['extension']);
            Storage::disk('conversaciones')->put($adjunto['ruta'], file_get_contents($archivo->getRealPath()));
        }
        try {
            $id = $this->ejecutar('select enviar_mensaje_operacion(?,?,?::uuid,?,?::jsonb) as id',
                [$actor, $operacion, $referencia, $texto, $adjunto ? json_encode($adjunto, JSON_THROW_ON_ERROR) : null], 'operacion')[0]->id;
        } catch (\Throwable $e) {
            if ($adjunto) {
                Storage::disk('conversaciones')->delete($adjunto['ruta']);
            }
            throw $e;
        }
        // Un reintento conserva el mensaje original y descarta la copia del archivo.
        if ($adjunto && DB::table('mensaje_operacion')->where('id_mensaje', $id)->value('archivo_ruta') !== $adjunto['ruta']) {
            Storage::disk('conversaciones')->delete($adjunto['ruta']);
        }
    }

    private function analizar(UploadedFile $archivo): array
    {
        $rechazar = fn () => throw ValidationException::withMessages(['archivo' => 'Adjunta una foto JPG, PNG o WebP, o un audio MP3, M4A, OGG, WAV o WebM de hasta 5 MB.']);
        if (! $archivo->isValid() || $archivo->getSize() < 1 || $archivo->getSize() > 5242880) {
            $rechazar();
        }
        $ruta = $archivo->getRealPath();
        $mime = $archivo->getMimeType();
        $imagenes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (isset($imagenes[$mime])) {
            $dimensiones = @getimagesize($ruta);
            if (! $dimensiones || $dimensiones[0] * $dimensiones[1] > 16000000) {
                $rechazar();
            }
            $tipo = 'Foto';
            $extension = $imagenes[$mime];
        } else {
            $formatos = ['audio/mpeg' => ['mp3', 'audio/mpeg'], 'audio/mp4' => ['m4a', 'audio/mp4'],
                'video/mp4' => ['m4a', 'audio/mp4'], 'audio/x-m4a' => ['m4a', 'audio/mp4'],
                'audio/ogg' => ['ogg', 'audio/ogg'], 'application/ogg' => ['ogg', 'audio/ogg'],
                'audio/wav' => ['wav', 'audio/wav'], 'audio/x-wav' => ['wav', 'audio/wav'],
                'audio/webm' => ['webm', 'audio/webm'], 'video/webm' => ['webm', 'audio/webm']];
            if (! isset($formatos[$mime])) {
                $rechazar();
            }
            $info = (new \getID3)->analyze($ruta);
            if (! empty($info['error']) || empty($info['audio']) || ! empty($info['video'])) {
                $rechazar();
            }
            [$extension, $mime] = $formatos[$mime];
            $tipo = 'Audio';
        }

        return ['tipo' => $tipo, 'mime' => $mime, 'extension' => $extension,
            'bytes' => $archivo->getSize(), 'sha256' => hash_file('sha256', $ruta)];
    }
}
