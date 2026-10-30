@php
    $usuarioConsulta = auth()->user();
    $puedeConsultar = $usuarioConsulta && !$usuarioConsulta->esAdministrador();
    $abiertaConsulta = $subasta->estado === 'Activa';
    $esVendedorConsulta = $usuarioConsulta?->id_usuario === $subasta->id_usuario;
    $fechaConsulta = fn ($valor) => \Illuminate\Support\Carbon::parse($valor)->timezone('America/La_Paz')->format('d/m/Y H:i');
@endphp
<section class="container section consultas-section" id="preguntas-articulo" aria-labelledby="consultas-titulo">
    <div class="consultas-heading">
        <div><p class="eyebrow">ANTES DE OFERTAR</p><h2 id="consultas-titulo">Preguntas sobre el artículo</h2>
        <p>Las consultas son públicas. Pregunta por el estado, las características o los accesorios del artículo.</p></div>
        <span class="consultas-total">{{ $consultas->total() }} {{ $consultas->total() === 1 ? 'pregunta' : 'preguntas' }}</span>
    </div>
    @if (!$abiertaConsulta)
        <p class="info-note">La subasta {{ $subasta->estado === 'Cancelada' ? 'fue cancelada' : 'ya no está activa' }}. Las preguntas y respuestas se conservan para consulta; no se admiten nuevos mensajes.</p>
    @elseif (!$usuarioConsulta)
        <p><a href="{{ route('iniciar-sesion') }}">Inicia sesión</a> para hacer una pregunta al vendedor.</p>
    @elseif ($esVendedorConsulta)
        <p class="info-note">Estas son las dudas de las personas interesadas. Puedes responder una vez a cada pregunta mientras tu subasta siga activa.</p>
    @elseif ($puedeConsultar)
        <form class="consulta-form" method="post" action="{{ route('consultas.preguntar', $subasta) }}">
            @csrf
            <input type="hidden" name="clave_envio" value="{{ old('clave_envio', (string) \Illuminate\Support\Str::uuid()) }}">
            <label for="pregunta-articulo">Tu pregunta</label>
            <textarea id="pregunta-articulo" name="pregunta" rows="3" maxlength="1000" required aria-describedby="pregunta-ayuda{{ $errors->has('pregunta') ? ' pregunta-error' : '' }}" @error('pregunta') aria-invalid="true" @enderror>{{ old('pregunta') }}</textarea>
            <p class="consulta-ayuda" id="pregunta-ayuda">Hasta 1.000 caracteres. No incluyas teléfonos, correos ni documentos personales. Consulta nuestras <a href="{{ route('normas.show') }}">normas y políticas</a>.</p>
            @error('pregunta')<p class="field-error" id="pregunta-error">{{ $message }}</p>@enderror
            <button class="button primary" type="submit">Enviar pregunta</button>
        </form>
    @endif
    <div class="consultas-lista">
        @forelse ($consultas as $consulta)
            <article class="consulta-item" aria-labelledby="consulta-{{ $consulta->id_consulta }}">
                <div class="consulta-meta"><strong id="consulta-{{ $consulta->id_consulta }}">{{ $consulta->autor }}</strong><time datetime="{{ \Illuminate\Support\Carbon::parse($consulta->fecha_pregunta)->toIso8601String() }}">{{ $fechaConsulta($consulta->fecha_pregunta) }} · Bolivia</time></div>
                <p class="consulta-texto">{{ $consulta->pregunta }}</p>
                @if ($consulta->respuesta_oculta)
                    <p class="consulta-respuesta consulta-ayuda">La respuesta fue ocultada por moderación.</p>
                @elseif ($consulta->respuesta !== null)
                    <div class="consulta-respuesta"><div class="consulta-meta"><strong>{{ $consulta->vendedor }} <span class="consulta-vendedor">Vendedor</span></strong><time datetime="{{ \Illuminate\Support\Carbon::parse($consulta->fecha_respuesta)->toIso8601String() }}">{{ $fechaConsulta($consulta->fecha_respuesta) }} · Bolivia</time></div><p class="consulta-texto">{{ $consulta->respuesta }}</p></div>
                @elseif ($esVendedorConsulta && $puedeConsultar && $abiertaConsulta)
                    <details class="consulta-acciones" @if(old('_consulta') == $consulta->id_consulta && old('_accion') === 'responder') open @endif>
                        <summary>Responder esta pregunta</summary>
                        <form class="consulta-form" method="post" action="{{ route('consultas.responder', $consulta->id_consulta) }}">
                            @csrf <input type="hidden" name="_consulta" value="{{ $consulta->id_consulta }}"><input type="hidden" name="_accion" value="responder">
                            <label for="respuesta-{{ $consulta->id_consulta }}">Tu respuesta pública</label>
                            <textarea id="respuesta-{{ $consulta->id_consulta }}" name="respuesta" rows="3" maxlength="2000" required aria-describedby="respuesta-ayuda-{{ $consulta->id_consulta }}">{{ old('_consulta') == $consulta->id_consulta ? old('respuesta') : '' }}</textarea>
                            <p class="consulta-ayuda" id="respuesta-ayuda-{{ $consulta->id_consulta }}">Hasta 2.000 caracteres. Responde sobre el artículo, sin datos de contacto. Una respuesta publicada no puede editarse.</p>
                            @if(old('_consulta') == $consulta->id_consulta) @error('respuesta')<p class="field-error" role="alert">{{ $message }}</p>@enderror @endif
                            <button class="button primary" type="submit">Publicar respuesta</button>
                        </form>
                    </details>
                @else
                    <p class="consulta-ayuda">{{ $abiertaConsulta ? 'Todavía no hay respuesta del vendedor.' : 'La pregunta quedó sin respuesta al finalizar la participación.' }}</p>
                @endif
                @if ($puedeConsultar)
                    <details class="consulta-acciones" @if(old('_consulta') == $consulta->id_consulta && old('_accion') === 'reportar') open @endif>
                        <summary>Reportar contenido</summary>
                        <form class="consulta-form" method="post" action="{{ route('consultas.reportar', $consulta->id_consulta) }}">
                            @csrf <input type="hidden" name="_consulta" value="{{ $consulta->id_consulta }}"><input type="hidden" name="_accion" value="reportar">
                            <label for="tipo-{{ $consulta->id_consulta }}">Contenido a revisar</label>
                            <select id="tipo-{{ $consulta->id_consulta }}" name="tipo"><option value="pregunta">La pregunta</option>@if($consulta->respuesta !== null)<option value="respuesta" @selected(old('_consulta') == $consulta->id_consulta && old('tipo') === 'respuesta')>La respuesta</option>@endif</select>
                            <label for="motivo-{{ $consulta->id_consulta }}">Motivo del reporte</label>
                            <textarea id="motivo-{{ $consulta->id_consulta }}" name="motivo" maxlength="500" rows="2" required aria-describedby="reporte-ayuda-{{ $consulta->id_consulta }}">{{ old('_consulta') == $consulta->id_consulta ? old('motivo') : '' }}</textarea>
                            <p class="consulta-ayuda" id="reporte-ayuda-{{ $consulta->id_consulta }}">Indica si contiene insultos, spam o información personal. Hasta 500 caracteres. El motivo solo lo revisa administración.</p>
                            @if(old('_consulta') == $consulta->id_consulta) @error('motivo')<p class="field-error" role="alert">{{ $message }}</p>@enderror @endif
                            <button class="button secondary" type="submit">Enviar reporte</button>
                        </form>
                    </details>
                @endif
            </article>
        @empty
            <div class="consultas-vacio"><h3>Todavía no hay preguntas</h3><p>{{ $abiertaConsulta ? 'Las dudas sobre este artículo y las respuestas del vendedor aparecerán aquí.' : 'No hay consultas públicas para mostrar en esta subasta.' }}</p></div>
        @endforelse
    </div>
    <x-paginacion :elementos="$consultas" />
</section>
