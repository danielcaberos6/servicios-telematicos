<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InicioSesionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['correo' => mb_strtolower(trim((string) $this->input('correo')))]);
    }

    public function rules(): array
    {
        return [
            'correo' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
