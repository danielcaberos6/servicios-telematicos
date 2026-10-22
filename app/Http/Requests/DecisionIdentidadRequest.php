<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecisionIdentidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdministrador() ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['Aprobada', 'Rechazada'])],
            'motivo' => ['required_if:decision,Rechazada', 'nullable', 'string', 'min:1', 'max:1000'],
            'revision_confirmada' => ['required_if:decision,Aprobada', 'accepted_if:decision,Aprobada'],
        ];
    }
}
