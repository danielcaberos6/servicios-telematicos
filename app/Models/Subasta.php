<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Subasta extends Model
{
    public const CONDITIONS = ['Nuevo', 'Usado'];

    protected $table = 'subasta';

    protected $primaryKey = 'id_subasta';

    public $timestamps = false;

    protected $fillable = ['titulo', 'descripcion', 'ubicacion', 'estado_articulo', 'monto_inicial', 'fecha_inicio', 'fecha_fin', 'id_categoria'];

    protected function casts(): array
    {
        return ['monto_inicial' => 'decimal:2', 'fecha_inicio' => 'datetime', 'fecha_fin' => 'datetime'];
    }

    public function scopeActivas(Builder $query): void
    {
        $query->where('estado_subasta', 'Activa')->where('fecha_inicio', '<=', now())->where('fecha_fin', '>', now());
    }

    public function getEstadoAttribute(): string
    {
        if ($this->estado_subasta !== 'Activa') {
            return $this->estado_subasta;
        }
        if ($this->fecha_fin->isPast()) {
            return 'Finalizada';
        }

        return $this->fecha_inicio->isFuture() ? 'Programada' : 'Activa';
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria');
    }

    public function imagenes()
    {
        return $this->hasMany(Imagen::class, 'id_subasta')->orderBy('id_imagen');
    }

    public function pujas()
    {
        return $this->hasMany(Puja::class, 'id_subasta');
    }
}
