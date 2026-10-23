@props(['src' => null, 'input' => null])
@once
    @push('styles')<link rel="stylesheet" href="{{ asset('css/visor-carnet.css') }}">@endpush
    @push('scripts')<script src="{{ asset('js/visor-carnet.js') }}" defer></script>@endpush
@endonce
<div class="document-viewer" data-document-viewer @if ($input) data-document-input="{{ $input }}" @endif>
    <div data-document-preview @if (!$src) hidden @endif>
        <img class="document-preview" @if ($src) src="{{ $src }}" @endif alt="Carnet para revisión de identidad" data-document-thumbnail>
        <button class="button secondary" type="button" data-document-open hidden>Ampliar carnet</button>
        @if ($input)<p class="muted">Revisa que los datos se lean bien. Esta vista previa todavía no envía tu carnet.</p>@endif
    </div>
    <p class="document-error" role="alert" data-document-error hidden></p>
    <dialog class="document-dialog" aria-label="Visor del carnet" data-document-dialog>
        <div class="document-toolbar">
            <strong>Carnet de identidad</strong>
            <button class="button secondary" type="button" data-document-close autofocus>Cerrar visor</button>
        </div>
        <div class="document-controls" role="group" aria-label="Controles de imagen">
            <button type="button" class="button secondary" data-document-out aria-label="Alejar carnet">−</button>
            <output aria-live="polite" data-document-zoom>100%</output>
            <button type="button" class="button secondary" data-document-in aria-label="Acercar carnet">+</button>
            <button type="button" class="button secondary" data-document-fit>Ajustar a pantalla</button>
            <button type="button" class="button secondary" data-document-rotate>Girar 90°</button>
        </div>
        <p class="document-help">Al ampliar, desplázate por la imagen con las barras de desplazamiento, el panel táctil o el dedo. Puedes cerrar con Esc.</p>
        <div class="document-viewport" tabindex="0" role="region" aria-label="Carnet ampliado; usa las flechas del teclado para desplazarte" data-document-viewport>
            <div class="document-stage"><div class="document-sheet" data-document-sheet>
                <img alt="Carnet ampliado para revisión" data-document-image>
            </div></div>
        </div>
    </dialog>
</div>
