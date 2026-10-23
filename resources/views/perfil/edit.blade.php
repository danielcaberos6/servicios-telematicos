@extends('plantillas.principal')
@section('title', 'Mi perfil')
@section('content')
    <div class="container section">
        <div class="section-heading">
            <div>
                <p class="eyebrow">TU ESPACIO</p>
                <h1 class="page-title">Mi perfil</h1>
                <p>Mantén tus datos al día para coordinar tus transacciones.</p>
            </div>
        </div>
        <div class="profile-summary">
            @if ($user->imagen)
                <img class="avatar" src="{{ $user->imagen->url }}" alt="Tu foto de perfil">
            @else
                <div class="avatar">{{ mb_strtoupper(mb_substr($user->nombre, 0, 1)) }}</div>
            @endif
            <div class="profile-summary-identity">
                <h2>{{ $user->nombre }}</h2>
                <p>{{ $user->ciudad ?: 'Agrega tu ciudad' }}</p>
                <p><x-distintivo-identidad :usuario="$user" /></p>
            </div>
            <div class="profile-summary-details">
                <p class="muted">Miembro desde {{ $user->fecha_registro->translatedFormat('F Y') }}</p>
                <div class="profile-stat"><strong>{{ $user->subastas()->count() }}</strong><span>subastas publicadas</span></div>
                <div class="profile-stat"><strong>{{ $reviews->isNotEmpty() ? number_format($reviews->avg('calificacion'), 1) : '—' }}</strong><span>{{ $reviews->count() }} reseñas recibidas</span></div>
            </div>
        </div>
                <form class="profile-form" method="post" action="{{ route('perfil.update') }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
<section id="informacion" data-apartado aria-labelledby="informacion-titulo"><h2 id="informacion-titulo">Información personal</h2>
                    @if (!$user->nombres || !$user->apellidos)
                        <p class="muted">Tu nombre registrado es {{ $user->nombre }}. Completa tus nombres y apellidos en los campos separados.</p>
                    @endif
                    <div class="form-grid">
                        <div><label for="nombres">Nombres *</label><input id="nombres" name="nombres" value="{{ old('nombres', $user->nombres) }}" required maxlength="100" autocomplete="given-name"></div>
                        <div><label for="apellidos">Apellidos *</label><input id="apellidos" name="apellidos" value="{{ old('apellidos', $user->apellidos) }}" required maxlength="100" autocomplete="family-name"></div>
                    </div>
                    <div class="form-grid">
                        <div><label for="telefono">Teléfono</label><input id="telefono" name="telefono" type="tel"
                                value="{{ old('telefono', $user->telefono) }}" maxlength="25" pattern="(?:[\+\(\)\s\-]*[0-9]){7,15}[\+\(\)\s\-]*"></div>
                    </div><label for="ciudad">Ciudad</label><input id="ciudad" name="ciudad"
                        value="{{ old('ciudad', $user->ciudad) }}" maxlength="100"><label for="biografia">Biografía</label>
                    <textarea id="biografia" name="biografia" rows="3" maxlength="1000">{{ old('biografia', $user->biografia) }}</textarea><label for="foto">Foto de perfil</label><input id="foto"
                        name="foto" type="file" accept="image/jpeg,image/png,image/webp"
                        data-preview="profile-preview"><small>JPG, PNG o WebP. Hasta 5 MB.</small>
                    <div id="profile-preview" class="image-preview"></div>
                    @if ($user->imagen)
                        <label class="checkbox-label"><input type="checkbox" name="quitar_foto" value="1">Quitar mi
                            foto actual</label>
                    @endif
<div class="form-footer"><button class="button primary" type="submit">Guardar perfil</button></div>
</section>
<section id="seguridad" data-apartado aria-labelledby="seguridad-titulo"><h2 id="seguridad-titulo">Seguridad</h2>
                        <div><label for="correo">Correo electrónico *</label><input id="correo" name="correo"
                                type="email" value="{{ old('correo', $user->correo) }}" required maxlength="254"></div>
                    <p class="muted">Para cambiar el correo o la contraseña, introduce tu contraseña actual.</p><label
                        for="current_password">Contraseña actual</label><input id="current_password" name="current_password"
                        type="password" autocomplete="current-password">
                    <div class="form-grid">
                        <div><label for="password">Nueva contraseña</label><input id="password" name="password"
                                type="password" minlength="8" maxlength="72" autocomplete="new-password"></div>
                        <div><label for="password_confirmation">Repetir nueva contraseña</label><input
                                id="password_confirmation" name="password_confirmation" type="password"
                                autocomplete="new-password"></div>
                    </div>
                    <div class="form-footer"><button class="button primary" type="submit">Guardar perfil</button></div>
</section>
                </form>
@unless ($user->esAdministrador())
<section id="identidad" data-apartado aria-label="Verificación de identidad">@include('identidad.perfil')</section>
@endunless
                <section class="panel" id="resenas" data-apartado aria-labelledby="resenas-titulo">
                    <h2 id="resenas-titulo">Reseñas y valoraciones</h2>
                    <section class="ratings-panel" aria-labelledby="ratings-title">
                    <h2 id="ratings-title">Mis valoraciones</h2>
                    <p class="rating-average"><strong>{{ $reviews->isNotEmpty() ? number_format($reviews->avg('calificacion'), 1) : '—' }}</strong><span> / 5 ★</span></p>
                    <p class="muted">{{ $reviews->count() }} {{ $reviews->count() === 1 ? 'valoración recibida' : 'valoraciones recibidas' }}</p>
                    <div class="ratings-chart" role="list" aria-label="Cantidad de valoraciones por estrellas">
                        @foreach ($ratings as $rating)
                            <div class="rating-column stars-{{ $rating->estrellas }}" role="listitem" aria-label="{{ $rating->estrellas }} estrellas: {{ $rating->cantidad }} valoraciones">
                                <span class="rating-count">{{ $rating->cantidad }}</span>
                                <div class="rating-track" aria-hidden="true"><div class="rating-bar" style="height: {{ round($rating->cantidad / max(1, $ratings->max('cantidad')) * 100) }}%"></div></div>
                                <span class="rating-label">{{ $rating->estrellas }} ★</span>
                            </div>
                        @endforeach
                    </div>
                    @if ($reviews->isEmpty())<p class="muted">Tus valoraciones aparecerán aquí cuando recibas reseñas.</p>@endif
                </section>

                    @forelse($reviews as $review)
                        <article class="review"><strong>{{ $review->nombre }}</strong><span class="rating">★
                                {{ number_format($review->calificacion, 1) }}/5</span>
                            <p>{{ $review->comentario }}</p>
                            <small>{{ \Carbon\Carbon::parse($review->fecha_creacion)->diffForHumans() }}</small>
                    </article>@empty<p class="muted">Todavía no recibiste reseñas.</p>
                    @endforelse
                </section>
    </div>
@endsection
