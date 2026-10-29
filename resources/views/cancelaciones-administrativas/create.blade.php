@extends('layouts.app')

@section('title', 'Nueva cancelación administrativa')
@section('page-title', '➕ Nueva cancelación administrativa')
@section('page-subtitle', 'Seleccione una factura timbrada elegible. No cancela el CFDI ante el SAT.')
@section('page-actions')
    <a href="{{ route('cancelaciones-administrativas.index') }}" class="btn btn-light">← Historial</a>
@endsection

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Cancelaciones administrativas', 'url' => route('cancelaciones-administrativas.index')],
    ['title' => 'Nueva'],
];
@endphp

@section('content')

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:16px;">
    @foreach($errors->all() as $err)
        <div>{{ $err }}</div>
    @endforeach
</div>
@endif

<div class="cancel-admin-create-grid" style="display:grid; grid-template-columns: {{ $facturaSeleccionada ? '1.2fr 1fr' : '1fr' }}; gap:20px; align-items:start;">

    <div>
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                <div class="card-title">Buscar factura candidata</div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('cancelaciones-administrativas.create') }}" class="filtros-bar" style="flex-wrap:wrap; gap:12px; align-items:flex-end;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Desde emisión</label>
                        <input type="date" name="fecha_desde" class="form-control" value="{{ request('fecha_desde') }}">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Hasta emisión</label>
                        <input type="date" name="fecha_hasta" class="form-control" value="{{ request('fecha_hasta') }}">
                    </div>
                    <div class="form-group" style="margin:0; flex:1; min-width:200px;">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="UUID, folio, cliente, RFC…">
                    </div>
                    @if($facturaSeleccionada)
                    <input type="hidden" name="factura_id" value="{{ $facturaSeleccionada->id }}">
                    @endif
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    @if(request()->anyFilled(['fecha_desde', 'fecha_hasta', 'q', 'factura_id']))
                    <a href="{{ route('cancelaciones-administrativas.create') }}" class="btn btn-light">Limpiar</a>
                    @endif
                </form>
                <p class="form-hint" style="margin:12px 0 0;">
                    Solo facturas <strong>timbradas</strong>. No aplica si hay complementos, NC, devoluciones o pagos en CxC.
                    Si la factura vino del importador CFDI sin salida de inventario, <strong>no se crean movimientos de inventario</strong>.
                </p>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th class="td-right">Total</th>
                        <th>¿Puede?</th>
                        <th class="td-actions">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($facturas as $f)
                    <tr style="{{ $facturaSeleccionada && $facturaSeleccionada->id === $f->id ? 'background: var(--color-gray-50);' : '' }}">
                        <td class="text-mono fw-600">{{ $f->folio_completo }}</td>
                        <td>{{ $f->fecha_emision?->format('d/m/Y') }}</td>
                        <td>
                            <div class="fw-600">{{ $f->cliente?->nombre ?? $f->nombre_receptor }}</div>
                            <div class="text-muted" style="font-size:12px;">{{ $f->rfc_receptor }}</div>
                        </td>
                        <td class="td-right text-mono">${{ number_format($f->total, 2, '.', ',') }}</td>
                        <td>
                            @if($f->puede_cancelar_admin)
                                <span class="badge badge-success">Sí</span>
                            @else
                                <span class="badge badge-danger" title="{{ e($f->motivo_no_cancelar_admin) }}">No</span>
                            @endif
                        </td>
                        <td class="td-actions">
                            @if($f->puede_cancelar_admin)
                            <a href="{{ route('cancelaciones-administrativas.create', array_filter([
                                'factura_id' => $f->id,
                                'q' => request('q'),
                                'fecha_desde' => request('fecha_desde'),
                                'fecha_hasta' => request('fecha_hasta'),
                            ])) }}" class="btn btn-danger btn-sm">
                                Seleccionar
                            </a>
                            @else
                            <span class="text-muted" style="font-size:12px;">{{ \Illuminate\Support\Str::limit($f->motivo_no_cancelar_admin, 48) }}</span>
                            @endif
                            <a href="{{ route('facturas.show', [$f->id, 'from' => 'cancelaciones-admin']) }}" class="btn btn-light btn-sm" style="margin-left:4px;">Ver</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding:40px;">No hay facturas timbradas con los filtros actuales.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            @if($facturas->isNotEmpty())
            <div style="padding:16px 20px; border-top:1px solid var(--color-gray-100);">
                {{ $facturas->links() }}
            </div>
            @endif
        </div>
    </div>

    @if($facturaSeleccionada)
    <div>
        <div class="card" style="position:sticky; top:16px;">
            <div class="card-header">
                <div class="card-title">Confirmar cancelación</div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Factura</div>
                    <div class="info-value-sm text-mono fw-600">{{ $facturaSeleccionada->folio_completo }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Cliente</div>
                    <div class="info-value-sm">{{ $facturaSeleccionada->cliente?->nombre ?? $facturaSeleccionada->nombre_receptor }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Total</div>
                    <div class="info-value-sm text-mono">${{ number_format($facturaSeleccionada->total, 2, '.', ',') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Método</div>
                    <div class="info-value-sm">{{ $facturaSeleccionada->metodo_pago }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">CxC</div>
                    <div class="info-value-sm">
                        @if($facturaSeleccionada->cuentaPorCobrar)
                            <span class="badge badge-warning">PPD · se cancelará</span>
                        @else
                            <span class="text-muted">Sin CxC</span>
                        @endif
                    </div>
                </div>

                <div class="alert alert-warning" style="margin:16px 0; font-size:13px; line-height:1.5;">
                    Esta acción cancela la factura <strong>solo en el ERP</strong>.
                    El CFDI <strong>no se envía al PAC/SAT</strong>. Quedará registro de auditoría.
                </div>

                @if(($checkSeleccionado['ok'] ?? false))
                <form method="POST" action="{{ route('cancelaciones-administrativas.store') }}" id="formCancelAdminStore"
                      onsubmit="return confirm('¿Cancelar administrativamente esta factura en el ERP? Quedará registrado en auditoría.');">
                    @csrf
                    <input type="hidden" name="factura_id" value="{{ $facturaSeleccionada->id }}">
                    <div class="form-group">
                        <label class="form-label">Motivo <span class="req">*</span></label>
                        <textarea name="motivo" class="form-control" rows="4" required minlength="10" maxlength="2000"
                                  placeholder="Describe el motivo (mínimo 10 caracteres).">{{ old('motivo') }}</textarea>
                        <p class="form-hint" style="margin-top:6px;">Registro obligatorio para auditoría (10–2000 caracteres).</p>
                    </div>
                    <button type="submit" class="btn btn-danger w-full">Confirmar cancelación en ERP</button>
                    <a href="{{ route('cancelaciones-administrativas.create', request()->only(['q', 'fecha_desde', 'fecha_hasta'])) }}"
                       class="btn btn-light w-full" style="margin-top:8px;">Cancelar selección</a>
                </form>
                @else
                <div class="alert alert-danger" style="margin:0; font-size:13px;">
                    {{ $checkSeleccionado['mensaje'] ?? 'Esta factura ya no es elegible.' }}
                </div>
                <a href="{{ route('cancelaciones-administrativas.create', request()->only(['q', 'fecha_desde', 'fecha_hasta'])) }}"
                   class="btn btn-light w-full" style="margin-top:12px;">Elegir otra factura</a>
                @endif
            </div>
        </div>
    </div>
    @endif

</div>

@endsection

@push('styles')
<style>
@media (max-width: 900px) {
    .cancel-admin-create-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush
