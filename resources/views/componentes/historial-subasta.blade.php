@props(['subasta'])
@if ($subasta->relationLoaded('decisiones') && $subasta->decisiones->isNotEmpty())
    <section class="panel" aria-label="Historial de revisiones">
        <h2>Historial de revisiones</h2>
        @foreach ($subasta->decisiones as $decision)
            <div>
                <p><strong>{{ $decision->estado }}</strong>
                    @if ($decision->fecha_decision)
                        · {{ $decision->revisor?->nombre }} (ID {{ $decision->id_revisor }})
                        · <time datetime="{{ $decision->fecha_decision->toIso8601String() }}">{{ $decision->fecha_decision->timezone('America/La_Paz')->format('d/m/Y H:i:s') }} (Bolivia)</time>
                    @else
                        · Decisión anterior sin registro de responsable ni fecha.
                    @endif
                </p>
                @if ($decision->motivo)<p><strong>Motivo:</strong> {{ $decision->motivo }}</p>@endif
            </div>
        @endforeach
    </section>
@endif
