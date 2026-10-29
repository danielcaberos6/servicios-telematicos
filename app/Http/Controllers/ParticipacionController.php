<?php

namespace App\Http\Controllers;

use App\Models\Subasta;
use App\Services\ParticipacionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParticipacionController extends Controller
{
    public function index(Request $request, ParticipacionService $servicio)
    {
        $datos = $request->validate([
            'apartado' => ['nullable', Rule::in(array_keys(ParticipacionService::APARTADOS))],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ]);

        return view('participaciones.index', ['subastas' => $servicio->listar($request->user()->id_usuario, $datos['apartado'] ?? null)]);
    }

    public function favoritos(Request $request, ParticipacionService $servicio)
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);

        return view('participaciones.favoritos', ['subastas' => $servicio->favoritos($request->user()->id_usuario)]);
    }

    public function guardar(Request $request, Subasta $subasta, ParticipacionService $servicio)
    {
        $servicio->guardar($request->user()->id_usuario, $subasta->id_subasta);

        return back()->with('status', 'Subasta guardada en tus favoritos.');
    }

    public function retirar(Request $request, Subasta $subasta, ParticipacionService $servicio)
    {
        $servicio->retirar($request->user()->id_usuario, $subasta->id_subasta);

        return back()->with('status', 'Subasta retirada de tus favoritos.');
    }
}
