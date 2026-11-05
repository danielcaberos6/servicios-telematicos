<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Laravel Cloud reemplaza estos discos con throw=false. Sin esto, una escritura
        // fallida devolvería false y se registraría en la base una ruta inválida.
        foreach (['public', 'identidad', 'conversaciones'] as $disco) {
            $this->app['config']->set("filesystems.disks.{$disco}.throw", true);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::anonymousComponentPath(resource_path('views/componentes'));
    }
}
