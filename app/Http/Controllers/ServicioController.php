<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Servicio;

class ServicioController extends Controller
{
    public function index(Request $request)
    {
        $buscar = $request->get('buscar');

        $servicios = Servicio::when($buscar, function ($query, $buscar) {
            return $query->where('codigo', 'LIKE', "%{$buscar}%")
                         ->orWhere('nombre', 'LIKE', "%{$buscar}%")
                         ->orWhere('nivel', 'LIKE', "%{$buscar}%");
        })->orderBy('id', 'desc')->get();

        return view('servicios.index', compact('servicios', 'buscar'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo'    => 'required|string|max:50',
            'nombre'    => 'required|string|max:255',
            'nivel'     => 'required|string',
            'tipo_pago' => 'required|string',
            'monto_bs'  => 'required|numeric|min:0',
        ]);

        $monto_usd = $request->filled('monto_usd') ? $request->monto_usd : round($request->monto_bs / 6.96, 2);

        Servicio::create([
            'codigo'            => strtoupper(trim($request->codigo)),
            'nombre'            => trim($request->nombre),
            'nivel'             => $request->nivel,
            'cursos_permitidos' => $request->cursos_permitidos,
            'tipo_pago'         => $request->tipo_pago,
            'descuento_anual'   => $request->descuento_anual ?? 0,
            'monto_bs'          => $request->monto_bs,
            'monto_usd'         => $monto_usd,
            'gestion'           => $request->gestion ?? 2026,
        ]);

        return back()->with('success', '¡Servicio registrado correctamente!');
    }

    public function update(Request $request, $id)
    {
        $servicio = Servicio::findOrFail($id);

        $request->validate([
            'codigo'    => 'required|string|max:50',
            'nombre'    => 'required|string|max:255',
            'monto_bs'  => 'required|numeric|min:0',
        ]);

        $monto_usd = $request->filled('monto_usd') ? $request->monto_usd : round($request->monto_bs / 6.96, 2);

        $servicio->update([
            'codigo'            => strtoupper(trim($request->codigo)),
            'nombre'            => trim($request->nombre),
            'nivel'             => $request->nivel,
            'cursos_permitidos' => $request->cursos_permitidos,
            'tipo_pago'         => $request->tipo_pago,
            'descuento_anual'   => $request->descuento_anual ?? 0,
            'monto_bs'          => $request->monto_bs,
            'monto_usd'         => $monto_usd,
            'gestion'           => $request->gestion ?? 2026,
        ]);

        return back()->with('success', '¡Servicio actualizado!');
    }

    public function destroy($id)
    {
        $servicio = Servicio::findOrFail($id);
        $servicio->delete();
        return back()->with('success', '¡Servicio eliminado!');
    }
}