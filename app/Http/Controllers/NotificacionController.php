<?php

namespace App\Http\Controllers;

use App\Http\Requests\FiltroNotificacionesRequest;
use App\Models\Notificacion;
use App\Services\NotificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function destino(Request $request, Notificacion $notificacion)
    {
        abort_unless($notificacion->id_usuario === $request->user()->id_usuario, 404);
        if ($request->user()->esAdministrador()) {
            $caso = DB::table('incidencia_operacion as i')->join('operacion_subasta as o', 'o.id_operacion', '=', 'i.id_operacion')
                ->where('o.id_subasta', $notificacion->id_subasta)->orderByRaw("case when i.estado='Resuelta' then 1 else 0 end")->orderByDesc('i.id_incidencia')->value('i.id_incidencia');

            return redirect()->route($caso ? 'admin.operaciones.caso' : 'admin.operaciones.index', $caso ? [$caso] : [])->header('Cache-Control', 'no-store, private');
        }
        $op = DB::table('operacion_subasta')->where('id_subasta', $notificacion->id_subasta)
            ->where(fn ($q) => $q->where('id_comprador', $request->user()->id_usuario)->orWhere('id_vendedor', $request->user()->id_usuario))->value('id_operacion');

        return redirect()->route($op ? 'operaciones.show' : 'operaciones.index', $op ? [$op] : [])->header('Cache-Control', 'no-store, private');
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
