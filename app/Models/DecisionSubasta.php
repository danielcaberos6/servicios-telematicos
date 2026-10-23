<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DecisionSubasta extends Model
{
    protected $table = 'decision_subasta';

    protected $primaryKey = 'id_decision';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['fecha_decision' => 'datetime'];
    }

    public function revisor()
    {
        return $this->belongsTo(User::class, 'id_revisor');
    }
}
