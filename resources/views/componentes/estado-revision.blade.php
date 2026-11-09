@props(['subasta'])
@php($estadoRevision = $subasta->estado_subasta === 'Cancelada' ? 'Cancelada' : $subasta->estado_revision)
@php($clase = ['En revisión' => 'pending', 'Aceptada' => 'green', 'Rechazada' => 'danger', 'Cancelada' => 'danger'][$estadoRevision] ?? null)
<span {{ $attributes->class(['tag', 'revision-tag', $clase]) }}>{{ $estadoRevision }}</span>
