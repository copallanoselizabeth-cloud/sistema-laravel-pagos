<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuota extends Model
{
    use HasFactory;
    protected $table = 'cuotas';
    protected $fillable = ['plan_pago_id', 'numero_cuota', 'mes', 'monto_bs', 'fecha_limite', 'estado', 'fecha_pago'];

    public function planPago() {
        return $this->belongsTo(PlanPago::class, 'plan_pago_id');
    }
}