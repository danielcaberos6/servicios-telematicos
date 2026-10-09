@props(['elementos'])
@if ($elementos->hasPages())
    <nav class="pagination" aria-label="Paginación">
        @if ($elementos->onFirstPage())
        <span aria-disabled="true">← Anterior</span>@else<a href="{{ $elementos->previousPageUrl() }}" rel="prev">←
                Anterior</a>
        @endif
        <span>Página {{ $elementos->currentPage() }} de {{ $elementos->lastPage() }}</span>
        @if ($elementos->hasMorePages())
        <a href="{{ $elementos->nextPageUrl() }}" rel="next">Siguiente →</a>@else<span aria-disabled="true">Siguiente
                →</span>
        @endif
    </nav>
@endif
