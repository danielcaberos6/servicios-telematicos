@extends('plantillas.principal')
@section('title', 'Historial de cuenta')
@section('content')
<section class="container section">
    <a href="{{ route('admin.usuarios.index') }}" class="text-link">← Volver a usuarios</a>
    <h1>Historial de {{ $usuario->nombre }}</h1>
    <p>{{ $usuario->correo }}</p>
    <div class="panel">
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
    </div>
</section>
@endsection
