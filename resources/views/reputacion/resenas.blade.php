@forelse($resenas as $resena)
    <article class="reputation-review">
        <div class="reputation-review-heading"><a href="{{ route('participantes.show',$resena->id_usuario_resenador) }}">{{ $resena->autor }}</a><strong>{{ number_format($resena->calificacion,1,',','.') }} / 5</strong></div>
        <p class="muted">{{ $historica ? 'Reseña histórica · sin operación verificada' : 'Operación verificada · como '.mb_strtolower($resena->rol_resenado) }} · <time>{{ \Carbon\Carbon::parse($resena->fecha_creacion)->timezone('America/La_Paz')->format('d/m/Y') }}</time></p>
        @if($resena->comentario_oculto)<p class="muted">Comentario oculto por moderación. La puntuación se conserva.</p>@elseif($resena->comentario)<p class="reputation-comment">{{ $resena->comentario }}</p>@else<p class="muted">Sin comentario.</p>@endif
        @if(auth()->check() && !auth()->user()->esAdministrador() && auth()->id()!==$resena->id_usuario_resenador && !$resena->comentario_oculto)
        <details class="reputation-report"><summary>Reportar contenido</summary><p>Reporta insultos, acoso o datos personales. Una puntuación baja por sí sola no justifica ocultar una reseña.</p><form class="ops-form" method="POST" action="{{ route('reputacion.reportar',$resena->id_resena) }}">@csrf
            <label for="categoria-{{ $resena->id_resena }}">Motivo del reporte</label><select id="categoria-{{ $resena->id_resena }}" name="categoria" required><option value="">Selecciona un motivo</option>@foreach(['Insultos','Acoso','Datos personales'] as $categoria)<option>{{ $categoria }}</option>@endforeach</select>
            <label for="detalle-{{ $resena->id_resena }}">Explica qué debemos revisar</label><textarea id="detalle-{{ $resena->id_resena }}" name="detalle" required minlength="5" maxlength="1000"></textarea><button class="button secondary">Enviar reporte</button>
        </form></details>@endif
    </article>
@empty<p class="muted">{{ $historica ? 'No hay reseñas históricas.' : 'Todavía no hay valoraciones publicadas de operaciones verificadas.' }}</p>@endforelse
<x-paginacion :elementos="$resenas" />
