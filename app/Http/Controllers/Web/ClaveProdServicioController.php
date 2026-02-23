<?php

namespace App\Http\Controllers\Web;

use App\Exports\ClaveProdServicioPlantillaExport;
use App\Http\Controllers\Controller;
use App\Imports\ClaveProdServicioImport;
use App\Jobs\ImportarClavesProdServicioJob;
use App\Models\ClaveProdServicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClaveProdServicioController extends Controller
{
    public function index(Request $request)
    {
        $query = ClaveProdServicio::query();
        $search = $request->filled('search') ? trim($request->search) : null;
        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('clave', 'like', '%' . $search . '%')
                  ->orWhere('descripcion', 'like', '%' . $search . '%');
            });
        }
        $items = $query->orderBy('orden')->orderBy('clave')->paginate(50)->withQueryString();
        $totalItems = (clone $query)->count();
        return view('catalogos-sat.claves-producto-servicio.index', compact('items', 'totalItems', 'search'));
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
     * Archivos grandes (ej. 54k filas) se procesan en segundo plano para evitar timeout.
     */
    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:51200',
        ], [
            'archivo.max' => 'El archivo no debe superar 50 MB. Para más de 50 mil filas considera dividirlo.',
        ]);

        $file = $request->file('archivo');
        $path = $file->store('imports/claves', 'local');
        if (!$path) {
            return back()->with('error', 'No se pudo guardar el archivo.');
        }

        ImportarClavesProdServicioJob::dispatch($path);

        return redirect()->route('catalogos-sat.claves-producto-servicio.index')
            ->with('success', 'Importación en segundo plano. Con 54 mil filas puede tardar varios minutos. Ejecuta en la terminal: php artisan queue:work — y actualiza esta página para ver las claves.');
    }
}
