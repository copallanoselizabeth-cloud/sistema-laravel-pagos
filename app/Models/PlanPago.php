<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanPago extends Model
{
    use HasFactory;
    
    protected $table = 'planes_pagos';
    
    protected $fillable = [
        'estudiante_id', 
        'gestion', 
        'total_bs', 
        'estado'
    ];

    // Relación: Un Plan pertenece a un Estudiante
    public function estudiante() 
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
    }

    // Relación: Un Plan tiene muchas Cuotas
    public function cuotas() 
    {
        return $this->hasMany(Cuota::class, 'plan_pago_id');
    }
}