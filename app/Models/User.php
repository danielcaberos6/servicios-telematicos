<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasFactory;

    protected $table = 'usuario';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $fillable = ['nombre', 'correo', 'contrasena', 'biografia', 'ciudad', 'telefono'];

    protected $hidden = ['contrasena', 'remember_token'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'fecha_registro' => 'datetime',
            'contrasena' => 'hashed',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'contrasena';
    }

    public function esAdministrador(): bool
    {
        return DB::table('usuario_rol')
            ->join('rol', 'rol.id_rol', '=', 'usuario_rol.id_rol')
            ->where('usuario_rol.id_usuario', $this->id_usuario)
            ->where('rol.nombre_rol', 'Administrador')->exists();
    }

    public function imagen()
    {
        return $this->hasOne(Imagen::class, 'id_usuario');
    }

    public function subastas()
    {
        return $this->hasMany(Subasta::class, 'id_usuario');
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class, 'id_usuario');
    }
}
