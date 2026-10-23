@props(['subasta'])
@php($clase = ['En revisión' => 'pending', 'Aceptada' => 'green', 'Rechazada' => 'danger'][$subasta->estado_revision] ?? null)
<span {{ $attributes->class(['tag', 'revision-tag', $clase]) }}>{{ $subasta->estado_revision }}</span>
