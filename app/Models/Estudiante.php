<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    use HasFactory;

    protected $table = 'estudiantes';

    protected $fillable = [
        'codigo', 
        'nombre', 
        'curso', 
        'paralelo', 
        'estado'
    ];

    // Relación: Un Estudiante tiene un Plan de Pago (Gestión actual)
    public function planPago()
    {
        return $this->hasOne(PlanPago::class, 'estudiante_id');
    }
}