@extends('plantillas.principal')
@section('title', 'Normas y políticas')
@push('styles')<link rel="stylesheet" href="{{ asset('css/normas.css') }}">@endpush
@section('content')
<div class="container section normas-page">
    <header class="normas-intro">
        <p class="eyebrow">PARTICIPAR CON CONFIANZA</p>
        <h1>Normas claras.<br>Subastas responsables.</h1>
        <p>Conoce las reglas para publicar, ofertar y cuidar tu información en SubastaYA. Puedes leerlas sin crear una cuenta.</p>
        <p class="normas-version">Versión {{ $version }} · Publicada el 31 de octubre de 2026</p>
    </header>
    <div class="normas-layout">
        <nav class="normas-indice" aria-label="Contenido de las normas">
            <h2>En esta página</h2>
            <ol>
                <li><a href="#cuenta">Tu cuenta</a></li>
                <li><a href="#publicaciones">Publicaciones veraces</a></li>
                <li><a href="#prohibidos">Artículos prohibidos</a></li>
                <li><a href="#ofertas">Ofertas y participación</a></li>
                <li><a href="#entrega">Entrega y desacuerdos</a></li>
                <li><a href="#privacidad">Datos y privacidad</a></li>
                <li><a href="#alcance">Pagos y alcance actual</a></li>
                <li><a href="#aceptacion">Aceptación de las normas</a></li>
            </ol>
        </nav>
        <div class="normas-texto">
            <section id="cuenta" aria-labelledby="cuenta-titulo">
                <span class="normas-numero" aria-hidden="true">01</span><h2 id="cuenta-titulo">Cuida tu cuenta</h2>
                <p>Utiliza datos veraces y mantén tu contraseña en privado. No suplantes a otra persona ni compartas tu cuenta para evitar restricciones. Eres responsable de las publicaciones y ofertas realizadas desde ella.</p>
                <p>Puedes explorar el catálogo sin iniciar sesión. Para publicar u ofertar necesitas una cuenta activa y haber aceptado esta versión de las normas.</p>
            </section>
            <section id="publicaciones" aria-labelledby="publicaciones-titulo">
                <span class="normas-numero" aria-hidden="true">02</span><h2 id="publicaciones-titulo">Publica lo que realmente ofreces</h2>
                <p>Publica únicamente artículos que puedas entregar y sobre los que tengas derecho a disponer. Describe su estado, defectos, características y accesorios con claridad. Utiliza fotografías propias o autorizadas que representen el artículo.</p>
                <p>No publiques información engañosa, contenido ofensivo ni datos personales de terceros. Las solicitudes se revisan antes de aparecer en el catálogo. Si una solicitud es rechazada, consulta el motivo y corrígela antes de reenviarla.</p>
            </section>
            <section id="prohibidos" aria-labelledby="prohibidos-titulo">
                <span class="normas-numero" aria-hidden="true">03</span><h2 id="prohibidos-titulo">Hay artículos que no tienen lugar aquí</h2>
                <ul><li>Productos ilícitos, robados o cuya venta esté prohibida.</li><li>Armas, municiones y explosivos.</li><li>Carnets, documentos de identidad y documentación personal de terceros.</li><li>Cuentas personales, contraseñas y accesos a servicios.</li><li>Artículos falsificados o presentados mediante engaños.</li></ul>
                <p>La administración puede rechazar publicaciones y revisar cuentas por incumplimientos. La revisión no garantiza la autenticidad, propiedad ni calidad del artículo.</p>
            </section>
            <section id="ofertas" aria-labelledby="ofertas-titulo">
                <span class="normas-numero" aria-hidden="true">04</span><h2 id="ofertas-titulo">Oferta con responsabilidad</h2>
                <p>Lee la descripción, revisa las fotografías, el monto y el cierre antes de confirmar. No se permiten autopujas, acuerdos para manipular precios, fraude ni el uso de otras cuentas para elevar artificialmente las ofertas.</p>
                <p>En las subastas nuevas, la primera oferta coincide con el monto inicial y las siguientes aumentan según el incremento indicado. El sistema muestra el importe antes de confirmarlo. Las publicaciones anteriores conservan sus reglas e historial.</p>
                <p>Una oferta registrada no se puede retirar desde el sistema. Si otra persona oferta antes, el importe puede cambiar y tendrás que revisarlo nuevamente. Al cierre, gana la mayor oferta válida; finalizar la subasta no significa que el artículo ya se haya entregado.</p>
            </section>
            <section id="entrega" aria-labelledby="entrega-titulo">
                <span class="normas-numero" aria-hidden="true">05</span><h2 id="entrega-titulo">Acuerden una entrega segura</h2>
                <p>Vendedor y ganador son responsables de coordinar la entrega. Elijan un lugar público, confirmen las condiciones y revisen el artículo antes de completar el intercambio. No compartas contraseñas, documentos ni información bancaria en las descripciones.</p>
                <p>Ante un desacuerdo, conserva los datos de la publicación y comunícalo al equipo administrador por los medios de coordinación del proyecto. Actualmente no hay un formulario de incidencias ni seguimiento de entrega dentro de SubastaYA.</p>
                <p>Las decisiones administrativas deben basarse en la revisión del caso. SubastaYA no realiza confiscaciones ni cobros automáticos como sanción.</p>
            </section>
            <section id="privacidad" aria-labelledby="privacidad-titulo">
                <span class="normas-numero" aria-hidden="true">06</span><h2 id="privacidad-titulo">Tu información tiene un propósito</h2>
                <p>El nombre y correo identifican tu cuenta y permiten iniciar sesión. Los datos del perfil, publicaciones, fotografías y ofertas se utilizan para mostrar artículos, gestionar la participación y facilitar el contacto que corresponda entre vendedor y ganador.</p>
                <p>El nombre del vendedor y la información de sus publicaciones son visibles al consultar sus artículos. Los datos de contacto disponibles para coordinar una subasta finalizada se muestran a las partes según sus permisos. Evita incluir información privada en fotografías o textos públicos.</p>
                <p>El carnet enviado para verificar identidad no forma parte del catálogo público. Su consulta está restringida al titular y al personal administrador autorizado para la revisión. No lo utilices como fotografía de un artículo.</p>
                <p>Tus participaciones, favoritos y avisos tienen acceso privado. Puedes revisar y corregir los campos disponibles de tu perfil. El historial de subastas, ofertas y aceptaciones se conserva para mantener la trazabilidad; no se ofrece su eliminación automática desde esta página.</p>
            </section>
            <section id="alcance" aria-labelledby="alcance-titulo">
                <span class="normas-numero" aria-hidden="true">07</span><h2 id="alcance-titulo">Qué incluye el sistema actual</h2>
                <p>La billetera utiliza saldos y recargas simulados en bolivianos para la demostración del proyecto. No se mueve dinero real, no se solicitan comprobantes ni datos bancarios y no se ofrecen préstamos, retiros o transferencias bancarias.</p>
                <p>En las nuevas publicaciones se reserva el importe del líder; si otra persona lo supera, recupera su disponibilidad. Si el líder aumenta su oferta, solo se reserva la diferencia. Al cierre, la reserva del ganador permanece retenida hasta la gestión de entrega. Las publicaciones anteriores conservan sus importes y no adquieren reservas retroactivas.</p>
                <p>La liquidación, las devoluciones por incidencias y la comisión se incorporarán mediante la gestión de operaciones. Actualmente no se cobra comisión. Aceptar estas normas no autoriza cargos reales; si cambian las reglas, se publicará una nueva versión.</p>
            </section>
            <section id="aceptacion" class="normas-aceptacion" aria-labelledby="aceptacion-titulo">
                <h2 id="aceptacion-titulo">Lee, acepta y continúa</h2>
                <p>Guardamos tu usuario, la versión aceptada y la fecha de aceptación. No necesitas volver a aceptar mientras siga vigente la misma versión.</p>
                @auth
                    @if (auth()->user()->esAdministrador())
                        <p>Estás consultando las normas como administrador. La aceptación de participación corresponde a las cuentas de usuario.</p>
                    @elseif ($aceptada)
                        <p class="normas-confirmada"><x-icono nombre="check" /> Ya aceptaste la versión {{ $version }}.</p>
                        <a class="button primary" href="{{ $volver }}">Continuar</a>
                    @else
                        <form method="post" action="{{ route('normas.aceptar') }}">
                            @csrf
                            <input type="hidden" name="version" value="{{ $version }}">
                            <input type="hidden" name="volver" value="{{ $volver }}">
                            <label class="normas-check" for="acepto"><input type="checkbox" id="acepto" name="acepto" value="1" required @error('acepto') aria-invalid="true" aria-describedby="acepto-error" @enderror><span>He leído y acepto las normas y políticas de SubastaYA, versión {{ $version }}.</span></label>
                            @error('acepto')<p class="field-error" id="acepto-error">{{ $message }}</p>@enderror
                            @error('version')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                            <button class="button primary" type="submit">Aceptar y continuar</button>
                            <p class="normas-ayuda">Aceptar no envía publicaciones ni registra ofertas. Podrás revisarlas antes de confirmar.</p>
                        </form>
                    @endif
                @else
                    <p>Puedes leer todas las normas sin una cuenta. Inicia sesión cuando quieras publicar u ofertar.</p>
                    <a class="button primary" href="{{ route('iniciar-sesion') }}">Iniciar sesión</a>
                @endauth
            </section>
        </div>
    </div>
</div>
@endsection
