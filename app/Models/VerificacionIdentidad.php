<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerificacionIdentidad extends Model
{
    protected $table = 'verificacion_identidad';

    protected $primaryKey = 'id_verificacion';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $hidden = ['ruta_documento'];

    protected function casts(): array
    {
        return ['vigente' => 'boolean', 'fecha_envio' => 'datetime', 'fecha_decision' => 'datetime', 'fecha_invalidacion' => 'datetime'];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function revisor()
    {
        return $this->belongsTo(User::class, 'id_revisor');
    }
}
