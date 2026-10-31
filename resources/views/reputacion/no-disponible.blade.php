@extends('plantillas.principal')
@section('title','Cuenta no disponible')
@section('content')<section class="container section"><h1>Cuenta no disponible</h1><p>Este perfil no está disponible para consulta pública.</p><a href="{{ route('subastas.index') }}">Explorar subastas</a></section>@endsection
