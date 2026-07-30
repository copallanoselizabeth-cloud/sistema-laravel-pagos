<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    use HasFactory;

    protected $table = 'servicios';

    protected $fillable = [
        'codigo',
        'nombre',
        'nivel',
        'cursos_permitidos',
        'tipo_pago',
        'descuento_anual',
        'monto_bs',
        'monto_usd',
        'gestion',
    ];
}