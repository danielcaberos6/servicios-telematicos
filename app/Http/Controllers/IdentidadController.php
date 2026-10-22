<?php

namespace App\Http\Controllers;

use App\Http\Requests\SolicitudIdentidadRequest;
use App\Models\VerificacionIdentidad;
use App\Services\IdentidadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IdentidadController extends Controller
{
    public function store(SolicitudIdentidadRequest $request, IdentidadService $identidad)
    {
        $identidad->solicitar($request->user()->id_usuario, $request->integer('version_identidad'), $request->file('carnet'));

        return redirect()->route('perfil.edit')->with('status', 'Tu carnet fue enviado de forma privada. Tu identidad está en revisión.');
    }

    private function autorizar(Request $request, VerificacionIdentidad $verificacion): void
    {
        abort_unless($request->user()->id_usuario === $verificacion->id_usuario || $request->user()->esAdministrador(), 403);
    }

    public function show(Request $request, VerificacionIdentidad $verificacion)
    {
        $this->autorizar($request, $verificacion);
        $verificacion->load(['usuario.imagen', 'revisor']);

        return response()->view('identidad.show', compact('verificacion'))->header('Cache-Control', 'private, no-store');
    }

    public function documento(Request $request, VerificacionIdentidad $verificacion)
    {
        $this->autorizar($request, $verificacion);

        return Storage::disk('identidad')->response($verificacion->ruta_documento, null, [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox", 'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
