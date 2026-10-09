<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PujaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'monto' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
        ];
    }
}
