@props(['administrador' => false])
@php
    $modalidades = ['comprador' => ['search', 'Comprador'], 'subastador' => ['gavel', 'Subastador']];
    if ($administrador) {
        $modalidades['administrador'] = ['shield', 'Administrador'];
    }
@endphp
<fieldset class="mode-picker">
    <legend>¿Cómo quieres ingresar?</legend>
    @foreach ($modalidades as $valor => [$icono, $titulo])
        <label class="mode-option"><input type="radio" name="modalidad" value="{{ $valor }}" required
                @checked(old('modalidad') === $valor)><span><x-icono :nombre="$icono" />{{ $titulo }}</span></label>
    @endforeach
</fieldset>
