<?php

namespace App\Http\Controllers;

use App\Http\Requests\PerfilRequest;
use App\Models\User;
use App\Services\ImagenService;
use App\Services\UsuarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PerfilController extends Controller
{
    public function __construct(
        private ImagenService $imagenService,
        private UsuarioService $usuarioService,
    ) {}

    public function edit(Request $request)
    {
        $user = $request->user()->fresh('imagen');
        $reviews = $this->usuarioService->resenas($user->id_usuario);
        $ratings = $this->usuarioService->valoraciones($user->id_usuario);

        return view('perfil.edit', compact('user', 'reviews', 'ratings'));
    }

    public function update(PerfilRequest $request)
    {
        $data = $request->validated();
        $path = null;
        $old = null;
        try {
            DB::transaction(function () use ($request, $data, &$path, &$old) {
                $user = User::whereKey($request->user()->id_usuario)->lockForUpdate()->firstOrFail();
                $this->usuarioService->actualizar($user->id_usuario, collect($data)->only(['nombre', 'correo', 'biografia', 'ciudad', 'telefono', 'password'])->all());
                if ($request->hasFile('foto') || $request->boolean('quitar_foto')) {
                    $old = $user->imagen?->ruta;
                    if ($user->imagen) {
                        $this->imagenService->eliminar($user->id_usuario, $user->imagen->id_imagen);
                    }
                    if ($request->hasFile('foto')) {
                        $path = $request->file('foto')->store('perfiles/'.$user->id_usuario, 'public');
                        $this->imagenService->guardar($user->id_usuario, null, $path, $request->file('foto')->getSize());
                    }
                }
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return back()->with('status', 'Tu perfil fue actualizado.');
    }
}
