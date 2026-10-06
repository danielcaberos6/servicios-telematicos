<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Puja extends Model
{
    protected $table = 'puja';

    protected $primaryKey = 'id_puja';

    public $timestamps = false;

    protected $fillable = ['monto', 'id_usuario'];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'fecha_puja' => 'datetime'];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
