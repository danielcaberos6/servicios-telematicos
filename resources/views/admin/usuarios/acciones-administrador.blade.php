<section class="panel" id="acciones-administrador" aria-labelledby="acciones-titulo">
    <h2 id="acciones-titulo">Acciones realizadas · {{ $acciones->total() }}</h2>
    <p class="muted">De la más reciente a la más antigua. Fechas y horas de Bolivia.</p>
    <div class="gestion-tabla-scroll" tabindex="0" role="region" aria-label="Acciones del administrador">
        <table class="ranking-table gestion-tabla">
            <thead><tr><th scope="col">Acción</th><th scope="col">Cuenta o publicación</th><th scope="col">Resultado</th><th scope="col">Fecha y hora</th></tr></thead>
            <tbody>
                @forelse ($acciones as $accion)
                    @php
                        [$etiqueta, $ruta] = match ($accion->tipo) {
                            'cuenta' => ['Gestión de cuenta', route('admin.usuarios.historial', $accion->destino)],
                            'publicacion' => ['Revisión de publicación', route('admin.subastas.show', $accion->destino)],
                            'identidad' => ['Verificación de identidad', route('identidad.show', $accion->destino)],
                        };
                    @endphp
                    <tr>
                        <th scope="row">{{ $etiqueta }}</th>
                        <td><a href="{{ $ruta }}">{{ $accion->titulo }}</a></td>
                        <td>{{ $accion->resultado }}
                            @if ($accion->motivo)<p>{{ $accion->motivo }}</p>@endif
                        </td>
                        <td>{{ \Carbon\Carbon::parse($accion->fecha)->timezone('America/La_Paz')->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="gestion-vacio">Este administrador todavía no tiene acciones registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-paginacion :elementos="$acciones" />
</section>
