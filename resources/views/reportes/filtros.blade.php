<aside class="panel report-filters" aria-labelledby="filtros-titulo">
    <h2 id="filtros-titulo">Filtrar reporte</h2>
    <p class="muted">{{ match($tipo) { 'subastas'=>'Según la fecha de publicación.', 'ventas'=>'Según la fecha de cierre de la subasta. Importes al momento de consultar.', 'movimientos'=>'Según la fecha de registro del movimiento.', default=>'Periodo de registro y fecha de corte de los acumulados.' } }}</p>
    <form method="get" action="{{ route('reportes.'.$tipo) }}">
        <div class="report-filter-field"><label for="desde">Desde</label><input type="date" name="desde" id="desde" value="{{ old('desde', $filtros['desde']) }}" min="1900-01-01" max="2100-12-31" required></div>
        <div class="report-filter-field"><label for="hasta">Hasta</label><input type="date" name="hasta" id="hasta" value="{{ old('hasta', $filtros['hasta']) }}" min="1900-01-01" max="2100-12-31" required></div>
        <!-- <label for="q">{{ $tipo === 'subastas' ? 'Artículo o vendedor' : 'Nombre del usuario' }}</label> -->
        <!-- <input type="search" id="q" name="q" value="{{ old('q', $filtros['q'] ?? '') }}" maxlength="120" placeholder="Escribe un nombre…"> -->
        @if(in_array($tipo,['ventas','movimientos']))
            <div class="report-filter-field"><label for="q">{{ $tipo==='ventas' ? 'Artículo, comprador o vendedor' : 'Referencia, titular o artículo' }}</label><input id="q" name="q" type="search" maxlength="120" value="{{ old('q',$filtros['q'] ?? '') }}"></div>
        @endif
        @if($tipo==='ventas')
            <div class="report-filter-field"><label for="estado">Estado de la operación</label><select id="estado" name="estado"><option value="">Todos los estados</option>@foreach(['Pendiente','En revisión','Completada','Reembolsada'] as $estado)<option @selected(old('estado',$filtros['estado'] ?? '')===$estado)>{{ $estado }}</option>@endforeach</select></div>
        @elseif($tipo==='movimientos')
            @foreach(['subasta'=>'Subasta','operacion'=>'Operación'] as $clave=>$label)@if(!empty($filtros[$clave]))<input type="hidden" name="{{ $clave }}" value="{{ $filtros[$clave] }}"><p>{{ $label }} #{{ $filtros[$clave] }}. Restablece los filtros para consultar todos los movimientos.</p>@endif
            @endforeach
            <div class="report-filter-field"><label for="tipo_movimiento">Tipo de movimiento</label><select id="tipo_movimiento" name="tipo_movimiento"><option value="">Todos los tipos</option>@foreach(['Recarga','Reserva','Liberación','Devolución','Liquidación','Pago'] as $movimiento)<option @selected(old('tipo_movimiento',$filtros['tipo_movimiento'] ?? '')===$movimiento)>{{ $movimiento }}</option>@endforeach</select></div>
            <div class="report-filter-field"><label for="usuario">ID del titular (opcional)</label><input id="usuario" name="usuario" type="number" min="1" step="1" value="{{ old('usuario',$filtros['usuario'] ?? '') }}"><small>También puedes buscar su nombre en el campo superior.</small></div>
        @elseif ($tipo === 'subastas')
            <div class="report-filter-field"><label for="categoria">Categoría</label><select id="categoria" name="categoria"><option value="">Todas las categorías</option>
                @foreach ($categorias as $categoria)<option value="{{ $categoria->id_categoria }}" @selected(old('categoria', $filtros['categoria'] ?? '') == $categoria->id_categoria)>{{ $categoria->nombre_categoria }}</option>@endforeach
            </select></div>
            <div class="report-filter-field"><label for="estado">Estado actual</label><select id="estado" name="estado"><option value="">Todos los estados</option>
                @foreach (['Activa', 'Finalizada', 'Cancelada'] as $estado)<option @selected(old('estado', $filtros['estado'] ?? '') === $estado)>{{ $estado }}</option>@endforeach
            </select></div>
            <!-- <label for="ubicacion">Ubicación del encuentro</label><input id="ubicacion" name="ubicacion" value="{{ old('ubicacion', $filtros['ubicacion'] ?? '') }}" maxlength="150" placeholder="Ciudad, zona o lugar"> -->
        @else
            <div class="report-filter-field"><label for="ciudad">Ciudad del usuario</label><select id="ciudad" name="ciudad"><option value="">Todas las ciudades</option>
                @foreach ($ciudades as $ciudad)<option @selected(old('ciudad', $filtros['ciudad'] ?? '') === $ciudad->ciudad)>{{ $ciudad->ciudad }}</option>@endforeach
            </select></div>
            <div class="report-filter-field"><label for="actividad">Actividad</label><select id="actividad" name="actividad"><option value="">Todos los usuarios</option>
                @foreach (['publicadores' => 'Con publicaciones', 'postores' => 'Con pujas', 'sin_actividad' => 'Sin publicaciones ni pujas'] as $valor => $etiqueta)<option value="{{ $valor }}" @selected(old('actividad', $filtros['actividad'] ?? '') === $valor)>{{ $etiqueta }}</option>@endforeach
            </select></div>
            <div class="report-filter-field"><label for="valoracion">Valoración promedio mínima</label><select id="valoracion" name="valoracion"><option value="">Cualquier valoración</option>
                @foreach (range(1, 5) as $estrella)<option value="{{ $estrella }}" @selected(old('valoracion', $filtros['valoracion'] ?? '') == $estrella)>{{ $estrella }} {{ $estrella === 1 ? 'estrella' : 'estrellas' }}</option>@endforeach
            </select></div>
        @endif
        <button class="button primary full-width" type="submit">Consultar reporte</button>
        <a class="report-reset" href="{{ route('reportes.'.$tipo) }}">Restablecer filtros</a>
    </form>
</aside>
