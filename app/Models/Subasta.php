<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Subasta extends Model
{
    public const CONDITIONS = ['Nuevo', 'Usado'];

    public const REVISIONES = ['En revisión', 'Aceptada', 'Rechazada'];

    protected $table = 'subasta';

    protected $primaryKey = 'id_subasta';

    public $timestamps = false;

    protected $fillable = ['titulo', 'descripcion', 'ubicacion', 'latitud', 'longitud', 'estado_articulo', 'monto_inicial', 'fecha_inicio', 'fecha_fin', 'id_categoria'];

    protected function casts(): array
    {
        return ['monto_inicial' => 'decimal:2', 'latitud' => 'decimal:6', 'longitud' => 'decimal:6', 'fecha_inicio' => 'datetime', 'fecha_fin' => 'datetime', 'fecha_decision' => 'datetime'];
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
        if (! $this->publicada()) {
            return $this->estado_revision;
        }
        if ($this->fecha_fin->isPast()) {
            return 'Finalizada';
        }

        return $this->fecha_inicio->isFuture() ? 'Programada' : 'Activa';
    }

    public function publicada(): bool
    {
        return $this->estado_revision === 'Aceptada';
    }

    public function editable(): bool
    {
        return $this->estado_subasta === 'Activa' && (! $this->publicada() || $this->estado === 'Activa');
    }

    public function decisiones()
    {
        return $this->hasMany(DecisionSubasta::class, 'id_subasta')->orderByDesc('id_decision');
    }

    // Una solicitud En revisión o Rechazada solo la ven su propietario y el Administrador.
    public function visiblePara(?User $user): bool
    {
        return $this->publicada() || ($user && ($user->id_usuario === $this->id_usuario || $user->esAdministrador()));
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function revisor()
    {
        return $this->belongsTo(User::class, 'id_revisor');
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
