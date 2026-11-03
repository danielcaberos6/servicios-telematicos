@props(['subasta'])
@if ($subasta->relationLoaded('decisiones') && $subasta->decisiones->isNotEmpty())
    <section class="panel" aria-label="Historial de revisiones">
        <h2>Historial de revisiones</h2>
        @foreach ($subasta->decisiones as $decision)
            <div>
                <p><strong>{{ $decision->estado }}</strong>
                    @if ($decision->fecha_decision)
                        · <time datetime="{{ $decision->fecha_decision->toIso8601String() }}">{{ $decision->fecha_decision->timezone('America/La_Paz')->format('d/m/Y H:i:s') }}</time>
                    @endif
                </p>
            </div>
        @endforeach
    </section>
@endif
