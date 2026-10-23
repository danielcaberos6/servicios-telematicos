<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecisionRevisionSubastaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdministrador() ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['Aceptada', 'Rechazada'])],
            'motivo' => ['required_if:decision,Rechazada', 'nullable', 'string', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Debes indicar una decisión válida.',
            'decision.in' => 'La decisión debe ser Aceptada o Rechazada.',
            'motivo.required_if' => 'El rechazo requiere un motivo explicativo.',
            'motivo.min' => 'El motivo debe tener al menos 1 caracter.',
            'motivo.max' => 'El motivo no puede exceder los 1000 caracteres.',
        ];
    }
}
