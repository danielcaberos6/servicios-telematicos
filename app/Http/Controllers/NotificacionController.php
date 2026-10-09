<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiltroNotificacionesRequest;
use App\Models\Notificacion;
use App\Services\NotificacionService;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function __construct(
        private NotificacionService $notificacionService,
    ) {}

    public function index(FiltroNotificacionesRequest $request)
    {
        $filters = $request->validated();

        return view('notificaciones.index', ['notificaciones' => $this->notificacionService->listar($request->user()->id_usuario, $filters)]);
    }

    public function leer(Request $request, Notificacion $notificacion)
    {
        abort_unless($notificacion->id_usuario === $request->user()->id_usuario, 403);
        $this->notificacionService->leer($request->user()->id_usuario, $notificacion->id_notificacion);

        return back()->with('status', 'Notificación marcada como leída.');
    }

    public function leerTodas(Request $request)
    {
        $this->notificacionService->leerTodas($request->user()->id_usuario);

        return back()->with('status', 'Todas tus notificaciones están leídas.');
    }

    public function destroy(Request $request, Notificacion $notificacion)
    {
        abort_unless($notificacion->id_usuario === $request->user()->id_usuario, 403);
        $this->notificacionService->eliminar($request->user()->id_usuario, $notificacion->id_notificacion);

        return back()->with('status', 'Notificación eliminada.');
    }
}
