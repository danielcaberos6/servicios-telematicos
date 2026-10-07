<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificacion';

    protected $primaryKey = 'id_notificacion';

    public $timestamps = false;

    protected $fillable = ['titulo', 'contenido', 'leido'];

    protected function casts(): array
    {
        return ['leido' => 'boolean', 'fecha_notificacion' => 'datetime'];
    }
}
