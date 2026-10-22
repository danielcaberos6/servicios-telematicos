<?php

namespace App\Http\Controllers;

use App\Http\Requests\DecisionIdentidadRequest;
use App\Http\Requests\SolicitudIdentidadRequest;
use App\Models\VerificacionIdentidad;
use App\Services\IdentidadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class IdentidadController extends Controller
{
    public function index(Request $request)
    {
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(['En revisión', 'Aprobada', 'Rechazada', 'Invalidada', 'todos'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ]);
        $estado = $filtros['estado'] ?? 'En revisión';
        $solicitudes = VerificacionIdentidad::with('usuario')->when($estado !== 'todos', fn ($q) => $q->where('estado', $estado))
            ->orderBy('fecha_envio')->orderBy('id_verificacion')->paginate(15)->withQueryString();

        return response()->view('admin.identidad.index', compact('solicitudes', 'estado'))->header('Cache-Control', 'private, no-store');
    }

    public function resolver(DecisionIdentidadRequest $request, VerificacionIdentidad $verificacion, IdentidadService $identidad)
    {
        $identidad->resolver($request->user()->id_usuario, $verificacion->id_verificacion, $request->validated('decision'), $request->validated('motivo'));

        return redirect()->route('identidad.show', $verificacion)->with('status', 'La decisión fue registrada y el titular recibió una notificación.');
    }

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
