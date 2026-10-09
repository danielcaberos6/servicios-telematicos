<?php

namespace App\Services\Concerns;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Puente entre los controladores HTTP y las operaciones de Funciones.sql. */
trait EjecutaFuncionesSql
{
    private function ejecutar(string $sql, array $parameters, string $field = 'subasta'): array
    {
        try {
            // Un savepoint permite recuperar la transacción externa si PostgreSQL rechaza la operación.
            return DB::transaction(fn () => DB::select($sql, $parameters));
        } catch (QueryException $e) {
            $state = $e->errorInfo[0] ?? $e->getCode();
            if ($state === '42501') {
                abort(403);
            }
            if ($state === 'P0002') {
                abort(404);
            }
            if ($state === 'P0001') {
                // Solo el mensaje de negocio, sin SQL, parámetros ni detalles de conexión.
                $message = preg_replace('/^.*?ERROR:\s*/s', '', $e->errorInfo[2] ?? 'Operación no permitida.');
                throw ValidationException::withMessages([$field => trim(explode("\n", $message)[0])]);
            }
            if ($state === '23505' && $field === 'correo') {
                throw ValidationException::withMessages(['correo' => 'Este correo ya está registrado.']);
            }
            throw $e;
        }
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
