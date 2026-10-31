<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FondosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FondosController extends Controller
{
    public function index(Request $request, FondosService $fondos)
    {
        $request->validate(['movimientos' => ['sometimes', 'integer', 'min:1'], 'recargas' => ['sometimes', 'integer', 'min:1']]);

        return response()->view('fondos.index', [
            'saldo' => $fondos->saldo($request->user()->id_usuario),
            'movimientos' => $fondos->movimientos($request->user()->id_usuario),
            'recargas' => $fondos->recargas($request->user()->id_usuario),
            'referencia' => (string) Str::uuid(), 'soloLectura' => false,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function saldo(Request $request, FondosService $fondos)
    {
        return response()->json($fondos->saldo($request->user()->id_usuario))->header('Cache-Control', 'no-store, private');
    }

    public function solicitar(Request $request, FondosService $fondos)
    {
        $datos = $request->validate([
            'referencia' => ['required', 'uuid'],
            'monto' => ['required', 'numeric', 'multiple_of:1', 'min:5', 'max:10000'],
        ], ['monto.multiple_of' => 'Ingresa bolivianos enteros, sin centavos.']);
        $recarga = $fondos->solicitar($request->user()->id_usuario, $datos['referencia'], (string) $datos['monto']);

        return redirect()->route('billetera.recarga', $recarga->id_recarga);
    }

    public function recarga(Request $request, string $recarga)
    {
        $registro = DB::table('recarga')->where('id_recarga', $recarga)->where('id_usuario', $request->user()->id_usuario)->first();
        abort_unless($registro, 404);

        return response()->view('fondos.recarga', ['recarga' => $registro])->header('Cache-Control', 'no-store, private');
    }

    public function resolver(Request $request, string $recarga, FondosService $fondos)
    {
        $datos = $request->validate(['resultado' => ['required', Rule::in(['Aprobada', 'Rechazada', 'Cancelada'])]]);
        $resultado = $fondos->resolver($request->user()->id_usuario, $recarga, $datos['resultado']);

        return redirect()->route('billetera.recarga', $recarga)->with('status', 'Recarga '.$resultado->estado.'.');
    }

    public function control(Request $request, FondosService $fondos, ?User $usuario = null)
    {
        if ($usuario) {
            return response()->view('fondos.index', [
                'saldo' => $fondos->saldo($request->user()->id_usuario, $usuario->id_usuario),
                'movimientos' => $fondos->movimientos($request->user()->id_usuario, $usuario->id_usuario),
                'recargas' => $fondos->recargas($request->user()->id_usuario, $usuario->id_usuario),
                'soloLectura' => true, 'titular' => $usuario,
            ])->header('Cache-Control', 'no-store, private');
        }
        $datos = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $usuarios = User::query()->select('usuario.id_usuario', 'usuario.nombre', 'usuario.correo')
            ->selectRaw('(select disponible from saldo_billetera(usuario.id_usuario)) as disponible')
            ->selectRaw('(select reservado from saldo_billetera(usuario.id_usuario)) as reservado')
            ->when($datos['q'] ?? null, fn ($q, $texto) => $q->where(fn ($q) => $q->where('nombre', 'ilike', '%'.$texto.'%')->orWhere('correo', 'ilike', '%'.$texto.'%')))
            ->orderBy('nombre')->orderBy('id_usuario')->paginate(15)->withQueryString();

        return response()->view('fondos.control', compact('usuarios'))->header('Cache-Control', 'no-store, private');
    }
}
