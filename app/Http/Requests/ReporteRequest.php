<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdministrador() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'desde' => $this->input('desde') ?: now()->startOfMonth()->subMonths(5)->toDateString(),
            'hasta' => $this->input('hasta') ?: now()->toDateString(),
        ]);
    }

    public function rules(): array
    {
        $common = [
            'desde' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:2100-12-31'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde', 'before_or_equal:2100-12-31'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];

        return $this->routeIs('reportes.subastas') ? [...$common,
            'categoria' => ['nullable', 'integer', 'exists:categoria,id_categoria'],
            'estado' => ['nullable', Rule::in(['Activa', 'Finalizada', 'Cancelada'])],
            'ubicacion' => ['nullable', 'string', 'max:150'],
        ] : [...$common,
            'ciudad' => ['nullable', 'string', 'max:100'],
            'actividad' => ['nullable', Rule::in(['publicadores', 'postores', 'sin_actividad'])],
            'valoracion' => ['nullable', 'numeric', 'min:1', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return ['hasta.after_or_equal' => 'La fecha inicial no puede ser posterior a la fecha final.'];
    }
}
