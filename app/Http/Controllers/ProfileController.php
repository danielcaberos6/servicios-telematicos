<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OperacionesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user()->fresh('imagen');
        $operations = app(OperacionesService::class);
        $reviews = $operations->resenas($user->id_usuario);
        $ratings = $operations->valoraciones($user->id_usuario);

        return view('profile.edit', compact('user', 'reviews', 'ratings'));
    }

    public function update(Request $request)
    {
        $request->merge(['correo' => mb_strtolower(trim((string) $request->input('correo')))]);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:254', Rule::unique('usuario', 'correo')->ignore($request->user()->id_usuario, 'id_usuario')],
            'biografia' => ['nullable', 'string', 'max:1000'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:25', 'regex:/^[+0-9()\s-]+$/', 'regex:/^(?:[+()\s-]*[0-9]){7,15}[+()\s-]*$/'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'quitar_foto' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:72', 'confirmed'],
            'current_password' => [Rule::requiredIf($request->filled('password') || $request->input('correo') !== $request->user()->correo), 'nullable', 'current_password'],
        ]);
        $path = null;
        $old = null;
        try {
            DB::transaction(function () use ($request, $data, &$path, &$old) {
                $user = User::whereKey($request->user()->id_usuario)->lockForUpdate()->firstOrFail();
                $operations = app(OperacionesService::class);
                $operations->actualizarUsuario($user->id_usuario, collect($data)->only(['nombre', 'correo', 'biografia', 'ciudad', 'telefono', 'password'])->all());
                if ($request->hasFile('foto') || $request->boolean('quitar_foto')) {
                    $old = $user->imagen?->ruta;
                    if ($user->imagen) {
                        $operations->eliminarImagen($user->id_usuario, $user->imagen->id_imagen);
                    }
                    if ($request->hasFile('foto')) {
                        $path = $request->file('foto')->store('perfiles/'.$user->id_usuario, 'public');
                        $operations->guardarImagen($user->id_usuario, null, $path, $request->file('foto')->getSize());
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
            try {
                Storage::disk('public')->delete($old);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'Tu perfil fue actualizado.');
    }
}
