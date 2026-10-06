<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'solo_no_leidas' => ['nullable', 'boolean']]);
        $query = $request->user()->notificaciones()->orderByDesc('fecha_notificacion')->orderByDesc('id_notificacion');
        if ($request->filled('q')) {
            $query->where(fn ($q) => $q->where('titulo', 'ilike', '%'.$filters['q'].'%')->orWhere('contenido', 'ilike', '%'.$filters['q'].'%'));
        }
        if ($request->boolean('solo_no_leidas')) {
            $query->where('leido', false);
        }

        return view('notifications.index', ['notificaciones' => $query->paginate(15)->withQueryString()]);
    }

    public function read(Request $request, Notificacion $notificacion)
    {
        abort_unless($notificacion->id_usuario === $request->user()->id_usuario, 403);
        $notificacion->update(['leido' => true]);

        return back()->with('status', 'Notificación marcada como leída.');
    }

    public function readAll(Request $request)
    {
        $request->user()->notificaciones()->where('leido', false)->update(['leido' => true]);

        return back()->with('status', 'Todas tus notificaciones están leídas.');
    }

    public function destroy(Request $request, Notificacion $notificacion)
    {
        abort_unless($notificacion->id_usuario === $request->user()->id_usuario, 403);
        $notificacion->delete();

        return back()->with('status', 'Notificación eliminada.');
    }
}
