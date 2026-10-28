<?php

namespace App\Http\Requests;

use App\Models\Subasta;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class FiltroSubastasRequest extends FormRequest
{
    public const FILTRO_RADIO = ['lat', 'lng', 'radio'];

    protected function prepareForValidation(): void
    {
        // Admite una categoría suelta (enlaces de la portada) o varias (categoria[]).
        if ($this->filled('categoria') && ! is_array($this->input('categoria'))) {
            $this->merge(['categoria' => [$this->input('categoria')]]);
        }
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'categoria' => ['nullable', 'array', 'max:50'],
            'categoria.*' => ['integer', 'distinct', 'exists:categoria,id_categoria'],
            'estado' => ['nullable', Rule::in(Subasta::CONDITIONS)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', ...($this->filled('desde') ? ['after_or_equal:desde'] : [])],
            'min' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max' => ['nullable', 'numeric', 'max:9999999999.99', 'min:0', ...($this->filled('min') ? ['gte:min'] : [])],
            'ubicacion' => ['nullable', 'string', 'max:250'],
            'lat' => ['nullable', 'required_with:lng,radio', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat,radio', 'numeric', 'between:-180,180'],
            'radio' => ['nullable', 'required_with:lat,lng', 'numeric', 'gt:0', 'max:150'],
            'orden' => ['nullable', Rule::in(['recientes', 'finalizan', 'precio_asc', 'precio_desc', 'cercanas'])],
        ];
    }

    public function messages(): array
    {
        $centro = 'Selecciona en el mapa el centro de la búsqueda por cercanía.';

        return [
            'lat.required_with' => $centro,
            'lng.required_with' => $centro,
            'lat.between' => 'La latitud del centro debe estar entre -90 y 90.',
            'lng.between' => 'La longitud del centro debe estar entre -180 y 180.',
            'radio.required_with' => 'Indica el radio de búsqueda en kilómetros.',
            'radio.gt' => 'El radio de búsqueda debe ser mayor que cero.',
            'radio.max' => 'El radio de búsqueda admite hasta 150 km.',
            'radio.numeric' => 'El radio de búsqueda debe ser un número de kilómetros.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        // Vuelve al catálogo sin los filtros inválidos; el filtro por radio se descarta
        // completo para que la nueva consulta no repita el error (sin bucles).
        $invalidos = array_keys($validator->errors()->messages());
        if (array_intersect($invalidos, self::FILTRO_RADIO)) {
            $invalidos = [...$invalidos, ...self::FILTRO_RADIO];
        }
        $this->redirect = route('subastas.index', Arr::except($this->query(), [...$invalidos, 'page']));

        parent::failedValidation($validator);
    }
}
