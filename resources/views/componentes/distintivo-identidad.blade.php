@props(['usuario'])
<span class="tag" aria-label="Estado de identidad: {{ $usuario->identidad_verificada ? 'Verificado' : 'No verificado' }}">{{ $usuario->identidad_verificada ? '✓ Verificado' : 'No verificado' }}</span>
