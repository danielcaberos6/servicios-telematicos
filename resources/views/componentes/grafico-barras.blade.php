@props(['datos', 'estrellas' => false])
@php($maximo = max(1, collect($datos)->max('cantidad')))
@if (collect($datos)->sum('cantidad') === 0)
    <p class="report-empty">Sin datos para el periodo seleccionado</p>
@else
    <div class="report-bars-scroll">
        <div class="report-bars" role="list" aria-label="{{ $estrellas ? 'Reseñas por cantidad de estrellas' : 'Subastas publicadas por mes' }}">
            @foreach ($datos as $dato)
                @php($etiqueta = $estrellas ? $dato['estrellas'].' ★' : \Carbon\Carbon::parse($dato['mes'].'-01')->translatedFormat('M y'))
                <div class="report-bar-column" role="listitem" aria-label="{{ $etiqueta }}: {{ $dato['cantidad'] }}">
                    <strong>{{ $dato['cantidad'] }}</strong>
                    <div class="report-bar-track" aria-hidden="true"><span style="height: {{ round($dato['cantidad'] / $maximo * 100, 2) }}%" @class(['star-bar' => $estrellas])></span></div>
                    <span>{{ $etiqueta }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
