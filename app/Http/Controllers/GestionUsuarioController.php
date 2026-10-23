<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiltroUsuariosRequest;
use App\Http\Requests\GestionCuentaRequest;
use App\Models\User;
use App\Services\GestionUsuarioService;
use Illuminate\Http\Request;

class GestionUsuarioController extends Controller
{
    public function update(GestionCuentaRequest $request, User $usuario, GestionUsuarioService $gestion)
    {
        $gestion->cambiar($request->user()->id_usuario, $usuario->id_usuario, $request->validated('operacion'), $request->boolean('valor'));

        return back()->with('status', 'Los permisos de la cuenta fueron actualizados.');
    }

    public function historial(Request $request, User $usuario, GestionUsuarioService $gestion)
    {
        $request->validate([
            'publicaciones' => ['sometimes', 'integer', 'min:1'],
            'ofertas' => ['sometimes', 'integer', 'min:1'],
        ]);
        $decisiones = $gestion->historial($request->user()->id_usuario, $usuario->id_usuario);
        $actividad = $gestion->actividad($request->user()->id_usuario, $usuario->id_usuario);

        return view('admin.usuarios.historial', [...compact('usuario', 'decisiones'), ...$actividad]);
    }

    public function index(FiltroUsuariosRequest $request, GestionUsuarioService $gestion)
    {
        $filtros = $request->validated();
        $usuarios = $gestion->listar($request->user()->id_usuario, $filtros);

        return $request->expectsJson()
            ? response()->json($usuarios)
            : view('admin.usuarios.index', compact('usuarios', 'filtros'));
    }
}
