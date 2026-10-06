<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user()->fresh('imagen');
        $reviews = DB::table('resenas')->join('usuario', 'usuario.id_usuario', '=', 'resenas.id_usuario_resenador')->where('id_usuario_resenado', $user->id_usuario)->select('resenas.*', 'usuario.nombre')->orderByDesc('fecha_creacion')->get();

        return view('profile.edit', compact('user', 'reviews'));
    }

    public function update(Request $request)
    {
        $request->merge(['correo' => mb_strtolower(trim((string) $request->input('correo')))]);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:254', Rule::unique('usuario', 'correo')->ignore($request->user()->id_usuario, 'id_usuario')],
            'biografia' => ['nullable', 'string', 'max:1000'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:25', 'regex:/^[+0-9()\s-]+$/'],
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
                $user->fill(collect($data)->only(['nombre', 'correo', 'biografia', 'ciudad', 'telefono'])->all());
                if ($request->filled('password')) {
                    $user->contrasena = $data['password'];
                }
                $user->save();
                if ($request->hasFile('foto') || $request->boolean('quitar_foto')) {
                    $old = $user->imagen?->ruta;
                    $user->imagen()->delete();
                    if ($request->hasFile('foto')) {
                        $path = $request->file('foto')->store('perfiles/'.$user->id_usuario, 'public');
                        $user->imagen()->create(['ruta' => $path, 'tamano' => $request->file('foto')->getSize()]);
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
