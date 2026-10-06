<?php

namespace App\Http\Controllers;

use App\Models\Subasta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PujaController extends Controller
{
    public function store(Request $request, Subasta $subasta)
    {
        $data = $request->validate(['monto' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99']]);
        DB::transaction(function () use ($request, $subasta, $data) {
            $locked = Subasta::whereKey($subasta->id_subasta)->lockForUpdate()->firstOrFail();
            if ($locked->estado !== 'Activa') {
                throw ValidationException::withMessages(['monto' => 'Esta subasta ya no admite ofertas.']);
            }
            if ($locked->id_usuario === $request->user()->id_usuario) {
                abort(403, 'No puedes pujar en tu propia subasta.');
            }
            $highest = $locked->pujas()->max('monto');
            // Céntimos enteros: evitar errores de coma flotante en las comparaciones.
            $cents = (int) round((float) $data['monto'] * 100);
            $minimum = $highest === null ? (int) round((float) $locked->monto_inicial * 100) : (int) round((float) $highest * 100) + 1;
            if ($cents < $minimum) {
                throw ValidationException::withMessages(['monto' => 'La oferta mínima es Bs '.number_format($minimum / 100, 2, '.', '').'.']);
            }
            $locked->pujas()->create(['id_usuario' => $request->user()->id_usuario, 'monto' => $data['monto']]);
        });

        return back()->with('status', 'Tu oferta fue registrada.');
    }
}
