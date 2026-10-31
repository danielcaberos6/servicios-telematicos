<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerfilRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
        if (is_string($this->input('nombres')) && is_string($this->input('apellidos'))) {
            $this->merge(['nombre' => trim($this->input('nombres')).' '.trim($this->input('apellidos'))]);
        }
    }

    public function rules(): array
    {
        $usuario = $this->user();

        return [
            'nombre' => ['required', 'string', 'max:201'],
            'nombres' => ['nullable', 'required_with:apellidos', 'string', 'max:100'],
            'apellidos' => ['nullable', 'required_with:nombres', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:254', Rule::unique('usuario', 'correo')->ignore($usuario->id_usuario, 'id_usuario')],
            'biografia' => ['nullable', 'string', 'max:1000'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'ciudad_publica' => ['sometimes', 'boolean'],
            'telefono' => ['nullable', 'string', 'max:25', 'regex:/^[+0-9()\s-]+$/', 'regex:/^(?:[+()\s-]*[0-9]){7,15}[+()\s-]*$/'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'quitar_foto' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:72', 'confirmed'],
            'current_password' => [Rule::requiredIf($this->filled('password') || $this->input('correo') !== $usuario->correo), 'nullable', 'current_password'],
        ];
    }
}
