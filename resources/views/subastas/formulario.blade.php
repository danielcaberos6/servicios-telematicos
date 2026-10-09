@extends('plantillas.principal')
@section('title', $subasta->exists ? 'Editar subasta' : 'Crear subasta')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auction-form.css') }}">
@endpush
@section('content')
    <div class="container section publish-page">
        <a class="breadcrumb" href="{{ route('subastas.mias') }}">← Mis Subastas</a>
        <header class="publish-heading">
            <div>
                <p class="eyebrow">DALE UNA NUEVA OPORTUNIDAD</p>
                <h1 class="page-title">{{ $subasta->exists ? 'Editar subasta' : 'Crear subasta' }}</h1>
                <p>Presenta tu artículo con claridad y define las condiciones de tu subasta.</p>
            </div>
            <p class="publish-required"><span aria-hidden="true">*</span> Campos obligatorios</p>
        </header>

        <div class="form-layout publish-layout">
            <form class="auction-form publish-form" method="post"
                action="{{ $subasta->exists ? route('subastas.update', $subasta) : route('subastas.store') }}"
                enctype="multipart/form-data">
                @csrf
                @if ($subasta->exists)
                    @method('PUT')
                @endif

                <section class="panel publish-section" aria-labelledby="article-heading">
                    <div class="publish-section-heading">
                        <span class="section-number" aria-hidden="true">01</span>
                        <div>
                            <h2 id="article-heading">Acerca del artículo</h2>
                            <p>Cuenta qué ofreces y en qué estado se encuentra.</p>
                        </div>
                    </div>
                    <div class="publish-fields">
                        <div class="publish-field">
                            <label for="titulo">Título de la subasta <span class="required-mark">*</span></label>
                            <input id="titulo" name="titulo" value="{{ old('titulo', $subasta->titulo) }}"
                                required minlength="5" maxlength="120" placeholder="Ej. Cámara Canon EOS con lente 18-55 mm">
                        </div>
                        <div class="publish-field">
                            <label for="descripcion">Descripción <span class="required-mark">*</span></label>
                            <textarea id="descripcion" name="descripcion" rows="5" required minlength="15" maxlength="5000"
                                placeholder="Describe las características, los accesorios y cualquier detalle de uso.">{{ old('descripcion', $subasta->descripcion) }}</textarea>
                        </div>
                        <div class="form-grid">
                            <div class="publish-field">
                                <label for="id_categoria">Categoría <span class="required-mark">*</span></label>
                                <select id="id_categoria" name="id_categoria" required>
                                    <option value="">Selecciona una categoría</option>
                                    @foreach ($categorias as $cat)
                                        <option value="{{ $cat->id_categoria }}" @selected(old('id_categoria', $subasta->id_categoria) == $cat->id_categoria)>
                                            {{ $cat->nombre_categoria }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="publish-field">
                                <label for="estado_articulo">Estado del artículo <span class="required-mark">*</span></label>
                                <select id="estado_articulo" name="estado_articulo" required>
                                    <option value="">Selecciona el estado</option>
                                    @foreach (\App\Models\Subasta::CONDITIONS as $state)
                                        <option @selected(old('estado_articulo', $subasta->estado_articulo) === $state)>{{ $state }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="panel publish-section" aria-labelledby="photos-heading">
                    <div class="publish-section-heading">
                        <span class="section-number" aria-hidden="true">02</span>
                        <div>
                            <h2 id="photos-heading">Fotografías <span class="publish-optional">Opcional</span></h2>
                            <p>Las fotos claras ayudan a conocer mejor tu artículo.</p>
                        </div>
                    </div>
                    <div class="publish-photo-rules" id="photo-help">
                        <span>Hasta 5 fotos</span><span>JPG, PNG o WebP</span><span>Máximo 5 MB por foto</span>
                    </div>
                    @if ($subasta->exists && $subasta->imagenes->isNotEmpty())
                        <div class="existing-images publish-photo-grid">
                            @foreach ($subasta->imagenes as $img)
                                <label class="publish-photo-card">
                                    <img src="{{ $img->url }}" alt="Foto del artículo">
                                    <span><input type="checkbox" name="eliminar_imagenes[]" value="{{ $img->id_imagen }}"
                                            @checked(in_array($img->id_imagen, old('eliminar_imagenes', [])))>Eliminar foto</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <label class="upload-area publish-upload" for="imagenes">
                        <span class="publish-upload-icon"><x-icono nombre="image" /></span>
                        <strong>Selecciona las fotos de tu artículo</strong>
                        <span>La primera fotografía será la portada de la publicación.</span>
                        <input id="imagenes" name="imagenes[]" type="file" multiple accept="image/jpeg,image/png,image/webp"
                            data-preview="image-preview" aria-describedby="photo-help">
                    </label>
                    <div id="image-preview" class="image-preview publish-photo-grid" aria-live="polite"></div>
                </section>

                <section class="panel publish-section" aria-labelledby="conditions-heading">
                    <div class="publish-section-heading">
                        <span class="section-number" aria-hidden="true">03</span>
                        <div>
                            <h2 id="conditions-heading">Condiciones de la subasta</h2>
                            <p>Define el monto inicial, el cierre y el lugar de encuentro.</p>
                        </div>
                    </div>
                    <div class="publish-fields">
                        <div class="form-grid">
                            <div class="publish-field">
                                <label for="monto_inicial">Monto inicial (Bs) <span class="required-mark">*</span></label>
                                <input id="monto_inicial" name="monto_inicial" type="number" min="0.01" max="9999999999.99"
                                    step="0.01" value="{{ old('monto_inicial', $subasta->monto_inicial) }}" required
                                    placeholder="0.00" aria-describedby="amount-help">
                                <small id="amount-help">El precio desde el que comienzan las ofertas.</small>
                            </div>
                            <div class="publish-field">
                                <label for="fecha_fin">Fecha y hora de cierre <span class="required-mark">*</span></label>
                                <input id="fecha_fin" name="fecha_fin" type="datetime-local" required
                                    value="{{ old('fecha_fin', $subasta->fecha_fin?->format('Y-m-d\TH:i')) }}"
                                    min="{{ now()->format('Y-m-d\TH:i') }}" max="{{ now()->addYear()->format('Y-m-d\TH:i') }}"
                                    aria-describedby="deadline-help">
                                <small id="deadline-help">Hora de Bolivia (UTC-4). Inicia al publicar.</small>
                            </div>
                        </div>
                        <div class="publish-field">
                            <label for="ubicacion">Punto de encuentro <span class="required-mark">*</span></label>
                            <input id="ubicacion" name="ubicacion" value="{{ old('ubicacion', $subasta->ubicacion) }}"
                                required minlength="5" maxlength="250" placeholder="Ej. La Paz, Plaza Abaroa, entrada principal"
                                aria-describedby="location-help">
                            <small id="location-help">Indica la ciudad y un lugar público donde coordinarás la entrega y el pago.</small>
                        </div>
                    </div>
                </section>

                <div class="panel publish-actions">
                    <p><x-icono nombre="shield" /> Revisa los datos antes de {{ $subasta->exists ? 'guardar los cambios' : 'publicar' }}.</p>
                    <div class="form-footer">
                        <a class="button secondary" href="{{ $subasta->exists ? route('subastas.show', $subasta) : route('subastas.mias') }}">Cancelar</a>
                        <button class="button primary" type="submit"><x-icono nombre="gavel" />
                            {{ $subasta->exists ? 'Guardar cambios' : 'Publicar subasta' }}
                        </button>
                    </div>
                </div>
            </form>

            <aside class="form-aside publish-aside" aria-labelledby="publish-advice-heading">
                <div class="panel publish-advice">
                    <p class="eyebrow">ANTES DE PUBLICAR</p>
                    <h3 id="publish-advice-heading">Los detalles hacen la diferencia</h3>
                    <ul class="publish-tips">
                        <li><x-icono nombre="box" /><div><strong>Un título claro</strong><p>Identifica el artículo y menciona su característica principal.</p></div></li>
                        <li><x-icono nombre="image" /><div><strong>Fotos propias</strong><p>Muestra el artículo y sus detalles de uso con buena iluminación.</p></div></li>
                        <li><x-icono nombre="clock" /><div><strong>Condiciones claras</strong><p>Elige el monto y el cierre con cuidado. Con una oferta, ya no podrás editar ni eliminar la subasta.</p></div></li>
                    </ul>
                </div>
                <div class="info-note publish-meeting"><x-icono nombre="pin" />
                    <div><strong>Un lugar público para encontrarse</strong><p>El pago y la entrega se coordinan entre vendedor y ganador.</p></div>
                </div>
            </aside>
        </div>
    </div>
@endsection
