@extends('layouts.app')
@section('title', 'Nuevo envío masivo — Logística')
@section('page-title', '📋 Nuevo envío masivo')
@section('page-subtitle', 'Selecciona varias facturas timbradas; luego registrarás cada envío en orden (misma validación que el listado individual)')

@php
$breadcrumbs = [
    ['title' => 'Logística', 'url' => route('logistica.index')],
    ['title' => 'Elegir documento', 'url' => route('logistica.elegir-origen')],
    ['title' => 'Envío masivo'],
];
@endphp

@section('page-actions')
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        <a href="{{ route('logistica.elegir-origen') }}" class="btn btn-light">← Elegir documento (individual)</a>
        <a href="{{ route('logistica.create') }}" class="btn btn-outline">➕ Búsqueda libre</a>
    </div>
@endsection

@section('content')

<p class="text-muted" style="margin:0 0 16px;font-size:13px;">Marca las facturas que permiten <strong>nuevo envío</strong> (misma regla que «Nuevo envío» en el listado). Al confirmar se abrirá el alta del <strong>primero</strong>; al guardar cada envío, el sistema abrirá el siguiente hasta terminar la cola.</p>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <form method="GET" action="{{ route('logistica.elegir-origen-masivo') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:220px;">
                <label class="form-label">Buscar facturas</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Folio, cliente, UUID, RFC..." class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
            @if($search)
                <a href="{{ route('logistica.elegir-origen-masivo') }}" class="btn btn-light">Limpiar</a>
            @endif
        </form>
    </div>
</div>

<form method="POST" action="{{ route('logistica.elegir-origen-masivo') }}" id="formMasivo">
    @csrf
    <div class="card">
        <div class="card-header" style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:10px;">
            <div class="card-title">🧾 Facturas timbradas (selección múltiple)</div>
            <button type="button" class="btn btn-light btn-sm" id="btnSeleccionarElegibles">Marcar todas (elegibles)</button>
        </div>
        <div class="table-container" style="border:none;margin:0;">
            @if($facturas->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th class="td-center" style="width:44px;"></th>
                        <th>Folio</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th class="td-center">Estado</th>
                        <th class="td-center">Envío logística</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($facturas as $f)
                    @php
                        $puede = $f->permiteNuevoEnvioLogistica();
                        $enviosFacturaRemision = $f->logisticaEnvios;
                        if ($f->remisionVinculada) {
                            $enviosFacturaRemision = $enviosFacturaRemision->concat($f->remisionVinculada->logisticaEnvios);
                        }
                        $enviosFacturaRemision = $enviosFacturaRemision->unique('id')->sortByDesc('id');
                    @endphp
                    <tr>
                        <td class="td-center">
                            @if($puede)
                                <input type="checkbox" name="factura_ids[]" value="{{ $f->id }}" class="chk-masivo-elegible">
                            @else
                                <span class="text-muted" title="Misma regla que en el listado individual: no aplica nuevo envío." style="cursor:help;">—</span>
                            @endif
                        </td>
                        <td class="text-mono fw-600">{{ $f->folio_completo }}</td>
                        <td>{{ $f->cliente->nombre ?? $f->nombre_receptor }}</td>
                        <td>{{ $f->fecha_emision?->format('d/m/Y') }}</td>
                        <td class="td-center"><span class="badge badge-success">Timbrada</span></td>
                        <td class="td-center">
                            @if($enviosFacturaRemision->isNotEmpty())
                                <span class="text-mono" style="font-size:12px;">{{ $enviosFacturaRemision->pluck('folio')->join(', ') }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;align-items:center;">
                <div>{{ $facturas->links() }}</div>
                <button type="submit" class="btn btn-primary">Nuevo envío masivo</button>
            </div>
            @else
            <p class="text-muted" style="padding:24px;margin:0;">No hay facturas timbradas con los filtros actuales.</p>
            @endif
        </div>
    </div>
</form>

@push('scripts')
<script>
document.getElementById('btnSeleccionarElegibles')?.addEventListener('click', function () {
    document.querySelectorAll('.chk-masivo-elegible').forEach(function (cb) { cb.checked = true; });
});
document.getElementById('formMasivo')?.addEventListener('submit', function (e) {
    const n = document.querySelectorAll('.chk-masivo-elegible:checked').length;
    if (n < 1) {
        e.preventDefault();
        alert('Seleccione al menos una factura elegible para envío.');
    }
});
</script>
@endpush
@endsection
