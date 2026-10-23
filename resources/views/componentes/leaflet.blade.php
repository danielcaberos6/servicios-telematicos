{{-- Carga Leaflet una sola vez por página; si el CDN falla, mapas.js muestra la alternativa textual. --}}
@once
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
        <link rel="stylesheet" href="{{ asset('css/mapas.css') }}">
    @endpush
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin="" defer></script>
        <script src="{{ asset('js/mapas.js') }}" defer></script>
    @endpush
@endonce
