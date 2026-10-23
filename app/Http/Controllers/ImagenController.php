<?php

namespace App\Http\Controllers;

use App\Models\Imagen;
use App\Models\Subasta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImagenController extends Controller
{
    public function show(Request $request, Imagen $imagen)
    {
        $subasta = $imagen->id_subasta ? Subasta::find($imagen->id_subasta) : null;
        abort_if($subasta && ! $subasta->visiblePara($request->user()), 404);
        abort_unless(Storage::disk('public')->exists($imagen->ruta), 404);

        $response = response()->file(Storage::disk('public')->path($imagen->ruta), ['X-Content-Type-Options' => 'nosniff']);
        // BinaryFileResponse se marca public por defecto: las fotos de solicitudes sin
        // publicar se declaran privadas y sin almacenamiento en cachés compartidas.
        if (! $subasta || $subasta->publicada()) {
            return $response->setPublic()->setMaxAge(86400);
        }
        $response->setPrivate()->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
