<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistroRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:254', 'unique:usuario,correo'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ];
    }
}
