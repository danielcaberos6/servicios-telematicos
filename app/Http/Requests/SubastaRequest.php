<?php

namespace App\Http\Requests;

use App\Models\Subasta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubastaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'min:5', 'max:120'],
            'descripcion' => ['required', 'string', 'min:15', 'max:5000'],
            'id_categoria' => ['required', 'integer', 'exists:categoria,id_categoria'],
            'estado_articulo' => ['required', Rule::in(Subasta::CONDITIONS)],
            'ubicacion' => ['required', 'string', 'min:5', 'max:250'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
            'monto_inicial' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            // La fecha de publicación se fija en el servidor; la subasta inicia al publicarse.
            'fecha_fin' => ['required', 'date', 'after:now', 'before:'.now()->addYear()->toDateTimeString()],
            'imagenes' => ['nullable', 'array', 'max:5'],
            'imagenes.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'eliminar_imagenes' => ['nullable', 'array', 'max:5'],
            'eliminar_imagenes.*' => ['integer', 'distinct'],
        ];
    }
}
