<?php

use App\Http\Controllers\AutenticacionController;
use App\Http\Controllers\ImagenController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\PujaController;
use App\Http\Controllers\SubastaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SubastaController::class, 'inicio'])->name('inicio');
Route::get('/buscar', [SubastaController::class, 'index'])->name('subastas.index');
Route::get('/imagenes/{imagen}', [ImagenController::class, 'show'])->name('imagenes.show');

Route::middleware('guest')->group(function () {
    Route::view('/iniciar-sesion', 'autenticacion.iniciar-sesion')->name('iniciar-sesion');
    Route::post('/iniciar-sesion', [AutenticacionController::class, 'iniciarSesion'])->middleware('throttle:20,1');
    Route::view('/registro', 'autenticacion.registro')->name('registro');
    Route::post('/registro', [AutenticacionController::class, 'registrar'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/cerrar-sesion', [AutenticacionController::class, 'cerrarSesion'])->name('cerrar-sesion');
    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::get('/mis-subastas', [SubastaController::class, 'misSubastas'])->name('subastas.mias');
    Route::get('/subastas/crear', [SubastaController::class, 'create'])->name('subastas.create');
    Route::post('/subastas', [SubastaController::class, 'store'])->middleware('throttle:20,1')->name('subastas.store');
    Route::get('/subastas/{subasta}/editar', [SubastaController::class, 'edit'])->name('subastas.edit');
    Route::put('/subastas/{subasta}', [SubastaController::class, 'update'])->name('subastas.update');
    Route::delete('/subastas/{subasta}', [SubastaController::class, 'destroy'])->name('subastas.destroy');
    Route::post('/subastas/{subasta}/pujas', [PujaController::class, 'store'])->middleware('throttle:30,1')->name('pujas.store');
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::patch('/notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leerTodas');
    Route::patch('/notificaciones/{notificacion}', [NotificacionController::class, 'leer'])->name('notificaciones.leer');
    Route::delete('/notificaciones/{notificacion}', [NotificacionController::class, 'destroy'])->name('notificaciones.destroy');
});
Route::get('/subastas/{subasta}', [SubastaController::class, 'show'])->whereNumber('subasta')->name('subastas.show');
