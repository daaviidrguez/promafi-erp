<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ClienteMetaComercial;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClienteMetaComercialController extends Controller
{
    public function store(Request $request, Cliente $cliente)
    {
        $validated = $this->validateMeta($request, $cliente);

        $meta = $cliente->metasComerciales()->create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'id' => $meta->id,
            ]);
        }

        return redirect()->route('clientes.show', $cliente)
            ->with('success', 'Meta comercial agregada correctamente');
    }

    public function update(Request $request, Cliente $cliente, ClienteMetaComercial $metaComercial)
    {
        if ((int) $metaComercial->cliente_id !== (int) $cliente->id) {
            abort(403);
        }

        $validated = $this->validateMeta($request, $cliente, $metaComercial);

        $metaComercial->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'id' => $metaComercial->id,
            ]);
        }

        return redirect()->route('clientes.show', $cliente)
            ->with('success', 'Meta comercial actualizada correctamente');
    }

    public function destroy(Request $request, Cliente $cliente, ClienteMetaComercial $metaComercial)
    {
        if ((int) $metaComercial->cliente_id !== (int) $cliente->id) {
            abort(403);
        }

        $metaComercial->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('clientes.show', $cliente)
            ->with('success', 'Meta comercial eliminada correctamente');
    }

    private function validateMeta(Request $request, Cliente $cliente, ?ClienteMetaComercial $meta = null): array
    {
        $periodo = $request->input('periodo', ClienteMetaComercial::PERIODO_ANUAL);

        $unique = Rule::unique('cliente_metas_comerciales')
            ->where(fn ($q) => $q
                ->where('cliente_id', $cliente->id)
                ->where('periodo', $periodo));

        if ($meta) {
            $unique = $unique->ignore($meta->id);
        }

        return $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100', $unique],
            'periodo' => ['required', Rule::in([
                ClienteMetaComercial::PERIODO_ANUAL,
                ClienteMetaComercial::PERIODO_MENSUAL,
            ])],
            'monto_meta' => 'required|numeric|min:0.01|max:999999999999.99',
            'notas' => 'nullable|string|max:2000',
        ], [
            'anio.required' => 'El año es obligatorio.',
            'anio.unique' => 'Ya existe una meta para ese periodo en este cliente.',
            'periodo.required' => 'El periodo es obligatorio.',
            'periodo.in' => 'El periodo debe ser anual o mensual.',
            'monto_meta.required' => 'El monto de la meta es obligatorio.',
            'monto_meta.min' => 'El monto de la meta debe ser mayor a cero.',
        ]);
    }
}
