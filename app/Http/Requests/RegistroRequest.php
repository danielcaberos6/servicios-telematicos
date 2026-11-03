<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistroRequest extends FormRequest
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
        return [
            'nombre' => ['required', 'string', 'max:201'],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:254', 'unique:usuario,correo'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'normas_version' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return ['normas_version.required' => 'Recarga el formulario para consultar las normas vigentes antes de crear tu cuenta.'];
    }
}
