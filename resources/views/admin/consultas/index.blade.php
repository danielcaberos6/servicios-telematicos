@extends('plantillas.principal')
@section('title', 'Moderación de consultas')
@push('styles')<link rel="stylesheet" href="{{ asset('css/consultas.css') }}">@endpush
@section('content')
<div class="container section consultas-moderacion">
    <header class="section-heading"><div><p class="eyebrow">REVISIÓN DE CONTENIDO PÚBLICO</p><h1 class="page-title">Consultas reportadas</h1><p>Revisa el contexto y registra una decisión con motivo. El historial original se conserva.</p></div></header>
    <nav class="consultas-filtros" aria-label="Estado de reportes">
        <a href="{{ route('admin.consultas.index') }}" @if($estado === 'pendientes') aria-current="page" @endif>Pendientes</a>
        <a href="{{ route('admin.consultas.index', ['estado' => 'resueltos']) }}" @if($estado === 'resueltos') aria-current="page" @endif>Resueltos</a>
    </nav>
    @forelse($reportes as $reporte)
        <article class="consulta-item">
            <h2>Reporte #{{ $reporte->id_reporte }} · {{ $reporte->titulo }}</h2>
            <p class="consulta-ayuda">Reportado por {{ $reporte->autor }} el {{ \Illuminate\Support\Carbon::parse($reporte->fecha_reporte)->timezone('America/La_Paz')->format('d/m/Y H:i') }} (Bolivia).</p>
            <p><strong>Pregunta:</strong></p><p class="consulta-texto">{{ $reporte->pregunta }}</p>
            @if($reporte->respuesta !== null)<p><strong>Respuesta del vendedor:</strong></p><p class="consulta-texto">{{ $reporte->respuesta }}</p>@endif
            <p><strong>Contenido reportado:</strong> {{ $reporte->tipo === 'pregunta' ? 'Pregunta' : 'Respuesta' }}</p>
            <p><strong>Motivo del reporte:</strong></p><p class="consulta-texto">{{ $reporte->motivo }}</p>
            <p><a href="{{ route('subastas.show', $reporte->id_subasta) }}#preguntas-articulo">Ver contexto en la publicación</a></p>
            @if($reporte->accion === null)
                <form class="consulta-form" method="post" action="{{ route('admin.consultas.moderar', $reporte->id_reporte) }}">
                    @csrf @method('PATCH') <input type="hidden" name="_reporte" value="{{ $reporte->id_reporte }}">
                    <label for="accion-{{ $reporte->id_reporte }}">Decisión</label>
                    <select id="accion-{{ $reporte->id_reporte }}" name="accion" required><option value="">Selecciona una decisión</option><option value="ocultar" @selected(old('_reporte') == $reporte->id_reporte && old('accion') === 'ocultar')>Ocultar el contenido reportado</option><option value="desestimar" @selected(old('_reporte') == $reporte->id_reporte && old('accion') === 'desestimar')>Desestimar y conservar visible</option></select>
                    <label for="resolucion-{{ $reporte->id_reporte }}">Motivo de la decisión</label>
                    <textarea id="resolucion-{{ $reporte->id_reporte }}" name="motivo_resolucion" rows="2" maxlength="500" required>{{ old('_reporte') == $reporte->id_reporte ? old('motivo_resolucion') : '' }}</textarea>
                    @if(old('_reporte') == $reporte->id_reporte) @error('motivo_resolucion')<p class="field-error" role="alert">{{ $message }}</p>@enderror @endif
                    <p class="consulta-ayuda">Ocultar una pregunta retira también su respuesta de la vista pública. Ocultar una respuesta conserva visible la pregunta. Los reportes pendientes del mismo contenido se resuelven juntos.</p>
                    <button class="button primary" type="submit">Registrar decisión</button>
                </form>
            @else
                <div class="consulta-respuesta"><p><strong>{{ $reporte->accion === 'ocultar' ? 'Contenido ocultado' : 'Reporte desestimado' }}</strong> por {{ $reporte->moderador }}, el {{ \Illuminate\Support\Carbon::parse($reporte->fecha_resolucion)->timezone('America/La_Paz')->format('d/m/Y H:i') }} (Bolivia).</p><p class="consulta-texto">{{ $reporte->motivo_resolucion }}</p></div>
            @endif
        </article>
    @empty
        <div class="consultas-vacio"><h2>{{ $estado === 'pendientes' ? 'No hay reportes pendientes' : 'Todavía no hay reportes resueltos' }}</h2><p>Las decisiones y sus motivos aparecerán en este historial.</p></div>
    @endforelse
    <x-paginacion :elementos="$reportes" />
</div>
@endsection
