<dl class="reputation-summary" aria-label="Reputación por rol">
    @foreach($resumen as $dato)<div><dt>Como {{ mb_strtolower($dato->rol) }}</dt><dd>{{ $dato->cantidad ? number_format($dato->promedio,2,',','.') .' / 5' : 'Sin valoraciones' }}</dd><p>{{ $dato->cantidad }} {{ $dato->cantidad===1 ? 'operación valorada' : 'operaciones valoradas' }}</p></div>@endforeach
</dl>
