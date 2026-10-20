<aside class="panel report-filters" aria-labelledby="filtros-titulo">
    <h2 id="filtros-titulo">Filtrar reporte</h2>
    <p class="muted">{{ $tipo === 'subastas' ? 'Según la fecha de publicación.' : 'Periodo de registro y fecha de corte de los acumulados.' }}</p>
    <form method="get" action="{{ route('reportes.'.$tipo) }}">
        <label for="desde">Desde</label><input type="date" name="desde" id="desde" value="{{ old('desde', $filtros['desde']) }}" min="1900-01-01" max="2100-12-31" required>
        <label for="hasta">Hasta</label><input type="date" name="hasta" id="hasta" value="{{ old('hasta', $filtros['hasta']) }}" min="1900-01-01" max="2100-12-31" required>
        <!-- <label for="q">{{ $tipo === 'subastas' ? 'Artículo o vendedor' : 'Nombre del usuario' }}</label> -->
        <!-- <input type="search" id="q" name="q" value="{{ old('q', $filtros['q'] ?? '') }}" maxlength="120" placeholder="Escribe un nombre…"> -->
        @if ($tipo === 'subastas')
            <label for="categoria">Categoría</label><select id="categoria" name="categoria"><option value="">Todas las categorías</option>
                @foreach ($categorias as $categoria)<option value="{{ $categoria->id_categoria }}" @selected(old('categoria', $filtros['categoria'] ?? '') == $categoria->id_categoria)>{{ $categoria->nombre_categoria }}</option>@endforeach
            </select>
            <label for="estado">Estado actual</label><select id="estado" name="estado"><option value="">Todos los estados</option>
                @foreach (['Activa', 'Finalizada', 'Cancelada'] as $estado)<option @selected(old('estado', $filtros['estado'] ?? '') === $estado)>{{ $estado }}</option>@endforeach
            </select>
            <!-- <label for="ubicacion">Ubicación del encuentro</label><input id="ubicacion" name="ubicacion" value="{{ old('ubicacion', $filtros['ubicacion'] ?? '') }}" maxlength="150" placeholder="Ciudad, zona o lugar"> -->
        @else
            <label for="ciudad">Ciudad del usuario</label><select id="ciudad" name="ciudad"><option value="">Todas las ciudades</option>
                @foreach ($ciudades as $ciudad)<option @selected(old('ciudad', $filtros['ciudad'] ?? '') === $ciudad->ciudad)>{{ $ciudad->ciudad }}</option>@endforeach
            </select>
            <label for="actividad">Actividad</label><select id="actividad" name="actividad"><option value="">Todos los usuarios</option>
                @foreach (['publicadores' => 'Con publicaciones', 'postores' => 'Con pujas', 'sin_actividad' => 'Sin publicaciones ni pujas'] as $valor => $etiqueta)<option value="{{ $valor }}" @selected(old('actividad', $filtros['actividad'] ?? '') === $valor)>{{ $etiqueta }}</option>@endforeach
            </select>
            <label for="valoracion">Valoración promedio mínima</label><select id="valoracion" name="valoracion"><option value="">Cualquier valoración</option>
                @foreach (range(1, 5) as $estrella)<option value="{{ $estrella }}" @selected(old('valoracion', $filtros['valoracion'] ?? '') == $estrella)>{{ $estrella }} {{ $estrella === 1 ? 'estrella' : 'estrellas' }}</option>@endforeach
            </select>
        @endif
        <button class="button primary full-width" type="submit">Consultar reporte</button>
        <a class="report-reset" href="{{ route('reportes.'.$tipo) }}">Restablecer filtros</a>
    </form>
</aside>
