<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PlanPago;
use App\Models\Cuota;

class PlanPagoController extends Controller
{
    // 1. Mostrar lista de planes
    public function index(Request $request)
    {
        $buscar = $request->get('buscar');

        $planes = PlanPago::with(['estudiante', 'cuotas'])
            ->when($buscar, function ($query, $buscar) {
                $query->whereHas('estudiante', function($q) use ($buscar) {
                    $q->where('codigo', 'LIKE', "%{$buscar}%")
                      ->orWhere('nombre', 'LIKE', "%{$buscar}%");
                });
            })
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('planes.index', compact('planes', 'buscar'));
    }

    // 2. Ver detalles del plan de pagos
    public function show($id)
    {
        $plan = PlanPago::with(['estudiante', 'cuotas' => function($q) {
            $q->orderBy('numero_cuota', 'asc');
        }])->findOrFail($id);

        $totalCuotas = $plan->cuotas->count();
        $cuotasPagadas = $plan->cuotas->where('estado', 'PAGADO')->count();
        
        // Calculamos las pendientes basándonos en la base de datos real
        $cuotasPendientes = $plan->cuotas->where('estado', 'PENDIENTE')->count();
        
        $progreso = $totalCuotas > 0 ? round(($cuotasPagadas / $totalCuotas) * 100) : 0;

        // Regla estricta: Solo está completado si no hay NINGUNA cuota pendiente
        $pagoCompletado = ($cuotasPendientes === 0);

        // Si por alguna razón el estado general no coincide, lo forzamos a actualizarse
        if ($pagoCompletado && $plan->estado !== 'COMPLETADO') {
            $plan->update(['estado' => 'COMPLETADO']);
        } elseif (!$pagoCompletado && $plan->estado === 'COMPLETADO') {
            $plan->update(['estado' => 'ACTIVO']);
        }

        return view('planes.show', compact('plan', 'progreso', 'pagoCompletado', 'cuotasPendientes'));
    }

    // 3. Registrar el cobro de una cuota específica
    public function cobrar(Request $request, $id)
    {
        $cuota = Cuota::findOrFail($id);
        $plan = PlanPago::findOrFail($cuota->plan_pago_id);
        
        // 1. Cobrar SOLO la cuota a la que le dimos clic
        $cuota->update([
            'estado' => 'PAGADO',
            'fecha_pago' => now()->format('Y-m-d')
        ]);

        // 2. Autoliquidar SOLO cuotas que cuestan 0.00 Bs (Ocurre en pagos Anuales)
        Cuota::where('plan_pago_id', $plan->id)
            ->where('estado', 'PENDIENTE')
            ->where('monto_bs', 0)
            ->update([
                'estado' => 'PAGADO',
                'fecha_pago' => now()->format('Y-m-d')
            ]);

        // 3. Verificar si después de este pago el plan ya se terminó
        $pendientes = Cuota::where('plan_pago_id', $plan->id)
            ->where('estado', 'PENDIENTE')
            ->count();
        
        if ($pendientes === 0) {
            $plan->update(['estado' => 'COMPLETADO']);
            return back()->with('success', '¡Pago de ' . $cuota->mes . ' registrado! El plan ha sido completado en su totalidad.');
        }

        return back()->with('success', '¡Pago de la cuota de ' . $cuota->mes . ' registrado exitosamente!');
    }

    // 4. Liquidación total ("Cobrar Todo el Año")
    public function cobrarTodo($plan_id)
    {
        $plan = PlanPago::findOrFail($plan_id);

        // Pasamos absolutamente todas las cuotas pendientes a pagado
        Cuota::where('plan_pago_id', $plan->id)->update([
            'estado' => 'PAGADO',
            'fecha_pago' => now()->format('Y-m-d')
        ]);

        $plan->update(['estado' => 'COMPLETADO']);

        return back()->with('success', '¡Éxito! Se ha liquidado la totalidad del plan académico. El estudiante no tiene deudas.');
    }

    // 5. Imprimir Recibo de Pago
    public function recibo($id)
    {
        $cuota = Cuota::with('planPago.estudiante')->findOrFail($id);
        
        if ($cuota->estado !== 'PAGADO') {
            return back()->with('error', 'Solo se pueden imprimir recibos de cuotas canceladas.');
        }
    }
        public function registrarPago(Request $request, $id)
    {
        $cuota = Cuota::findOrFail($id);

        if ($cuota->estado === 'PAGADO') {
            return back()->with('error', 'Esta cuota ya ha sido cancelada anteriormente.');
        }

        // Actualizar la cuota
        $cuota->update([
            'estado' => 'PAGADO',
            'fecha_pago' => now(),
            'referencia' => $request->input('referencia', 'PAGO EN VENTANILLA'),
        ]);

        // Si todas las cuotas del plan están pagadas, marcar el plan como COMPLETADO
        $plan = $cuota->planPago;
        $pendientes = $plan->cuotas()->where('estado', 'PENDIENTE')->count();
        if ($pendientes === 0) {
            $plan->update(['estado' => 'COMPLETADO']);
        }

        return back()->with('success', '¡Pago de ' . $cuota->mes . ' registrado exitosamente!')
                     ->with('recibo_id', $cuota->id);
    }

    // Generar la vista imprimible del recibo de pago
    public function imprimirRecibo($id)
    {
        $cuota = Cuota::with(['planPago.estudiante'])->findOrFail($id);
        return view('planes.recibo', compact('cuota'));
    }
}

     