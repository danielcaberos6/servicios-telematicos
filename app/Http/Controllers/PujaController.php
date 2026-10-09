<?php

namespace App\Http\Controllers;

use App\Http\Requests\PujaRequest;
use App\Models\Subasta;
use App\Services\PujaService;

class PujaController extends Controller
{
    public function __construct(
        private PujaService $pujaService,
    ) {}

    public function store(PujaRequest $request, Subasta $subasta)
    {
        $data = $request->validated();
        $this->pujaService->registrar($request->user()->id_usuario, $subasta->id_subasta, (string) $data['monto']);

        return back()->with('status', 'Tu oferta fue registrada.');
    }
}
