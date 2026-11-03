<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PujaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'monto' => ['required', 'numeric', 'multiple_of:1', 'min:1', 'max:9999999999'],
        ];
    }

    public function messages(): array
    {
        return ['monto.multiple_of' => 'La oferta debe ser un monto entero, sin centavos.'];
    }
}
