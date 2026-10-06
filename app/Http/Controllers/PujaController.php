<?php

namespace App\Http\Controllers;

use App\Models\Subasta;
use App\Services\OperacionesService;
use Illuminate\Http\Request;

class PujaController extends Controller
{
    public function store(Request $request, Subasta $subasta)
    {
        $data = $request->validate(['monto' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99']]);
        app(OperacionesService::class)->pujar($request->user()->id_usuario, $subasta->id_subasta, (string) $data['monto']);

        return back()->with('status', 'Tu oferta fue registrada.');
    }
}
