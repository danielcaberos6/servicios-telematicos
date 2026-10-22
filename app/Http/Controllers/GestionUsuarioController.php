<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiltroUsuariosRequest;
use App\Services\GestionUsuarioService;

class GestionUsuarioController extends Controller
{
    public function index(FiltroUsuariosRequest $request, GestionUsuarioService $gestion)
    {
        $filtros = $request->validated();
        $usuarios = $gestion->listar($request->user()->id_usuario, $filtros);

        return $request->expectsJson()
            ? response()->json($usuarios)
            : view('admin.usuarios.index', compact('usuarios', 'filtros'));
    }
}
