<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use App\Services\OperacionesService;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'solo_no_leidas' => ['nullable', 'boolean']]);

        return view('notifications.index', ['notificaciones' => app(OperacionesService::class)->notificaciones($request->user()->id_usuario, $filters)]);
    }

    public function read(Request $request, Notificacion $notificacion)
    {
        abort_unless($notificacion->id_usuario === $request->user()->id_usuario, 403);
        app(OperacionesService::class)->leerNotificacion($request->user()->id_usuario, $notificacion->id_notificacion);

        return back()->with('status', 'Notificación marcada como leída.');
    }

    public function readAll(Request $request)
    {
        app(OperacionesService::class)->leerTodas($request->user()->id_usuario);

        return back()->with('status', 'Todas tus notificaciones están leídas.');
    }

    public function destroy(Request $request, Notificacion $notificacion)
    {
        abort_unless($notificacion->id_usuario === $request->user()->id_usuario, 403);
        app(OperacionesService::class)->eliminarNotificacion($request->user()->id_usuario, $notificacion->id_notificacion);

        return back()->with('status', 'Notificación eliminada.');
    }
}
