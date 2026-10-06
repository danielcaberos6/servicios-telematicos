@extends('layouts.app')
@section('title', $subasta->exists ? 'Editar subasta' : 'Crear subasta')
@section('content')
    <div class="container section">
        <a class="breadcrumb" href="{{ route('auctions.mine') }}">← Mis Subastas</a>
        <div class="section-heading">
            <div>
                <p class="eyebrow">DALE UNA NUEVA OPORTUNIDAD</p>
                <h1 class="page-title">{{ $subasta->exists ? 'Editar subasta' : 'Crear subasta' }}</h1>
                <p>Cuéntanos qué ofreces. Los campos con * son obligatorios.</p>
            </div>
        </div>
        <div class="form-layout">
            <form class="panel auction-form" method="post"
                action="{{ $subasta->exists ? route('auctions.update', $subasta) : route('auctions.store') }}"
                enctype="multipart/form-data">@csrf @if ($subasta->exists)
                    @method('PUT')
                @endif
                <h2><span class="section-number">01</span> Acerca del artículo</h2>
                <label for="titulo">Título de la subasta *</label><input id="titulo" name="titulo"
                    value="{{ old('titulo', $subasta->titulo) }}" required minlength="5" maxlength="120"
                    placeholder="Ej. Cámara Canon EOS con lente 18–55 mm">
                <label for="descripcion">Descripción *</label>
                <textarea id="descripcion" name="descripcion" rows="5" required minlength="15" maxlength="5000"
                    placeholder="Describe las características, los accesorios y cualquier detalle de uso.">{{ old('descripcion', $subasta->descripcion) }}</textarea>
                <div class="form-grid">
                    <div><label for="id_categoria">Categoría *</label><select id="id_categoria" name="id_categoria"
                            required>
                            <option value="">Selecciona una categoría</option>
                            @foreach ($categorias as $cat)
                                <option value="{{ $cat->id_categoria }}" @selected(old('id_categoria', $subasta->id_categoria) == $cat->id_categoria)>
                                    {{ $cat->nombre_categoria }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><label for="estado_articulo">Estado del artículo *</label><select id="estado_articulo"
                            name="estado_articulo" required>
                            <option value="">Selecciona el estado</option>
                            @foreach (\App\Models\Subasta::CONDITIONS as $state)
                                <option @selected(old('estado_articulo', $subasta->estado_articulo) === $state)>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <h2><span class="section-number">02</span> Fotografías</h2>
                <p class="muted">Hasta 5 fotos JPG, PNG o WebP, de 5 MB cada una. La primera será la portada.</p>
                @if ($subasta->exists && $subasta->imagenes->isNotEmpty())
                    <div class="existing-images">
                        @foreach ($subasta->imagenes as $img)
                            <label><img src="{{ $img->url }}" alt="Foto del artículo"><span><input type="checkbox"
                                        name="eliminar_imagenes[]" value="{{ $img->id_imagen }}"
                                        @checked(in_array($img->id_imagen, old('eliminar_imagenes', [])))>Eliminar</span></label>
                        @endforeach
                    </div>
                @endif
                <label class="upload-area" for="imagenes"><x-icon name="image" /><strong>Selecciona las fotos de tu
                        artículo</strong><span>Las fotos claras ayudan a conocerlo mejor.</span><input id="imagenes"
                        name="imagenes[]" type="file" multiple accept="image/jpeg,image/png,image/webp"
                        data-preview="image-preview"></label>
                <div id="image-preview" class="image-preview" aria-live="polite"></div>
                <h2><span class="section-number">03</span> Condiciones de la subasta</h2>
                <div class="form-grid">
                    <div><label for="monto_inicial">Monto inicial (Bs) *</label><input id="monto_inicial"
                            name="monto_inicial" type="number" min="0.01" max="9999999999.99" step="0.01"
                            value="{{ old('monto_inicial', $subasta->monto_inicial) }}" required
                            placeholder="0.00"><small>El precio desde el que comienzan las ofertas.</small></div>
                    <div><label for="fecha_fin">Fecha y hora de cierre *</label><input id="fecha_fin" name="fecha_fin"
                            type="datetime-local" required
                            value="{{ old('fecha_fin', $subasta->fecha_fin?->format('Y-m-d\TH:i')) }}"
                            min="{{ now()->format('Y-m-d\TH:i') }}"
                            max="{{ now()->addYear()->format('Y-m-d\TH:i') }}"><small>Hora de Bolivia (UTC−4). Inicia al
                            publicar.</small></div>
                </div>
                <label for="ubicacion">Punto de encuentro *</label><input id="ubicacion" name="ubicacion"
                    value="{{ old('ubicacion', $subasta->ubicacion) }}" required minlength="5" maxlength="250"
                    placeholder="Ej. La Paz, Plaza Abaroa, entrada principal"><small>Indica la ciudad y un lugar público
                    donde te encontrarás con el ganador para realizar la entrega y el pago.</small>
                <div class="form-footer"><a class="button secondary"
                        href="{{ $subasta->exists ? route('auctions.show', $subasta) : route('auctions.mine') }}">Cancelar</a><button
                        class="button primary" type="submit"><x-icon
                            name="gavel" />{{ $subasta->exists ? 'Guardar cambios' : 'Publicar subasta' }}</button></div>
            </form>
            <aside class="form-aside">
                <div class="panel help-panel"><x-icon name="shield" />
                    <h3>Una buena publicación hace la diferencia</h3>
                    <p>Usa un título claro y fotos propias del artículo.</p>
                    <p>Menciona los detalles de uso y todo lo que incluye.</p>
                    <p>Elige el monto y la fecha con cuidado: cuando recibas una oferta, no podrás editar ni eliminar la
                        subasta.</p>
                </div>
                <div class="info-note"><x-icon name="pin" />
                    <p>El punto de encuentro es el lugar de la transacción. El pago y la entrega se coordinan entre vendedor
                        y ganador.</p>
                </div>
            </aside>
        </div>
    </div>
@endsection
