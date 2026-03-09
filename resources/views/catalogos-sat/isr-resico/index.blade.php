@extends('layouts.app')

@section('title', 'Tabla ISR RESICO')
@section('page-title', '📊 Tabla ISR RESICO')
@section('page-subtitle', 'Aplica a régimen 626 - Régimen Simplificado de Confianza (persona física). Tasas aproximadas por ingreso mensual.')

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Catálogos SAT', 'url' => route('catalogos-sat.index')],
    ['title' => 'Tabla ISR RESICO']
];
@endphp

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <div class="card-title">Tasas por ingreso mensual</div>
        <span class="text-muted small">Modifica los valores si el SAT actualiza porcentajes o montos</span>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('catalogos-sat.isr-resico.update') }}">
            @csrf
            @method('PUT')
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Ingreso mensual desde ($)</th>
                            <th>Ingreso mensual hasta ($)</th>
                            <th>Tasa ISR (%)</th>
                            <th class="td-actions">Quitar</th>
                        </tr>
                    </thead>
                    <tbody id="tasas-body">
                        @foreach($tasas as $tasa)
                        <tr>
                            <td>
                                <input type="number" name="tasas[{{ $loop->index }}][desde]" step="0.01" min="0"
                                       value="{{ old('tasas.'.$loop->index.'.desde', $tasa->desde) }}"
                                       class="form-control" style="max-width: 140px;">
                            </td>
                            <td>
                                <input type="number" name="tasas[{{ $loop->index }}][hasta]" step="0.01" min="0"
                                       value="{{ old('tasas.'.$loop->index.'.hasta', $tasa->hasta) }}"
                                       class="form-control" style="max-width: 140px;">
                            </td>
                            <td>
                                <input type="number" name="tasas[{{ $loop->index }}][tasa]" step="0.0001" min="0" max="1"
                                       value="{{ old('tasas.'.$loop->index.'.tasa', $tasa->tasa) }}"
                                       class="form-control" style="max-width: 100px;"
                                       placeholder="0.01 = 1%">
                            </td>
                            <td class="td-actions">
                                <button type="button" class="btn btn-danger btn-sm btn-icon quitar-fila" title="Quitar">✕</button>
                            </td>
                        </tr>
                        @endforeach
                        @if($tasas->isEmpty())
                        <tr>
                            <td><input type="number" name="tasas[0][desde]" step="0.01" min="0" class="form-control" style="max-width: 140px;"></td>
                            <td><input type="number" name="tasas[0][hasta]" step="0.01" min="0" class="form-control" style="max-width: 140px;"></td>
                            <td><input type="number" name="tasas[0][tasa]" step="0.0001" min="0" max="1" class="form-control" style="max-width: 100px;"></td>
                            <td class="td-actions"><button type="button" class="btn btn-danger btn-sm btn-icon quitar-fila">✕</button></td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <button type="button" id="agregar-fila" class="btn btn-outline btn-sm">+ Agregar rango</button>
                <button type="submit" class="btn btn-primary">💾 Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<div class="alert alert-info" style="margin-top: 16px; font-size: 13px;">
    <strong>Nota:</strong> La tasa se ingresa en decimal (ej. 0.01 = 1%, 0.025 = 2.5%). Los rangos deben ser consecutivos y sin huecos.
</div>

@endsection

@push('scripts')
<script>
(function() {
    let indice = document.querySelectorAll('#tasas-body tr').length;

    document.getElementById('agregar-fila').addEventListener('click', function() {
        const tbody = document.getElementById('tasas-body');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="number" name="tasas[${indice}][desde]" step="0.01" min="0" class="form-control" style="max-width: 140px;"></td>
            <td><input type="number" name="tasas[${indice}][hasta]" step="0.01" min="0" class="form-control" style="max-width: 140px;"></td>
            <td><input type="number" name="tasas[${indice}][tasa]" step="0.0001" min="0" max="1" class="form-control" style="max-width: 100px;"></td>
            <td class="td-actions"><button type="button" class="btn btn-danger btn-sm btn-icon quitar-fila">✕</button></td>
        `;
        tbody.appendChild(tr);
        indice++;
        reindexar();
    });

    document.getElementById('tasas-body').addEventListener('click', function(e) {
        if (e.target.classList.contains('quitar-fila')) {
            const tr = e.target.closest('tr');
            if (document.querySelectorAll('#tasas-body tr').length > 1) {
                tr.remove();
                reindexar();
            }
        }
    });

    function reindexar() {
        document.querySelectorAll('#tasas-body tr').forEach((tr, i) => {
            tr.querySelectorAll('input').forEach((inp, j) => {
                const base = inp.name.match(/tasas\[\d+\]\[(\w+)\]/);
                if (base) inp.name = 'tasas[' + i + '][' + base[1] + ']';
            });
        });
    }
})();
</script>
@endpush
