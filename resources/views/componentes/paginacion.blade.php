@props(['items'])
@if ($items->hasPages())
    <nav class="pagination" aria-label="Paginación">
        @if ($items->onFirstPage())
        <span aria-disabled="true">← Anterior</span>@else<a href="{{ $items->previousPageUrl() }}" rel="prev">←
                Anterior</a>
        @endif
        <span>Página {{ $items->currentPage() }} de {{ $items->lastPage() }}</span>
        @if ($items->hasMorePages())
        <a href="{{ $items->nextPageUrl() }}" rel="next">Siguiente →</a>@else<span aria-disabled="true">Siguiente
                →</span>
        @endif
    </nav>
@endif
