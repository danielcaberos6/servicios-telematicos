<?php

namespace App\Http\Controllers;

use App\Models\Imagen;
use Illuminate\Support\Facades\Storage;

class ImagenController extends Controller
{
    public function show(Imagen $imagen)
    {
        abort_unless(Storage::disk('public')->exists($imagen->ruta), 404);

        return response()->file(Storage::disk('public')->path($imagen->ruta), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=86400']);
    }
}
