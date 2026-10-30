<?php

use App\Http\Controllers\AutenticacionController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\GestionUsuarioController;
use App\Http\Controllers\IdentidadController;
use App\Http\Controllers\ImagenController;
use App\Http\Controllers\NormativaController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\ParticipacionController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PujaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RevisionSubastaController;
use App\Http\Controllers\SubastaController;
use App\Http\Middleware\NormasAceptadas;
use App\Http\Middleware\SoloAdministrador;
use App\Http\Middleware\SoloUsuario;
use Illuminate\Support\Facades\Route;

Route::get('/', [SubastaController::class, 'inicio'])->middleware(SoloUsuario::class)->name('inicio');
Route::get('/buscar', [SubastaController::class, 'index'])->middleware(SoloUsuario::class)->name('subastas.index');
Route::view('/como-funciona', 'como-funciona')->name('como-funciona');
Route::get('/normas-y-politicas', [NormativaController::class, 'show'])->name('normas.show');
Route::get('/imagenes/{imagen}', [ImagenController::class, 'show'])->name('imagenes.show');

Route::middleware(['auth', SoloAdministrador::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/subastas', [RevisionSubastaController::class, 'index'])->name('subastas.index');
    Route::get('/subastas/{subasta}', [RevisionSubastaController::class, 'show'])->name('subastas.show');
    Route::patch('/subastas/{subasta}', [RevisionSubastaController::class, 'resolver'])->name('subastas.resolver');
    Route::get('/identidad', [IdentidadController::class, 'index'])->name('identidad.index');
    Route::patch('/identidad/{verificacion}', [IdentidadController::class, 'resolver'])->name('identidad.resolver');
    Route::resource('categorias', CategoriaController::class)->except('show', 'destroy');
    Route::patch('/categorias/{categoria}/estado', [CategoriaController::class, 'estado'])->name('categorias.estado');
    Route::get('/usuarios', [GestionUsuarioController::class, 'index'])->name('usuarios.index');
    Route::patch('/usuarios/{usuario}', [GestionUsuarioController::class, 'update'])->name('usuarios.update');
    Route::get('/usuarios/{usuario}/historial', [GestionUsuarioController::class, 'historial'])->name('usuarios.historial');
});

Route::middleware(['auth', SoloAdministrador::class])->prefix('reportes')->name('reportes.')->group(function () {
    Route::redirect('/', '/reportes/subastas')->name('index');
    Route::get('/subastas', [ReporteController::class, 'subastas'])->name('subastas');
    Route::get('/usuarios', [ReporteController::class, 'usuarios'])->name('usuarios');
});

Route::middleware('guest')->group(function () {
    Route::view('/iniciar-sesion', 'autenticacion.iniciar-sesion')->name('iniciar-sesion');
    Route::post('/iniciar-sesion', [AutenticacionController::class, 'iniciarSesion'])->middleware('throttle:20,1');
    Route::view('/registro', 'autenticacion.registro')->name('registro');
    Route::post('/registro', [AutenticacionController::class, 'registrar'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/perfil/identidad', [IdentidadController::class, 'store'])->middleware([SoloUsuario::class, 'throttle:5,1'])->name('identidad.store');
    Route::get('/identidad/{verificacion}', [IdentidadController::class, 'show'])->name('identidad.show');
    Route::get('/identidad/{verificacion}/documento', [IdentidadController::class, 'documento'])->name('identidad.documento');
    Route::post('/cerrar-sesion', [AutenticacionController::class, 'cerrarSesion'])->name('cerrar-sesion');
    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [PerfilController::class, 'update'])->middleware('throttle:10,1')->name('perfil.update');
    Route::middleware(SoloUsuario::class)->group(function () {
        Route::post('/normas-y-politicas/aceptar', [NormativaController::class, 'aceptar'])->middleware('throttle:20,1')->name('normas.aceptar');
        Route::get('/mis-participaciones', [ParticipacionController::class, 'index'])->name('participaciones.index');
        Route::get('/favoritos', [ParticipacionController::class, 'favoritos'])->name('favoritos.index');
        Route::put('/favoritos/{subasta}', [ParticipacionController::class, 'guardar'])->name('favoritos.guardar');
        Route::delete('/favoritos/{subasta}', [ParticipacionController::class, 'retirar'])->name('favoritos.retirar');
        Route::get('/mis-subastas', [SubastaController::class, 'misSubastas'])->name('subastas.mias');
        Route::get('/subastas/crear', [SubastaController::class, 'create'])->middleware(NormasAceptadas::class)->name('subastas.create');
        Route::post('/subastas', [SubastaController::class, 'store'])->middleware([NormasAceptadas::class, 'throttle:20,1'])->name('subastas.store');
        Route::get('/subastas/{subasta}/editar', [SubastaController::class, 'edit'])->middleware(NormasAceptadas::class)->name('subastas.edit');
        Route::put('/subastas/{subasta}', [SubastaController::class, 'update'])->middleware(NormasAceptadas::class)->name('subastas.update');
        Route::delete('/subastas/{subasta}', [SubastaController::class, 'destroy'])->name('subastas.destroy');
        Route::post('/subastas/{subasta}/pujas', [PujaController::class, 'store'])->middleware([NormasAceptadas::class, 'throttle:30,1'])->name('pujas.store');
        Route::get('/subastas/{subasta}/participar', [SubastaController::class, 'participar'])->whereNumber('subasta')->name('subastas.participar');
    });
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::patch('/notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leerTodas');
    Route::patch('/notificaciones/{notificacion}', [NotificacionController::class, 'leer'])->name('notificaciones.leer');
    Route::delete('/notificaciones/{notificacion}', [NotificacionController::class, 'destroy'])->name('notificaciones.destroy');
});
Route::get('/subastas/{subasta}', [SubastaController::class, 'show'])->whereNumber('subasta')->name('subastas.show');
