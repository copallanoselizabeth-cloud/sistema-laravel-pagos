<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Estudiante;

class EstudianteController extends Controller
{
    // Muestra la lista y maneja el buscador
    public function index(Request $request)
    {
        $buscar = $request->get('buscar');

        // Utilizamos paginate(15) en lugar de get() para que la carga sea instantánea
        $estudiantes = Estudiante::when($buscar, function ($query, $buscar) {
            return $query->where('codigo', 'LIKE', "%{$buscar}%")
                         ->orWhere('nombre', 'LIKE', "%{$buscar}%")
                         ->orWhere('curso', 'LIKE', "%{$buscar}%");
        })
        ->orderBy('id', 'desc')
        ->paginate(15); 

        return view('estudiantes.index', compact('estudiantes', 'buscar'));
    }
    // Guardar manual
    public function store(Request $request)
    {
        $request->validate([
            'codigo'   => 'required|string|unique:estudiantes,codigo',
            'nombre'   => 'required|string|max:255',
            'curso'    => 'required|string|max:100',
            'paralelo' => 'required|string|max:50',
            'estado'   => 'required|string',
        ]);

        Estudiante::create([
            'codigo'   => $request->codigo,
            'nombre'   => $request->nombre,
            'curso'    => $request->curso,
            'paralelo' => $request->paralelo,
            'estado'   => $request->estado,
        ]);

        return back()->with('success', '¡Estudiante registrado correctamente!');
    }

    // Actualizar estudiante existente
    public function update(Request $request, $id)
    {
        $estudiante = Estudiante::findOrFail($id);

        $request->validate([
            'codigo'   => 'required|string|max:100|unique:estudiantes,codigo,' . $id,
            'nombre'   => 'required|string|max:255',
            'curso'    => 'required|string|max:100',
            'paralelo' => 'required|string|max:50',
            'estado'   => 'required|string',
        ]);

        $estudiante->update([
            'codigo'   => $request->codigo,
            'nombre'   => $request->nombre,
            'curso'    => $request->curso,
            'paralelo' => $request->paralelo,
            'estado'   => $request->estado,
        ]);

        return back()->with('success', '¡Datos del estudiante actualizados correctamente!');
    }

    // Eliminar estudiante
    public function destroy($id)
    {
        $estudiante = Estudiante::findOrFail($id);
        $estudiante->delete();

        return back()->with('success', '¡Estudiante eliminado correctamente!');
    }

    // Importar desde CSV nativo
    public function import(Request $request)
    {
        $request->validate([
            'archivo_excel' => 'required|file|max:5120', 
        ], [
            'archivo_excel.required' => 'Por favor seleccione un archivo.',
        ]);

        $extension = $request->file('archivo_excel')->getClientOriginalExtension();
        if (strtolower($extension) !== 'csv') {
            return back()->with('error', 'Error: El archivo debe tener la extensión .csv');
        }

        try {
            $file = $request->file('archivo_excel');
            
            $contenido = file_get_contents($file->getRealPath());
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'UTF-8, ISO-8859-1');
            $contenido = str_replace(["\r\n", "\r"], "\n", $contenido);
            
            $lineas = explode("\n", $contenido);

            if (count($lineas) < 2) {
                return back()->with('error', 'El archivo está vacío o no tiene el formato correcto.');
            }

            $delimitador = strpos($lineas[0], ';') !== false ? ';' : ',';
            $contador = 0;

            for ($i = 1; $i < count($lineas); $i++) {
                $linea = trim($lineas[$i]);
                if (empty($linea)) continue; 

                $row = str_getcsv($linea, $delimitador);

                if (count($row) >= 4) {
                    $cursoLimpio = strtoupper(trim($row[2]));
                    
                    if (preg_match('/^(0K|OK|P3|PK)/i', $cursoLimpio)) {
                        $nivelCalculado = 'NIVEL INICIAL';
                    } elseif (preg_match('/^(01|02|03|04|05|06)/', $cursoLimpio)) {
                        $nivelCalculado = 'NIVEL PRIMARIA';
                    } elseif (preg_match('/^(07|08|09|10|11|12)/', $cursoLimpio)) {
                        $nivelCalculado = 'NIVEL SECUNDARIA';
                    } else {
                        $nivelCalculado = isset($row[4]) && !empty(trim($row[4])) ? strtoupper(trim($row[4])) : 'NIVEL GENERAL';
                    }

                    Estudiante::updateOrCreate(
                        ['codigo' => trim($row[0])],
                        [
                            'nombre'   => trim($row[1]),
                            'curso'    => trim($row[2]),
                            'paralelo' => trim($row[3]),
                            'estado'   => $nivelCalculado,
                        ]
                    );
                    $contador++;
                }
            }
            
            if ($contador == 0) {
                return back()->with('error', 'Se leyó el archivo pero no se encontraron datos válidos.');
            }

            return back()->with('success', '¡Éxito total! Se han importado ' . $contador . ' estudiantes clasificados por Nivel.');
            
        } catch (\Throwable $e) {
            return back()->with('error', 'ERROR: ' . $e->getMessage() . ' (Línea ' . $e->getLine() . ')');
        }
    }

    // Exportar a CSV
    public function export()
    {
        $estudiantes = Estudiante::select('codigo', 'nombre', 'curso', 'paralelo', 'estado')->get();
        $fileName = 'Reporte_Estudiantes_SEA.csv';
        
        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($estudiantes) {
            $file = fopen('php://output', 'w');
            fputs($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            
            fputcsv($file, ['Código', 'Nombre Completo', 'Curso', 'Paralelo', 'Nivel']);
            
            foreach ($estudiantes as $estudiante) {
                fputcsv($file, [
                    $estudiante->codigo, 
                    $estudiante->nombre, 
                    $estudiante->curso, 
                    $estudiante->paralelo, 
                    $estudiante->estado
                ]);
            }
            fclose($file);
        };
    }
        public function estadoCuenta($id)
   {
    $estudiante = Estudiante::with(['planPago.cuotas'])->findOrFail($id);
    $plan = $estudiante->planPago;

    $totalComprometido = $plan ? $plan->total_bs : 0;
    $totalPagado = $plan ? $plan->cuotas->where('estado', 'PAGADO')->sum('monto_bs') : 0;
    $saldoPendiente = $totalComprometido - $totalPagado;

    $cuotasPagadas = $plan ? $plan->cuotas->where('estado', 'PAGADO')->sortBy('numero_cuota') : collect();
    $cuotasPendientes = $plan ? $plan->cuotas->where('estado', 'PENDIENTE')->sortBy('numero_cuota') : collect();

    return view('estudiantes.estado_cuenta', compact(
        'estudiante',
        'plan',
        'totalComprometido',
        'totalPagado',
        'saldoPendiente',
        'cuotasPagadas',
        'cuotasPendientes'
    ));
}
}

        