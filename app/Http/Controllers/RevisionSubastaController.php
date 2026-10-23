<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecisionRevisionSubastaRequest;
use App\Models\Subasta;
use App\Services\RevisionSubastaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RevisionSubastaController extends Controller
{
    public function __construct(
        private RevisionSubastaService $revisionService
    ) {}

    public function index(Request $request)
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(['En revisión', 'Aceptada', 'Rechazada', 'todos'])],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ]);

        $estado = $filtros['estado'] ?? 'En revisión';
        $subastas = $this->revisionService->listar($filtros);

        return response()
            ->view('admin.subastas.index', compact('subastas', 'estado'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Subasta $subasta)
    {
        $subasta->load(['imagenes', 'categoria', 'usuario.imagen', 'revisor', 'decisiones.revisor'])->loadCount('pujas')->loadMax('pujas', 'monto');

        return response()
            ->view('admin.subastas.show', compact('subasta'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function resolver(DecisionRevisionSubastaRequest $request, Subasta $subasta)
    {
        $decision = $request->validated('decision');
        $motivo = $request->validated('motivo');

        $this->revisionService->resolver(
            $request->user()->id_usuario,
            $subasta->id_subasta,
            $decision,
            $motivo
        );

        $mensaje = $decision === 'Aceptada'
            ? 'La subasta fue aceptada y publicada correctamente.'
            : 'La subasta fue rechazada y se notificó al vendedor con el motivo.';

        return redirect()
            ->route('admin.subastas.show', $subasta)
            ->with('status', $mensaje);
    }
}
