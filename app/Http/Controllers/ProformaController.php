<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Estudiante;
use App\Models\Servicio;
use App\Models\PlanPago;
use App\Models\Cuota;

class ProformaController extends Controller
{
    // 1. Mostrar lista de estudiantes
    public function index(Request $request)
    {
        $buscar = $request->get('buscar');
        $estudiantes = Estudiante::when($buscar, function ($query, $buscar) {
            return $query->where('codigo', 'LIKE', "%{$buscar}%")
                         ->orWhere('nombre', 'LIKE', "%{$buscar}%")
                         ->orWhere('curso', 'LIKE', "%{$buscar}%");
        })->orderBy('id', 'desc')->paginate(15);

        return view('proformas.index', compact('estudiantes', 'buscar'));
    }

    // 2. Pantalla para configurar la proforma detallada manualmente
    public function configurar($id)
    {
        $estudiante = Estudiante::findOrFail($id);
        
        $numeroCurso = preg_replace('/[^0-9]/', '', $estudiante->curso);
        if ($numeroCurso === "") $numeroCurso = "0";

        $serviciosBrutos = Servicio::whereIn('nivel', ['TODOS LOS NIVELES', $estudiante->estado])->get();

        $servicios = $serviciosBrutos->filter(function($servicio) use ($numeroCurso) {
            if (empty($servicio->cursos_permitidos)) return true;
            $permitidos = array_map('trim', explode(',', $servicio->cursos_permitidos));
            return in_array($numeroCurso, $permitidos);
        });

        return view('proformas.configurar', compact('estudiante', 'servicios'));
    }

    // 3. Procesar, GUARDAR EN BASE DE DATOS y mostrar la Proforma Detallada
    public function imprimir(Request $request, $id)
    {
        $estudiante = Estudiante::findOrFail($id);
        $serviciosSeleccionados = $request->input('servicios', []); 
        $modalidades = $request->input('modalidades', []); 

        if(empty($serviciosSeleccionados)){
            return back()->with('error', 'Debes seleccionar al menos un servicio para generar la proforma.');
        }

        $servicios = Servicio::whereIn('id', $serviciosSeleccionados)->get();

        $meses = [
            1 => ['nombre' => 'FEBRERO', 'fecha' => '2026-02-10'],
            2 => ['nombre' => 'MARZO', 'fecha' => '2026-03-10'],
            3 => ['nombre' => 'ABRIL', 'fecha' => '2026-04-10'],
            4 => ['nombre' => 'MAYO', 'fecha' => '2026-05-10'],
            5 => ['nombre' => 'JUNIO', 'fecha' => '2026-06-10'],
            6 => ['nombre' => 'JULIO', 'fecha' => '2026-07-10'],
            7 => ['nombre' => 'AGOSTO', 'fecha' => '2026-08-10'],
            8 => ['nombre' => 'SEPTIEMBRE', 'fecha' => '2026-09-10'],
            9 => ['nombre' => 'OCTUBRE', 'fecha' => '2026-10-10'],
            10 => ['nombre' => 'NOVIEMBRE', 'fecha' => '2026-11-10'],
        ];

        $granTotalBs = 0;
        $detalleCuotas = [];

        foreach($meses as $num => $mes) {
            $subtotalMes = 0;
            foreach($servicios as $s) {
                $montoFila = 0;
                $modalidad = $modalidades[$s->id] ?? 'UNICO';

                if($modalidad == 'UNICO' && $num == 1) {
                    $montoFila = $s->monto_bs; 
                } elseif($modalidad == 'ANUAL' && $num == 1) {
                    $costoAnual = $s->monto_bs * 10;
                    $descuento = $costoAnual * ($s->descuento_anual / 100);
                    $montoFila = $costoAnual - $descuento;
                } elseif($modalidad == 'MENSUAL') {
                    $montoFila = $s->monto_bs;
                }
                $subtotalMes += $montoFila;
            }
            
            $granTotalBs += $subtotalMes;
            $detalleCuotas[$num] = [
                'mes' => $mes['nombre'],
                'monto_bs' => $subtotalMes,
                'fecha_limite' => $mes['fecha']
            ];
        }

        $planExistente = PlanPago::where('estudiante_id', $estudiante->id)->where('gestion', 2026)->first();
        if($planExistente) { $planExistente->delete(); }

        $nuevoPlan = PlanPago::create([
            'estudiante_id' => $estudiante->id,
            'gestion' => 2026,
            'total_bs' => $granTotalBs,
            'estado' => 'ACTIVO'
        ]);

        foreach($detalleCuotas as $num => $datos) {
            Cuota::create([
                'plan_pago_id' => $nuevoPlan->id,
                'numero_cuota' => $num,
                'mes' => $datos['mes'],
                'monto_bs' => $datos['monto_bs'],
                'fecha_limite' => $datos['fecha_limite'],
                'estado' => 'PENDIENTE'
            ]);
        }

        return view('proformas.imprimir', compact('estudiante', 'servicios', 'modalidades', 'meses'));
    }

    // 4. Generar Proforma Rápida (Resumen Automático para PDF)
    public function proformaRapida($id)
    {
        $estudiante = Estudiante::findOrFail($id);

        $numeroCurso = preg_replace('/[^0-9]/', '', $estudiante->curso);
        if ($numeroCurso === "") $numeroCurso = "0";

        $serviciosBrutos = Servicio::whereIn('nivel', ['TODOS LOS NIVELES', $estudiante->estado])->get();

        $servicios = $serviciosBrutos->filter(function($servicio) use ($numeroCurso) {
            if (empty($servicio->cursos_permitidos)) return true;
            $permitidos = array_map('trim', explode(',', $servicio->cursos_permitidos));
            return in_array($numeroCurso, $permitidos);
        });

        $totalAnualConDescuento = 0;
        $totalDescuentoAhorrado = 0;
        $totalInscripcion = 0;  
        $totalCuotaRegular = 0; 
        $resumenServicios = [];

        foreach ($servicios as $servicio) {
            $montoBase = $servicio->monto_bs;
            $descuentoPorcentaje = $servicio->descuento_anual ?? 0;
            
            $esPagoUnico = (stripos($servicio->nombre, 'complementario') !== false);

            if ($esPagoUnico) {
                $descuentoMonto = $montoBase * ($descuentoPorcentaje / 100);
                $montoAnualNeto = $montoBase - $descuentoMonto;
                $totalInscripcion += $montoBase; 
                
                $resumenServicios[] = [
                    'nombre' => $servicio->nombre . ' (Pago Único)',
                    'inscripcion' => $montoBase,
                    'cuota_mensual' => 0, 
                    'total_anual_neto' => $montoAnualNeto,
                ];
                $totalAnualConDescuento += $montoAnualNeto;
                $totalDescuentoAhorrado += $descuentoMonto;
            } else {
                $montoAnualBruto = $montoBase * 10;
                $descuentoMonto = $montoAnualBruto * ($descuentoPorcentaje / 100);
                $montoAnualNeto = $montoAnualBruto - $descuentoMonto;
                $totalInscripcion += $montoBase; 
                $totalCuotaRegular += $montoBase; 
                
                $resumenServicios[] = [
                    'nombre' => $servicio->nombre . ' (10 Cuotas)',
                    'inscripcion' => $montoBase,
                    'cuota_mensual' => $montoBase,
                    'total_anual_neto' => $montoAnualNeto,
                ];
                $totalAnualConDescuento += $montoAnualNeto;
                $totalDescuentoAhorrado += $descuentoMonto;
            }
        }

        return view('proformas.rapida', compact(
            'estudiante',
            'resumenServicios',
            'totalAnualConDescuento',
            'totalDescuentoAhorrado',
            'totalInscripcion',
            'totalCuotaRegular'
        ));
    }
}