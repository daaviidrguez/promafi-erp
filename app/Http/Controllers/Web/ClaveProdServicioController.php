<?php

namespace App\Http\Controllers\Web;

use App\Exports\ClaveProdServicioPlantillaExport;
use App\Http\Controllers\Controller;
use App\Imports\ClaveProdServicioImport;
use App\Models\ClaveProdServicio;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClaveProdServicioController extends Controller
{
    public function index()
    {
        $items = ClaveProdServicio::orderBy('orden')->orderBy('clave')->paginate(50);
        return view('catalogos-sat.claves-producto-servicio.index', compact('items'));
    }

    public function create()
    {
        $item = new ClaveProdServicio;
        return view('catalogos-sat.claves-producto-servicio.create', compact('item'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:8|unique:claves_producto_servicio,clave',
            'descripcion' => 'required|string|max:500',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        ClaveProdServicio::create($validated);
        return redirect()->route('catalogos-sat.claves-producto-servicio.index')
            ->with('success', 'Clave producto/servicio creada.');
    }

    public function edit(ClaveProdServicio $claves_producto_servicio)
    {
        $item = $claves_producto_servicio;
        return view('catalogos-sat.claves-producto-servicio.edit', compact('item'));
    }

    public function update(Request $request, ClaveProdServicio $claves_producto_servicio)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:8|unique:claves_producto_servicio,clave,' . $claves_producto_servicio->id,
            'descripcion' => 'required|string|max:500',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        $claves_producto_servicio->update($validated);
        return redirect()->route('catalogos-sat.claves-producto-servicio.index')
            ->with('success', 'Clave producto/servicio actualizada.');
    }

    public function destroy(ClaveProdServicio $claves_producto_servicio)
    {
        $claves_producto_servicio->delete();
        return redirect()->route('catalogos-sat.claves-producto-servicio.index')
            ->with('success', 'Clave producto/servicio eliminada.');
    }

    /**
     * Descargar plantilla Excel para carga masiva (columnas: clave, descripcion).
     */
    public function descargarPlantilla(): BinaryFileResponse
    {
        return Excel::download(
            new ClaveProdServicioPlantillaExport,
            'plantilla_claves_producto_servicio.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    /**
     * Importar claves desde Excel (misma estructura que la plantilla).
     */
    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            Excel::import(new ClaveProdServicioImport, $request->file('archivo'));
            return redirect()->route('catalogos-sat.claves-producto-servicio.index')
                ->with('success', 'Claves importadas correctamente.');
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $msg = 'Errores en filas: ';
            foreach ($failures as $f) {
                $msg .= 'Fila ' . $f->row() . ': ' . implode(', ', $f->errors()) . '; ';
            }
            return back()->with('error', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al importar: ' . $e->getMessage());
        }
    }
}
