<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\IsrResicoTasa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IsrResicoController extends Controller
{
    public function index()
    {
        $tasas = IsrResicoTasa::orderBy('orden')->orderBy('desde')->get();
        return view('catalogos-sat.isr-resico.index', compact('tasas'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'tasas' => 'required|array|min:1',
            'tasas.*.desde' => 'required|numeric|min:0',
            'tasas.*.hasta' => 'required|numeric|min:0',
            'tasas.*.tasa' => 'required|numeric|min:0|max:1',
        ]);

        DB::transaction(function () use ($validated) {
            IsrResicoTasa::truncate();
            foreach ($validated['tasas'] as $i => $rango) {
                IsrResicoTasa::create([
                    'desde' => (float) $rango['desde'],
                    'hasta' => (float) $rango['hasta'],
                    'tasa' => (float) $rango['tasa'],
                    'orden' => $i + 1,
                ]);
            }
        });

        return redirect()
            ->route('catalogos-sat.isr-resico.index')
            ->with('success', 'Tabla ISR RESICO actualizada.');
    }
}
