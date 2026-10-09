<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PujaController;
use App\Http\Controllers\SubastaController;
use App\Models\Imagen;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', [SubastaController::class, 'home'])->name('home');
Route::get('/buscar', [SubastaController::class, 'index'])->name('auctions.index');
Route::get('/imagenes/{imagen}', function (Imagen $imagen) {
    abort_unless(Storage::disk('public')->exists($imagen->ruta), 404);

    return response()->file(Storage::disk('public')->path($imagen->ruta), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=86400']);
})->name('images.show');

Route::middleware('guest')->group(function () {
    Route::view('/iniciar-sesion', 'auth.login')->name('login');
    Route::post('/iniciar-sesion', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::view('/registro', 'auth.register')->name('register');
    Route::post('/registro', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/cerrar-sesion', [AuthController::class, 'logout'])->name('logout');
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/mis-subastas', [SubastaController::class, 'mine'])->name('auctions.mine');
    Route::get('/subastas/crear', [SubastaController::class, 'create'])->name('auctions.create');
    Route::post('/subastas', [SubastaController::class, 'store'])->middleware('throttle:20,1')->name('auctions.store');
    Route::get('/subastas/{subasta}/editar', [SubastaController::class, 'edit'])->name('auctions.edit');
    Route::put('/subastas/{subasta}', [SubastaController::class, 'update'])->name('auctions.update');
    Route::delete('/subastas/{subasta}', [SubastaController::class, 'destroy'])->name('auctions.destroy');
    Route::post('/subastas/{subasta}/pujas', [PujaController::class, 'store'])->middleware('throttle:30,1')->name('bids.store');
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notifications.index');
    Route::patch('/notificaciones/leer-todas', [NotificacionController::class, 'readAll'])->name('notifications.readAll');
    Route::patch('/notificaciones/{notificacion}', [NotificacionController::class, 'read'])->name('notifications.read');
    Route::delete('/notificaciones/{notificacion}', [NotificacionController::class, 'destroy'])->name('notifications.destroy');
});
Route::get('/subastas/{subasta}', [SubastaController::class, 'show'])->whereNumber('subasta')->name('auctions.show');
