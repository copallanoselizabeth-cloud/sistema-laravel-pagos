<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Estudiante;
use App\Models\PlanPago;
use App\Models\Cuota;
use Carbon\Carbon;

class ReporteController extends Controller
{
    public function index()
    {
        // 1. Métricas Generales
        $totalEstudiantes = Estudiante::count();
        $planesCompletados = PlanPago::where('estado', 'COMPLETADO')->count();
        $planesActivos = PlanPago::where('estado', 'ACTIVO')->count();

        // 2. Reporte de Estudiantes Registrados UNICAMENTE HOY
        $nuevosEstudiantes = Estudiante::whereDate('created_at', Carbon::today())
            ->orderBy('id', 'desc')
            ->get();

        // Conteo exacto de los registrados el día de hoy
        $conteoNuevos = $nuevosEstudiantes->count();

        // 3. Métricas Financieras (Total real cobrado y pendiente)
        $totalCobrado = Cuota::where('estado', 'PAGADO')->sum('monto_bs');
        $totalPendiente = Cuota::where('estado', 'PENDIENTE')->sum('monto_bs');

        // 4. Pagos Registrados (AGRUPADOS POR ESTUDIANTE)
        $pagosRecientes = PlanPago::with(['estudiante', 'cuotas'])
            ->whereHas('cuotas', function($query) {
                $query->where('estado', 'PAGADO')->where('monto_bs', '>', 0);
            })
            ->get()
            ->map(function($plan) {
                $plan->total_pagado = $plan->cuotas->where('estado', 'PAGADO')->sum('monto_bs');
                $plan->fecha_ultimo_pago = $plan->cuotas->where('estado', 'PAGADO')->max('fecha_pago');
                return $plan;
            })
            ->sortByDesc('fecha_ultimo_pago')
            ->take(50);

        // 5. Lista de Morosos
        $morosos = PlanPago::with('estudiante')
            ->where('estado', 'ACTIVO')
            ->get()
            ->map(function($plan) {
                $plan->deuda_total = $plan->cuotas->where('estado', 'PENDIENTE')->sum('monto_bs');
                return $plan;
            })
            ->filter(function($plan) {
                return $plan->deuda_total > 0;
            })
            ->sortByDesc('deuda_total')
            ->take(50);

        return view('reportes.index', compact(
            'totalEstudiantes', 'planesCompletados', 'planesActivos', 
            'totalCobrado', 'totalPendiente', 'pagosRecientes', 'morosos',
            'nuevosEstudiantes', 'conteoNuevos'
        ));
    }
}