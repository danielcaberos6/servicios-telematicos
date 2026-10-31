<section class="ops-block" id="valoracion"><h2>Valorar esta experiencia</h2>
    @if($op->estado!=='Completada')<p class="muted">Podrás valorar a la otra parte cuando la entrega quede completada.</p>
    @elseif($valoracion->propia)
        <p>Tu valoración: <strong>{{ number_format($valoracion->propia->calificacion,0) }} / 5</strong>.</p>
        @if($valoracion->propia->comentario)<p class="reputation-comment">{{ $valoracion->propia->comentario }}</p>@endif
        <p class="muted">{{ $valoracion->publicada ? 'Las valoraciones de esta operación ya son públicas.' : 'Tu valoración está reservada. No se publica ni afecta los promedios hasta que ambos valoren o termine el plazo.' }}</p>
    @elseif($valoracion->puede)
        <p>Valora tu experiencia con {{ $comprador ? $op->vendedor : $op->comprador }}. Tienes hasta el {{ $valoracion->plazo->format('d/m/Y H:i') }} (Bolivia).</p>
        <p class="muted">La valoración se guarda una sola vez. Su contenido permanece reservado hasta que ambos valoren o se cumplan 14 días desde la entrega completada.</p>
        @error('valoracion')<p role="alert">{{ $message }}</p>@enderror
        <form class="ops-form" method="POST" action="{{ route('reputacion.valorar',$op->id_operacion) }}">@csrf
            <fieldset class="reputation-stars"><legend>Puntuación de la experiencia</legend>@for($i=1;$i<=5;$i++)<label><input type="radio" name="estrellas" value="{{ $i }}" required @checked((int)old('estrellas')===$i)> {{ $i }} {{ $i===1 ? 'estrella' : 'estrellas' }}</label>@endfor</fieldset>@error('estrellas')<p role="alert">{{ $message }}</p>@enderror
            <label for="comentario">Comentario (opcional)</label><textarea id="comentario" name="comentario" maxlength="1000" rows="4">{{ old('comentario') }}</textarea>@error('comentario')<p role="alert">{{ $message }}</p>@enderror<small>Hasta 1.000 caracteres. No incluyas datos personales, insultos ni amenazas.</small>
            <button class="button primary">Enviar valoración</button>
        </form>
    @else<p class="muted">Terminó el plazo de 14 días para enviar tu valoración.</p>@endif
    <p><a href="{{ route('participantes.show',$comprador ? $op->id_vendedor : $op->id_comprador) }}">Ver reputación de {{ $comprador ? $op->vendedor : $op->comprador }}</a></p>
</section>
