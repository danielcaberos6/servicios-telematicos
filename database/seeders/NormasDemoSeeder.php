<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\NormativaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NormasDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        // Solo cuentas ficticias del catálogo de demostración; nunca cuentas reales.
        $correos = ['demo@subastaya.test', 'vendedor@subastaya.test'];
        foreach (['participante' => 5, 'comunidad' => 16] as $prefijo => $cantidad) {
            for ($i = 1; $i <= $cantidad; $i++) {
                $correos[] = $prefijo.$i.'@subastaya.test';
            }
        }

        DB::transaction(function () use ($correos) {
            $normas = app(NormativaService::class);
            $version = $normas->version();
            foreach (User::whereIn('correo', $correos)->where('activo', true)->get() as $cuenta) {
                if (! $cuenta->esAdministrador() && ! $normas->aceptada($cuenta->id_usuario)) {
                    // Preparar el estado de participación de los datos simulados.
                    $normas->aceptar($cuenta->id_usuario, $version);
                }
            }
        });
    }
}
