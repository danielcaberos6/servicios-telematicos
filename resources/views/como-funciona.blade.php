@extends('plantillas.principal')
@section('title', 'Cómo funciona')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/como-funciona.css') }}">
@endpush
@section('content')
    <div class="container section guide-page">
        <header class="guide-intro">
            <p class="eyebrow">TUS PRIMEROS PASOS</p>
            <h1>Encuentra. Oferta. Gana.</h1>
            <p>Descubre cómo participar en SubastaYA y darle una nueva oportunidad a cada artículo.</p>
            <div class="button-row">
                <a class="button primary" href="{{ route('subastas.index') }}">Explorar subastas <x-icono nombre="arrow" /></a>
                @guest<a class="button secondary" href="{{ route('registro') }}">Crear una cuenta</a>@endguest
            </div>
        </header>

        <section aria-labelledby="comprar-titulo" class="guide-section">
            <h2 id="comprar-titulo">¿Buscas algo especial?</h2>
            <ol class="guide-steps">
                <li class="panel"><span class="guide-number" aria-hidden="true">01</span><h3>Explora y compara</h3><p>Consulta las subastas sin crear una cuenta. Busca por categoría, revisa las fotos, la descripción, el monto y el lugar de encuentro.</p></li>
                <li class="panel"><span class="guide-number" aria-hidden="true">02</span><h3>Crea tu cuenta y oferta</h3><p>Inicia sesión para participar. Comprueba cuánto falta para el cierre y confirma el monto: las ofertas registradas no pueden retirarse.</p></li>
                <li class="panel"><span class="guide-number" aria-hidden="true">03</span><h3>Consulta el resultado</h3><p>Al finalizar, la oferta más alta registrada gana. Si ganas, consulta los datos de contacto disponibles en el detalle para coordinar con el vendedor.</p></li>
            </ol>
        </section>

        <section aria-labelledby="vender-titulo" class="guide-section">
            <h2 id="vender-titulo">¿Tienes algo para vender?</h2>
            <ol class="guide-steps">
                <li class="panel"><span class="guide-number" aria-hidden="true">01</span><h3>Prepara tu publicación</h3><p>Con tu sesión iniciada, entra a Mis subastas y elige Crear subasta. Agrega fotos claras, una descripción fiel, la categoría, el monto inicial y la fecha de cierre.</p></li>
                <li class="panel"><span class="guide-number" aria-hidden="true">02</span><h3>Espera la revisión</h3><p>La solicitud queda En revisión antes de publicarse. Consulta su estado en Mis subastas. Si es rechazada, revisa el motivo y corrige lo necesario.</p></li>
                <li class="panel"><span class="guide-number" aria-hidden="true">03</span><h3>Coordina la entrega</h3><p>Sigue las ofertas hasta el cierre. Si hay un ganador, acuerden la entrega y el pago. Si no hay ofertas, la subasta termina sin ganador.</p></li>
            </ol>
        </section>

        <section class="panel guide-safety guide-section" aria-labelledby="seguridad-titulo">
            <x-icono nombre="shield" />
            <div><h2 id="seguridad-titulo">Participa con cuidado</h2>
                <ul>
                    <li>Revisa las fotos, la descripción y los datos del vendedor antes de ofertar.</li>
                    <li>El distintivo Verificado indica una revisión de identidad; no garantiza el estado del artículo ni la operación.</li>
                    <li>Acuerda un lugar público para el encuentro y revisa el artículo antes de pagar.</li>
                    <li>No compartas contraseñas ni códigos de acceso con otras personas.</li>
                </ul>
            </div>
        </section>

        <section class="guide-section guide-faq" aria-labelledby="preguntas-titulo">
            <h2 id="preguntas-titulo">Preguntas frecuentes</h2>
            <details><summary>¿Necesito una cuenta para ver las subastas?</summary><p>No. Puedes explorar el catálogo y sus filtros sin iniciar sesión. Para publicar u ofertar necesitas una cuenta activa.</p></details>
            <details><summary>¿Cómo se realizan la entrega y el pago?</summary><p>El vendedor y el ganador los coordinan directamente. SubastaYA no procesa el pago dentro de la plataforma.</p></details>
            <details><summary>¿Es obligatorio verificar mi identidad?</summary><p>La verificación es opcional. Puedes solicitarla desde tu perfil después de guardar tus nombres, apellidos y una foto donde se vea tu rostro. El carnet se envía de forma privada para revisión administrativa.</p></details>
            <details><summary>¿Puedo modificar una subasta publicada?</summary><p>Solo puedes editar una subasta activa que todavía no recibió ofertas. Al editarla, vuelve a revisión antes de publicarse nuevamente.</p></details>
        </section>
    </div>
@endsection
