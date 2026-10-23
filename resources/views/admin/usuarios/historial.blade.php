@extends('plantillas.principal')
@section('title', 'Historial de cuenta')
@push('styles')<link rel="stylesheet" href="{{ asset('css/gestion.css') }}">@endpush
@section('content')
<section class="container section gestion-page gestion-historial">
    <a href="{{ route('admin.usuarios.index') }}" class="text-link">← Volver a usuarios</a>
    <h1>Historial de {{ $usuario->nombre }}</h1>
    <p>{{ $usuario->correo }}</p>
    <p class="muted">Consulta sus publicaciones, ofertas realizadas y cambios administrativos. Las publicaciones incluyen todos los estados de revisión.</p>
    <nav class="gestion-filtros" aria-label="Apartados del historial">
        <a class="text-link" href="#subastas-usuario">Subastas ({{ $subastas->total() }})</a>
        <a class="text-link" href="#pujas-usuario">Pujas ({{ $pujas->total() }})</a>
        <a class="text-link" href="#decisiones-cuenta">Decisiones administrativas</a>
    </nav>
    <section class="panel" id="subastas-usuario" aria-labelledby="subastas-titulo">
        <h2 id="subastas-titulo">Subastas de este usuario · {{ $subastas->total() }}</h2>
        <div class="gestion-tabla-scroll" tabindex="0" role="region" aria-label="Subastas del usuario">
            <table class="ranking-table gestion-tabla">
                <thead><tr><th scope="col">Artículo</th><th scope="col">Estado</th><th scope="col">Monto inicial</th><th scope="col">Pujas recibidas</th><th scope="col">Envío o publicación</th><th scope="col">Cierre</th></tr></thead>
                <tbody>
                    @forelse ($subastas as $subasta)
                        <tr>
                            <th scope="row"><a href="{{ route('admin.subastas.show', $subasta) }}">{{ $subasta->titulo }}</a></th>
                            <td>{{ $subasta->estado }}</td>
                            <td>Bs {{ number_format($subasta->monto_inicial, 2, ',', '.') }}</td>
                            <td>{{ $subasta->pujas_count }}</td>
                            <td>{{ $subasta->fecha_inicio->timezone('America/La_Paz')->format('d/m/Y H:i') }}</td>
                            <td>{{ $subasta->fecha_fin->timezone('America/La_Paz')->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="gestion-vacio">Este usuario todavía no tiene subastas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-paginacion :elementos="$subastas" />
    </section>
    <section class="panel" id="pujas-usuario" aria-labelledby="pujas-titulo">
        <h2 id="pujas-titulo">Pujas realizadas · {{ $pujas->total() }}</h2>
        <p class="muted">Cada fila corresponde a una oferta individual. Fechas y horas de Bolivia.</p>
        <div class="gestion-tabla-scroll" tabindex="0" role="region" aria-label="Pujas del usuario">
            <table class="ranking-table gestion-tabla">
                <thead><tr><th scope="col">Subasta</th><th scope="col">Monto ofertado</th><th scope="col">Fecha y hora</th><th scope="col">Estado de la subasta</th></tr></thead>
                <tbody>
                    @forelse ($pujas as $puja)
                        <tr>
                            <th scope="row"><a href="{{ route('admin.subastas.show', $puja->subasta) }}">{{ $puja->subasta->titulo }}</a></th>
                            <td>Bs {{ number_format($puja->monto, 2, ',', '.') }}</td>
                            <td>{{ $puja->fecha_puja->timezone('America/La_Paz')->format('d/m/Y H:i') }}</td>
                            <td>{{ $puja->subasta->estado }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="gestion-vacio">Este usuario todavía no ha realizado pujas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-paginacion :elementos="$pujas" />
    </section>
    <section class="panel" id="decisiones-cuenta" aria-labelledby="decisiones-titulo">
        <h2 id="decisiones-titulo">Decisiones administrativas</h2>
        <p class="muted">Últimas 100 decisiones, de la más reciente a la más antigua.</p>
        @forelse ($decisiones as $decision)
            <article class="review">
                <strong>{{ $decision->operacion === 'estado' ? 'Estado de cuenta' : 'Rol administrador' }}:
                    {{ $decision->valor_nuevo ? 'Habilitado' : 'Deshabilitado' }}</strong>
                <p>{{ $decision->revisor }} · {{ \Carbon\Carbon::parse($decision->fecha)->timezone('America/La_Paz')->format('d/m/Y H:i') }}</p>
            </article>
        @empty
            <p>No hay decisiones registradas para esta cuenta.</p>
        @endforelse
    </section>
</section>
@endsection
