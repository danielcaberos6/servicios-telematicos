<?php

namespace App\Http\Controllers;

use App\Services\NormativaService;
use Illuminate\Http\Request;

class NormativaController extends Controller
{
    public function __construct(private NormativaService $normativa) {}

    public function show(Request $request)
    {
        return view('normas', [
            'version' => $this->normativa->version(),
            'aceptada' => $request->user() && $this->normativa->aceptada($request->user()->id_usuario),
            'volver' => $this->normativa->destino(is_string($request->query('volver')) ? $request->query('volver') : null),
        ]);
    }

    public function aceptar(Request $request)
    {
        $datos = $request->validate([
            'acepto' => ['required', 'accepted'],
            'version' => ['required', 'string', 'max:50'],
            'volver' => ['nullable', 'string', 'max:150'],
        ], ['acepto.required' => 'Marca la casilla para aceptar las normas.', 'acepto.accepted' => 'Debes aceptar las normas para continuar.']);
        $this->normativa->aceptar($request->user()->id_usuario, $datos['version']);

        return redirect($this->normativa->destino($datos['volver'] ?? null))
            ->with('status', 'Aceptaste las normas vigentes. Puedes continuar; no se envió ninguna publicación ni oferta.');
    }
}
