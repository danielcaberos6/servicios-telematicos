<section class="panel spaced-heading" aria-labelledby="identidad-titulo">
    <h2 id="identidad-titulo">Verificación de identidad</h2>
    <p>Es opcional. Puedes usar tu cuenta sin enviar tu carnet.</p>
    @if ($user->identidad_verificada)
        <p><strong>Verificado</strong>. Tu identidad fue revisada por un administrador.</p>
    @elseif ($pendiente)
        <p><strong>En revisión</strong>. Tu perfil mantiene la etiqueta No verificado hasta que se apruebe la solicitud.</p>
    @elseif (!$user->nombres || !$user->apellidos)
        <p>Completa y guarda tus nombres y apellidos en Información personal antes de enviar el carnet.</p>
    @elseif (!$user->imagen)
        <p>Para solicitar la verificación de identidad, primero sube y guarda una foto de perfil donde se vea claramente tu rostro.</p>
        <a href="#foto">Subir foto de perfil</a>
    @else
        <form method="post" action="{{ route('identidad.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="version_identidad" value="{{ $user->version_identidad }}">
            <p><strong>Nombres:</strong> {{ $user->nombres }}<br><strong>Apellidos:</strong> {{ $user->apellidos }}</p>
            <p>El administrador comparará tus nombres y tu foto de perfil con el carnet. Tu rostro debe verse claramente en la foto de perfil.</p>
            <label for="carnet">Fotografía legible del carnet</label>
            <input id="carnet" name="carnet" type="file" accept="image/jpeg,image/png" required>
            <p class="muted">JPG o PNG de hasta 5 MB. Solo tú y los administradores activos pueden consultarlo. El documento no cambia tus datos de perfil.</p>
            <label class="checkbox-label"><input name="confirmacion" type="checkbox" value="1" required>Confirmo que mis nombres y apellidos coinciden con el carnet.</label>
            <button type="submit" class="button primary">Solicitar verificación</button>
        </form>
    @endif
    <h3 class="spaced-heading">Mis solicitudes</h3>
    @forelse ($solicitudes as $solicitud)
        <article class="review">
            <strong>{{ $solicitud->estado }}{{ !$solicitud->vigente ? ' · Sin vigencia' : '' }}</strong>
            <p>{{ $solicitud->fecha_envio->timezone('America/La_Paz')->format('d/m/Y H:i') }}</p>
            @if ($solicitud->motivo)<p>Motivo: {{ $solicitud->motivo }}</p>@endif
            @if ($solicitud->motivo_invalidacion)<p>{{ $solicitud->motivo_invalidacion }} Solicita otra revisión.</p>@endif
            <a href="{{ route('identidad.show', $solicitud) }}">Ver solicitud</a>
        </article>
    @empty<p class="muted">Todavía no enviaste solicitudes.</p>@endforelse
    <x-paginacion :elementos="$solicitudes" />
</section>
