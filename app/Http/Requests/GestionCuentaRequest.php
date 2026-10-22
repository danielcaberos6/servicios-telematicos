<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GestionCuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdministrador() ?? false;
    }

    public function rules(): array
    {
        return ['operacion' => ['required', Rule::in(['estado', 'administrador'])], 'valor' => ['required', 'boolean']];
    }
}
