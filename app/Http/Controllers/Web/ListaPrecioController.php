<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ListaPrecio;
use App\Models\ListaPrecioDetalle;
use App\Models\Cliente;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ListaPrecioPlantillaExport;
use App\Imports\ListaPrecioMasivoImport;
use Dompdf\Dompdf;
use Dompdf\Options;

class ListaPrecioController extends Controller
{
    public function index(Request $request)
    {
        $query = ListaPrecio::with('cliente:id,nombre')->withCount('detalles');
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('nombre', 'like', "%{$s}%")
                  ->orWhere('descripcion', 'like', "%{$s}%");
            });
        }
        $items = $query->orderBy('nombre')->paginate(20)->withQueryString();
        return view('listas-precios.index', compact('items'));
    }

    public function create()
    {
        $item = new ListaPrecio;
        $clientes = Cliente::activos()->orderBy('nombre')->get(['id', 'nombre', 'rfc']);
        $productos = Producto::activos()->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'unidad', 'costo', 'costo_promedio', 'tasa_iva', 'tipo_factor', 'objeto_impuesto', 'tipo_impuesto']);
        return view('listas-precios.create', compact('item', 'clientes', 'productos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:120',
            'descripcion' => 'nullable|string|max:500',
            'cliente_id' => 'nullable|exists:clientes,id',
            'activo' => 'boolean',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.tipo_utilidad' => 'required|in:factorizado,margen',
            'productos.*.valor_utilidad' => 'required|numeric|min:1|max:99',
            'productos.*.activo' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $lista = ListaPrecio::create([
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'cliente_id' => $validated['cliente_id'] ?? null,
                'activo' => $request->boolean('activo', true),
            ]);

            foreach ($validated['productos'] as $i => $p) {
                ListaPrecioDetalle::create([
                    'lista_precio_id' => $lista->id,
                    'producto_id' => $p['producto_id'],
                    'tipo_utilidad' => $p['tipo_utilidad'],
                    'valor_utilidad' => $p['valor_utilidad'],
                    'orden' => $i,
                    'activo' => $request->boolean("productos.{$i}.activo", true),
                ]);
            }
            DB::commit();
            return redirect()->route('listas-precios.show', $lista->id)
                ->with('success', 'Lista de precios creada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(ListaPrecio $listaPrecio)
    {
        $listaPrecio->load(['cliente', 'detalles.producto']);
        return view('listas-precios.show', compact('listaPrecio'));
    }

    public function edit(ListaPrecio $listaPrecio)
    {
        $item = $listaPrecio;
        $clientes = Cliente::activos()->orderBy('nombre')->get(['id', 'nombre', 'rfc']);
        $productos = Producto::activos()->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'unidad', 'costo', 'costo_promedio', 'tasa_iva', 'tipo_factor', 'objeto_impuesto', 'tipo_impuesto']);
        $listaPrecio->load('detalles.producto');
        return view('listas-precios.edit', compact('item', 'clientes', 'productos', 'listaPrecio'));
    }

    public function update(Request $request, ListaPrecio $listaPrecio)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:120',
            'descripcion' => 'nullable|string|max:500',
            'cliente_id' => 'nullable|exists:clientes,id',
            'activo' => 'boolean',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.tipo_utilidad' => 'required|in:factorizado,margen',
            'productos.*.valor_utilidad' => 'required|numeric|min:1|max:99',
            'productos.*.activo' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $listaPrecio->update([
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'cliente_id' => $validated['cliente_id'] ?? null,
                'activo' => $request->boolean('activo', true),
            ]);

            $listaPrecio->detalles()->delete();
            foreach ($validated['productos'] as $i => $p) {
                ListaPrecioDetalle::create([
                    'lista_precio_id' => $listaPrecio->id,
                    'producto_id' => $p['producto_id'],
                    'tipo_utilidad' => $p['tipo_utilidad'],
                    'valor_utilidad' => $p['valor_utilidad'],
                    'orden' => $i,
                    'activo' => $request->boolean("productos.{$i}.activo", true),
                ]);
            }
            DB::commit();
            return redirect()->route('listas-precios.show', $listaPrecio->id)
                ->with('success', 'Lista de precios actualizada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function editarMasivamente(ListaPrecio $listaPrecio)
    {
        $listaPrecio->load('detalles.producto');
        return view('listas-precios.editar-masivamente', compact('listaPrecio'));
    }

    public function descargarPlantilla(ListaPrecio $listaPrecio)
    {
        $listaPrecio->load('detalles.producto');
        $filename = 'plantilla_lista_' . Str::slug($listaPrecio->nombre) . '.xlsx';
        return Excel::download(
            new ListaPrecioPlantillaExport($listaPrecio->detalles->all()),
            $filename,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function importarMasivo(Request $request, ListaPrecio $listaPrecio)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        try {
            Excel::import(new ListaPrecioMasivoImport($listaPrecio->id), $request->file('archivo'));
            return redirect()->route('listas-precios.show', $listaPrecio)
                ->with('success', 'Lista actualizada exitosamente.');
        } catch (\Exception $e) {
            return back()->with('error', 'Error al importar: ' . $e->getMessage());
        }
    }

    public function toggleActivo(ListaPrecio $listaPrecio)
    {
        $listaPrecio->update(['activo' => !$listaPrecio->activo]);
        return redirect()->route('listas-precios.index')
            ->with('success', $listaPrecio->activo ? 'Lista activada.' : 'Lista desactivada.');
    }

    public function destroy(ListaPrecio $listaPrecio)
    {
        $listaPrecio->detalles()->delete();
        $listaPrecio->delete();
        return redirect()->route('listas-precios.index')
            ->with('success', 'Lista de precios eliminada.');
    }

    public function verPDF(ListaPrecio $listaPrecio)
    {
        $listaPrecio->load(['detalles.producto']);
        $html = view('pdf.lista-precio', compact('listaPrecio'))->render();
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Lista_' . Str::slug($listaPrecio->nombre) . '.pdf"',
        ]);
    }

    public function verPDFCliente(ListaPrecio $listaPrecio)
    {
        $listaPrecio->load(['detalles' => fn($q) => $q->where('activo', true), 'detalles.producto']);
        $html = view('pdf.lista-precio-cliente', compact('listaPrecio'))->render();
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Lista_Cliente_' . Str::slug($listaPrecio->nombre) . '.pdf"',
        ]);
    }

    public function descargarPDF(ListaPrecio $listaPrecio)
    {
        $listaPrecio->load(['detalles.producto']);
        $html = view('pdf.lista-precio', compact('listaPrecio'))->render();
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        $filename = 'Lista_' . Str::slug($listaPrecio->nombre) . '.pdf';
        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
