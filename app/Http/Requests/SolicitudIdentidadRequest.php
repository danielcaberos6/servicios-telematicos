<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SolicitudIdentidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'carnet' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'confirmacion' => ['accepted'],
            'version_identidad' => ['required', 'integer', 'min:1'],
        ];
    }
}
