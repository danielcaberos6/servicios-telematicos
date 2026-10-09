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
        <div class="profile-layout">
            <aside>
                <div class="panel profile-summary">
                    @if ($user->imagen)
                    <img class="avatar" src="{{ $user->imagen->url }}" alt="Tu foto de perfil">@else<div class="avatar">
                            {{ mb_strtoupper(mb_substr($user->nombre, 0, 1)) }}</div>
                    @endif
                    <h2>
                        {{ $user->nombre }}</h2>
                    <p>{{ $user->ciudad ?: 'Agrega tu ciudad' }}</p>
                    <p class="muted">Miembro desde {{ $user->fecha_registro->translatedFormat('F Y') }}</p>
                    <div class="profile-stat"><strong>{{ $user->subastas()->count() }}</strong><span>subastas
                            publicadas</span></div>
                    <div class="profile-stat">
                        <strong>{{ $reviews->isNotEmpty() ? number_format($reviews->avg('calificacion'), 1) : '—' }}</strong><span>{{ $reviews->count() }}
                            reseñas recibidas</span></div>
                </div>
                <section class="panel ratings-panel spaced-heading" aria-labelledby="ratings-title">
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
            </aside>
            <div>
                <form class="panel" method="post" action="{{ route('perfil.update') }}" enctype="multipart/form-data">
                    @csrf @method('PUT')<h2>Información personal</h2><label for="nombre">Nombre completo
                        *</label><input id="nombre" name="nombre" value="{{ old('nombre', $user->nombre) }}" required
                        maxlength="100">
                    <div class="form-grid">
                        <div><label for="correo">Correo electrónico *</label><input id="correo" name="correo"
                                type="email" value="{{ old('correo', $user->correo) }}" required maxlength="254"></div>
                        <div><label for="telefono">Teléfono</label><input id="telefono" name="telefono" type="tel"
                                value="{{ old('telefono', $user->telefono) }}" maxlength="25"></div>
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
                    <h2 class="spaced-heading">
                        Seguridad</h2>
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
                </form>
                <div class="panel spaced-heading">
                    <h2>Reseñas y valoraciones</h2>
                    @forelse($reviews as $review)
                        <article class="review"><strong>{{ $review->nombre }}</strong><span class="rating">★
                                {{ number_format($review->calificacion, 1) }}/5</span>
                            <p>{{ $review->comentario }}</p>
                            <small>{{ \Carbon\Carbon::parse($review->fecha_creacion)->diffForHumans() }}</small>
                    </article>@empty<p class="muted">Todavía no recibiste reseñas.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
