@extends('layouts.app')

@section('title', 'Cancelaciones administrativas')
@section('page-title', '🛑 Cancelaciones administrativas')
@section('page-subtitle', 'Cancelación solo en ERP (sin PAC). Revoca saldo del cliente; inventario solo si hubo salida por timbrado.')

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Cancelaciones administrativas'],
];
$baseUrlCancelAdmin = url('/cancelaciones-administrativas');
@endphp

@section('content')

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:16px;">
    @foreach($errors->all() as $err)
        <div>{{ $err }}</div>
    @endforeach
</div>
@endif

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
@endif

<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <form method="GET" action="{{ route('cancelaciones-administrativas.index') }}" class="filtros-bar" style="flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="{{ request('fecha_desde') }}">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="{{ request('fecha_hasta') }}">
            </div>
            <div class="form-group" style="margin:0; flex:1; min-width:200px;">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="UUID, folio, cliente, RFC…">
            </div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            @if(request()->anyFilled(['fecha_desde', 'fecha_hasta', 'q']))
            <a href="{{ route('cancelaciones-administrativas.index') }}" class="btn btn-light">Limpiar</a>
            @endif
        </form>
        <p class="form-hint" style="margin:12px 0 0;">
            Listado: facturas <strong>timbradas</strong> vigentes, orden <strong>folio de mayor a menor</strong>.
            No sustituye la cancelación ante el SAT. Si hubo salida de inventario al timbrar en el ERP, aquí se reversa; si la factura vino del <strong>importador CFDI</strong> sin movimientos de salida, <strong>no se crean movimientos de inventario</strong>.
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
                <th>Método</th>
                <th>CxC</th>
                <th>¿Puede?</th>
                <th class="td-actions">Acción</th>
            </tr>
        </thead>
        <tbody>
            @forelse($facturas as $f)
            <tr>
                <td class="text-mono fw-600">{{ $f->folio_completo }}</td>
                <td>{{ $f->fecha_emision?->format('d/m/Y') }}</td>
                <td>
                    <div class="fw-600">{{ $f->cliente?->nombre ?? $f->nombre_receptor }}</div>
                    <div class="text-muted" style="font-size:12px;">{{ $f->rfc_receptor }}</div>
                </td>
                <td class="td-right text-mono">${{ number_format($f->total, 2, '.', ',') }}</td>
                <td>{{ $f->metodo_pago }}</td>
                <td>
                    @if($f->cuentaPorCobrar)
                        <span class="badge badge-warning">PPD</span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td>
                    @if($f->puede_cancelar_admin)
                        <span class="badge badge-success">Sí</span>
                    @else
                        <span class="badge badge-danger" title="{{ e($f->motivo_no_cancelar_admin) }}">No</span>
                    @endif
                </td>
                <td class="td-actions">
                    @if($f->puede_cancelar_admin)
                    <button type="button"
                            class="btn btn-danger btn-sm js-open-cancel-admin"
                            data-factura-id="{{ $f->id }}"
                            data-folio="{{ e($f->folio_completo) }}">
                        Cancelar administrativamente
                    </button>
                    @else
                    <span class="text-muted" style="font-size:12px;">{{ \Illuminate\Support\Str::limit($f->motivo_no_cancelar_admin, 72) }}</span>
                    @endif
                    <a href="{{ route('facturas.show', $f->id) }}" class="btn btn-light btn-sm" style="margin-left:4px;">Ver</a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="text-center text-muted" style="padding:40px;">No hay facturas timbradas con los filtros actuales.</td>
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

<div id="modalCancelAdmin" class="modal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div class="modal-title" id="modalCancelAdminTitulo">Cancelación administrativa</div>
            <button type="button" class="modal-close" onclick="cerrarModalCancelAdmin()">✕</button>
        </div>
        <form id="formCancelAdmin" method="POST" action="">
            @csrf
            <div class="modal-body">
                <p class="form-hint" style="margin-top:0;">Registro obligatorio para auditoría. No cancela el CFDI ante el SAT.</p>
                <div class="form-group">
                    <label class="form-label">Motivo <span class="req">*</span></label>
                    <textarea name="motivo" id="cancel_admin_motivo" class="form-control" rows="4" required minlength="10" maxlength="2000" placeholder="Describe el motivo (mínimo 10 caracteres)."></textarea>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" class="btn btn-light" onclick="cerrarModalCancelAdmin()">Cerrar</button>
                <button type="submit" class="btn btn-danger">Confirmar cancelación en ERP</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    var cancelAdminBaseUrl = @json($baseUrlCancelAdmin);
    function abrirModalCancelAdmin(id, folio) {
        document.getElementById('modalCancelAdminTitulo').textContent = 'Cancelar — ' + folio;
        document.getElementById('formCancelAdmin').action = cancelAdminBaseUrl + '/' + id;
        document.getElementById('cancel_admin_motivo').value = '';
        document.getElementById('modalCancelAdmin').classList.add('show');
    }
    window.cerrarModalCancelAdmin = function() {
        document.getElementById('modalCancelAdmin').classList.remove('show');
    };
    document.querySelectorAll('.js-open-cancel-admin').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = parseInt(this.getAttribute('data-factura-id'), 10);
            var folio = this.getAttribute('data-folio') || '';
            abrirModalCancelAdmin(id, folio);
        });
    });
    document.getElementById('modalCancelAdmin')?.addEventListener('click', function(e) {
        if (e.target === this) window.cerrarModalCancelAdmin();
    });
    document.getElementById('formCancelAdmin')?.addEventListener('submit', function(e) {
        if (!confirm('¿Cancelar administrativamente esta factura en el ERP? Quedará registrado en auditoría.')) {
            e.preventDefault();
        }
    });
})();
</script>
@endpush
