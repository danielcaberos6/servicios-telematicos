<?php

namespace App\Http\Requests;

use App\Models\Subasta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiltroSubastasRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'categoria' => ['nullable', 'integer', 'exists:categoria,id_categoria'],
            'estado' => ['nullable', Rule::in(Subasta::CONDITIONS)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...($this->filled('desde') ? ['after_or_equal:desde'] : [])],
            'min' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max' => ['nullable', 'numeric', 'max:9999999999.99', 'min:0', ...($this->filled('min') ? ['gte:min'] : [])],
            'ubicacion' => ['nullable', 'string', 'max:250'],
            'orden' => ['nullable', Rule::in(['recientes', 'finalizan', 'precio_asc', 'precio_desc'])],
        ];
    }
}
