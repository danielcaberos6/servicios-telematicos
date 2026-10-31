<?php

namespace Database\Factories;

use App\Models\User;
use App\Services\FondosService;
use App\Services\NormativaService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    // Las cuentas de las pruebas previas representan participantes preparados.
    // No se usa en registro ni en seeders; las cuentas reales nunca se aceptan solas.
    public function configure(): static
    {
        return $this->afterCreating(function (User $usuario) {
            if (app()->environment('testing')) {
                DB::table('aceptacion_normas')->insert([
                    'id_usuario' => $usuario->id_usuario,
                    'version' => app(NormativaService::class)->version(),
                ]);
            }
        });
    }

    public function sinAceptarNormas(): static
    {
        return $this->afterCreating(fn (User $usuario) => DB::table('aceptacion_normas')->where('id_usuario', $usuario->id_usuario)->delete());
    }

    public function conFondos(int $monto = 10000): static
    {
        // Preparación explícita de cuentas de prueba mediante el flujo real de recarga.
        return $this->afterCreating(function (User $usuario) use ($monto) {
            if (! app()->environment('testing')) {
                throw new \RuntimeException('conFondos solo se permite en pruebas.');
            }
            $fondos = app(FondosService::class);
            $recarga = $fondos->solicitar($usuario->id_usuario, (string) Str::uuid(), (string) $monto);
            $fondos->resolver($usuario->id_usuario, $recarga->id_recarga, 'Aprobada');
        });
    }

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'correo' => fake()->unique()->safeEmail(),
            'contrasena' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
}
