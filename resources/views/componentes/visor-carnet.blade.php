@props(['src' => null, 'input' => null, 'perfil' => false])
@php
    $tituloImagen = $perfil ? 'Foto de perfil' : 'Carnet de identidad';
    $nombreImagen = $perfil ? 'foto de perfil' : 'carnet';
    $descripcionImagen = $perfil ? 'Foto de perfil completa para revisión de identidad' : 'Carnet para revisión de identidad';
@endphp
@once
    @push('styles')<link rel="stylesheet" href="{{ asset('css/visor-carnet.css') }}">@endpush
    @push('scripts')<script src="{{ asset('js/visor-carnet.js') }}" defer></script>@endpush
@endonce
<div class="document-viewer" data-document-viewer data-document-label="{{ $perfil ? 'la foto de perfil' : 'el carnet' }}" @if ($input) data-document-input="{{ $input }}" @endif>
    <div data-document-preview @if (!$src) hidden @endif>
        <img @class(['document-preview', 'document-preview-profile' => $perfil]) @if ($src) src="{{ $src }}" @endif alt="{{ $descripcionImagen }}" data-document-thumbnail>
        <button class="button secondary" type="button" data-document-open hidden>Ampliar {{ $nombreImagen }}</button>
        @if ($input)<p class="muted">Revisa que los datos se lean bien. Esta vista previa todavía no envía tu carnet.</p>@endif
    </div>
    <p class="document-error" role="alert" data-document-error hidden></p>
    <dialog class="document-dialog" aria-label="{{ $perfil ? 'Visor de la foto de perfil' : 'Visor del carnet' }}" data-document-dialog>
        <div class="document-toolbar">
            <strong>{{ $tituloImagen }}</strong>
            <button class="button secondary" type="button" data-document-close autofocus>Cerrar visor</button>
        </div>
        <div class="document-controls" role="group" aria-label="Controles de imagen">
            <button type="button" class="button secondary" data-document-out aria-label="Alejar {{ $nombreImagen }}">−</button>
            <output aria-live="polite" data-document-zoom>100%</output>
            <button type="button" class="button secondary" data-document-in aria-label="Acercar {{ $nombreImagen }}">+</button>
            <button type="button" class="button secondary" data-document-fit>Ajustar a pantalla</button>
            <button type="button" class="button secondary" data-document-rotate>Girar 90°</button>
        </div>
        <p class="document-help">Al ampliar, desplázate por la imagen con las barras de desplazamiento, el panel táctil o el dedo. Puedes cerrar con Esc.</p>
        <div class="document-viewport" tabindex="0" role="region" aria-label="{{ $tituloImagen }}; usa las flechas del teclado para desplazarte" data-document-viewport>
            <div class="document-stage"><div class="document-sheet" data-document-sheet>
                <img alt="{{ $descripcionImagen }}" data-document-image>
            </div></div>
        </div>
    </dialog>
</div>
