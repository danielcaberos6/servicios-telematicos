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
            'reenviar' => ['sometimes', 'accepted'],
            'descripcion' => ['required', 'string', 'min:15', 'max:5000'],
            'id_categoria' => ['required', 'integer', Rule::exists('categoria', 'id_categoria')->where(function ($query) {
                $query->where('habilitada', true);
                if ($this->route('subasta') instanceof Subasta && $this->route('subasta')->estado_revision !== 'Rechazada') {
                    $query->orWhere('id_categoria', $this->route('subasta')->id_categoria);
                }
            })],
            'estado_articulo' => ['required', Rule::in(Subasta::CONDITIONS)],
            'ubicacion' => ['required', 'string', 'min:5', 'max:250'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
            'monto_inicial' => $this->route('subasta')?->modelo_puja === 1
                ? ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99']
                : ['required', 'numeric', 'multiple_of:5', 'min:5', 'max:9999999995'],
            'incremento_puja' => ['sometimes', 'required', 'integer', Rule::in(Subasta::INCREMENTOS)],
            // La fecha de publicación se fija en el servidor; la subasta inicia al publicarse.
            'fecha_fin' => ['required', 'date', 'after:now', 'before:'.now()->addYear()->toDateTimeString()],
            'imagenes' => $this->route('subasta') instanceof Subasta
                ? ['nullable', 'array', 'max:5']
                : ['required', 'array', 'min:1', 'max:5'],
            'imagenes.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'eliminar_imagenes' => ['nullable', 'array', 'max:5'],
            'eliminar_imagenes.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto_inicial.multiple_of' => 'El monto inicial debe ser un múltiplo entero positivo de Bs 5.',
            'incremento_puja.in' => 'Elige un incremento de 5, 10, 20, 50 o 100 Bs.',
            'fecha_fin.after' => 'La fecha y hora de cierre deben ser posteriores al momento actual (hora de Bolivia).',
            'imagenes.required' => 'Debes subir al menos una foto del artículo para enviar la subasta a revisión.',
            'imagenes.min' => 'Debes subir al menos una foto del artículo.',
            'imagenes.max' => 'La subasta puede tener como máximo 5 fotos.',
            'latitud.required_with' => 'Falta la latitud del punto de encuentro. Vuelve a marcarlo en el mapa o quita las coordenadas.',
            'longitud.required_with' => 'Falta la longitud del punto de encuentro. Vuelve a marcarlo en el mapa o quita las coordenadas.',
        ];
    }
}
