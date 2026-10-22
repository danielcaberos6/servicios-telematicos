<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltroUsuariosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdministrador() ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', Rule::in(['activo', 'inactivo'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
