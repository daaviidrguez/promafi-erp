@extends('layouts.app')
@section('title', 'Envío ' . $envio->folio)
@section('page-title', '📦 Envío ' . $envio->folio)
@section('page-subtitle', $envio->cliente->nombre ?? 'Cliente')

@php
$breadcrumbs = [
    ['title' => 'Logística', 'url' => route('logistica.index')],
    ['title' => $envio->folio],
];
@endphp

@section('page-actions')
    <a href="{{ route('logistica.ver-pdf', $envio) }}" class="btn btn-light" target="_blank" rel="noopener">Ver PDF</a>
    <a href="{{ route('logistica.descargar-pdf', $envio) }}" class="btn btn-primary">⬇ PDF firma</a>
@endsection

@section('content')

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">📋 Datos del envío</div></div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Estado</div>
                        <div class="info-value">
                            @if($envio->estado === 'pendiente')<span class="badge badge-warning">{{ $envio->estado_etiqueta }}</span>
                            @elseif($envio->estado === 'entrega_parcial')<span class="badge badge-warning">{{ $envio->estado_etiqueta }}</span>
                            @elseif($envio->estado === 'entregado')<span class="badge badge-success">{{ $envio->estado_etiqueta }}</span>
                            @elseif($envio->estado === 'cancelado')<span class="badge badge-danger">{{ $envio->estado_etiqueta }}</span>
                            @else<span class="badge badge-info">{{ $envio->estado_etiqueta }}</span>@endif
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Origen</div>
                        <div class="info-value">
                            @if($envio->factura_id && $envio->factura)
                                Factura <span class="text-mono">{{ $envio->factura->folio_completo }}</span>
                            @elseif($envio->remision_id && $envio->remision)
                                Remisión <span class="text-mono">{{ $envio->remision->folio }}</span>
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    @if($envio->usuario)
                    <div class="info-row">
                        <div class="info-label">Registró</div>
                        <div class="info-value">{{ $envio->usuario->name }}</div>
                    </div>
                    @endif
                    <div class="info-row">
                        <div class="info-label">Alta</div>
                        <div class="info-value">{{ $envio->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">📍🚚 Dirección de entrega</div></div>
            <div class="card-body">
                @if($envio->direccionEntregaRel)
                    <div class="text-muted" style="font-size:12px;margin-bottom:6px;">Catálogo cliente: {{ $envio->direccionEntregaRel->sucursal_almacen ?? '—' }}</div>
                @endif
                <div style="white-space:pre-wrap;">{{ $envio->direccion_entrega ?: '—' }}</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">📦 Productos enviados</div></div>
            <div class="table-container" style="border:none;margin:0;">
                <table>
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th class="td-center">Cant.</th>
                            <th>Vínculo</th>
                            <th class="td-center" id="th-col-entregado" style="{{ auth()->user()->can('logistica.editar') || in_array($envio->estado, ['entrega_parcial', 'entregado'], true) ? '' : 'display:none;' }}">Entregado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($envio->items as $it)
                        <tr>
                            <td>{{ $it->descripcion }}</td>
                            <td class="td-center text-mono">{{ $it->cantidad }}</td>
                            <td class="text-muted" style="font-size:12px;">
                                @if($it->factura_detalle_id)
                                    Factura {{ $envio->factura?->folio_completo ?? ('#' . $it->factura_detalle_id) }}
                                @elseif($it->remision_detalle_id)
                                    Remisión {{ $envio->remision?->folio ?? $it->remisionDetalle?->remision?->folio ?? '—' }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="td-center td-col-entregado" style="{{ auth()->user()->can('logistica.editar') || in_array($envio->estado, ['entrega_parcial', 'entregado'], true) ? '' : 'display:none;' }}">
                                @can('logistica.editar')
                                    @if($envio->estado === 'entregado')
                                        @if($it->linea_entregada)<span class="badge badge-success">Sí</span>@else<span class="badge badge-gray">No</span>@endif
                                    @else
                                        <label style="display:flex;align-items:center;justify-content:center;gap:6px;cursor:pointer;margin:0;font-size:13px;">
                                            <input type="checkbox"
                                                   name="item_entregado_ids[]"
                                                   value="{{ $it->id }}"
                                                   form="form-logistica-update"
                                                   {{ $it->linea_entregada ? 'checked' : '' }}
                                                   class="chk-linea-entregado"
                                                   @unless($envio->estado === 'entrega_parcial') disabled @endunless>
                                            <span class="text-muted lbl-entregado">{{ $it->linea_entregada ? 'Sí' : 'No' }}</span>
                                        </label>
                                    @endif
                                @else
                                    @if(in_array($envio->estado, ['entrega_parcial', 'entregado'], true))
                                        @if($it->linea_entregada)<span class="badge badge-success">Sí</span>@else<span class="badge badge-gray">No</span>@endif
                                    @else
                                        —
                                    @endif
                                @endcan
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @can('logistica.editar')
            <p id="hint-entrega-parcial" class="text-muted small" style="padding:12px 16px;margin:0;{{ $envio->estado === 'entrega_parcial' ? '' : 'display:none;' }}">
                Marca las partidas ya entregadas en destino y guarda con <strong>Guardar cambios</strong> en el panel derecho.
            </p>
            <p id="hint-entrega-parcial-a-entregado" class="text-muted small" style="padding:0 16px 12px;margin:0;display:none;">
                Si pasas a <strong>Entregado</strong>, se conservan las marcas de partidas entregadas que indiques ahora (no se fuerza todo el envío salvo que venga de otro estado sin entrega parcial).
            </p>
            @endcan
        </div>

        @if($envio->remision_id && $envio->remision)
        <div class="card">
            <div class="card-header"><div class="card-title">📊 Pendiente por remisión (todos los envíos)</div></div>
            <div class="card-body text-muted" style="font-size:13px;">
                <p>Remisión vs salida en logística y entrega en destino (checks). Pendiente = aún por entregar al cliente; puedes registrar otro envío mientras haya cantidad sin entregar o hueco de inventario.</p>
                <div class="table-container" style="border:none;margin:0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th class="td-right">Remisión</th>
                                <th class="td-right">En logística</th>
                                <th class="td-right">Entregado destino</th>
                                <th class="td-right">Pendiente entrega</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($envio->remision->detalles as $d)
                                @php
                                    $enLog = \App\Models\LogisticaEnvio::cantidadEnviadaRemisionDetalle($d->id);
                                    $entDest = \App\Models\LogisticaEnvio::cantidadEntregadaEnDestinoRemisionDetalle($d->id);
                                    $pendEnt = \App\Models\LogisticaEnvio::cantidadPendienteEntregaRemisionDetalle($d->id);
                                @endphp
                                <tr>
                                    <td>{{ \Illuminate\Support\Str::limit($d->descripcion, 60) }}</td>
                                    <td class="td-right text-mono">{{ $d->cantidad }}</td>
                                    <td class="td-right text-mono">{{ $enLog }}</td>
                                    <td class="td-right text-mono">{{ $entDest }}</td>
                                    <td class="td-right text-mono">{{ $pendEnt <= 0.0001 ? '0' : $pendEnt }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        @if($envio->factura_id && $envio->factura)
        <div class="card">
            <div class="card-header"><div class="card-title">📊 Pendiente por factura (todos los envíos)</div></div>
            <div class="card-body text-muted" style="font-size:13px;">
                <p>Factura vs salida en logística y entrega en destino (checks). Pendiente entrega = aún por entregar al cliente; puedes registrar otro envío mientras haya cantidad sin entregar o hueco de inventario.</p>
                <div class="table-container" style="border:none;margin:0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th class="td-right">Facturado</th>
                                <th class="td-right">En logística</th>
                                <th class="td-right">Entregado destino</th>
                                <th class="td-right">Pendiente entrega</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($envio->factura->detalles as $d)
                                @php
                                    $enLog = \App\Models\LogisticaEnvio::cantidadEnviadaFacturaDetalle($d->id);
                                    $entDest = \App\Models\LogisticaEnvio::cantidadEntregadaEnDestinoFacturaDetalle($d->id);
                                    $pendEnt = \App\Models\LogisticaEnvio::cantidadPendienteEntregaFacturaDetalle($d->id);
                                @endphp
                                <tr>
                                    <td>{{ \Illuminate\Support\Str::limit($d->descripcion, 60) }}</td>
                                    <td class="td-right text-mono">{{ $d->cantidad }}</td>
                                    <td class="td-right text-mono">{{ $enLog }}</td>
                                    <td class="td-right text-mono">{{ $entDest }}</td>
                                    <td class="td-right text-mono">{{ $pendEnt <= 0.0001 ? '0' : $pendEnt }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    <div>
        @can('logistica.editar')
        <div class="card">
            <div class="card-header"><div class="card-title">⚙️ Flujo y auditoría</div></div>
            <div class="card-body">
                <form method="POST" action="{{ route('logistica.update', $envio) }}" id="form-logistica-update">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label class="form-label">Siguiente estado</label>
                        <select name="estado" id="logistica-estado-select" class="form-control">
                            <option value="" selected>— Sin cambio —</option>
                            @foreach(\App\Models\LogisticaEnvio::ESTADOS as $st)
                                <option value="{{ $st }}">{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nota del cambio (opcional)</label>
                        <input type="text" name="nota_cambio_estado" class="form-control" maxlength="500" placeholder="Motivo o comentario breve">
                    </div>
                    <hr style="margin:16px 0;border:none;border-top:1px solid var(--color-gray-200);">
                    <div class="form-group">
                        <label class="form-label">Chofer</label>
                        <input type="text" name="chofer" value="{{ old('chofer', $envio->chofer) }}" class="form-control" maxlength="200">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quién recibió en almacén</label>
                        <input type="text" name="recibido_almacen" value="{{ old('recibido_almacen', $envio->recibido_almacen) }}" class="form-control" maxlength="200">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lugar de entrega</label>
                        <input type="text" name="lugar_entrega" value="{{ old('lugar_entrega', $envio->lugar_entrega) }}" class="form-control" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quién recibió en destino (firma)</label>
                        <input type="text" name="entrega_recibido_por" value="{{ old('entrega_recibido_por', $envio->entrega_recibido_por) }}" class="form-control" maxlength="200">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dirección (texto libre)</label>
                        <textarea name="direccion_entrega" class="form-control" rows="2">{{ old('direccion_entrega', $envio->direccion_entrega) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dirección catálogo cliente</label>
                        <select name="cliente_direccion_entrega_id" class="form-control">
                            <option value="">— Ninguna —</option>
                            @foreach($envio->cliente->direccionesEntrega ?? [] as $dir)
                                <option value="{{ $dir->id }}" {{ (string)$envio->cliente_direccion_entrega_id === (string)$dir->id ? 'selected' : '' }}>
                                    {{ $dir->sucursal_almacen ?: 'Dir. '.$dir->id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notas</label>
                        <textarea name="notas" class="form-control" rows="2">{{ old('notas', $envio->notas) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Guardar cambios</button>
                </form>
            </div>
        </div>
        @endcan

        <div class="card">
            <div class="card-header"><div class="card-title">🕐 Historial</div></div>
            <div class="card-body" style="max-height:420px;overflow:auto;">
                @forelse($envio->historial as $h)
                    <div style="border-bottom:1px solid var(--color-gray-100);padding:8px 0;font-size:13px;">
                        <div class="fw-600">
                            {{ $h->estado_anterior ?? '—' }} → {{ $h->estado_nuevo }}
                        </div>
                        <div class="text-muted">{{ $h->created_at?->format('d/m/Y H:i') }}
                            @if($h->user) · {{ $h->user->name }}@endif
                        </div>
                        @if($h->nota)
                            <div style="margin-top:4px;">{{ $h->nota }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted">Sin movimientos.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@can('logistica.editar')
@push('scripts')
<script>
(function() {
    const sel = document.getElementById('logistica-estado-select');
    const cur = @json($envio->estado);
    function syncColEntregado() {
        if (!sel) return;
        const v = sel.value;
        const show = v === 'entrega_parcial' || v === 'entregado'
            || (v === '' && (cur === 'entrega_parcial' || cur === 'entregado'));
        document.querySelectorAll('#th-col-entregado, .td-col-entregado').forEach(function(el) {
            el.style.display = show ? '' : 'none';
        });
        const hint = document.getElementById('hint-entrega-parcial');
        if (hint) {
            hint.style.display = (v === 'entrega_parcial' || (v === '' && cur === 'entrega_parcial')) ? '' : 'none';
        }
        const hintEnt = document.getElementById('hint-entrega-parcial-a-entregado');
        if (hintEnt) {
            hintEnt.style.display = (v === 'entregado' && cur === 'entrega_parcial') ? 'block' : 'none';
        }
        const allowChecks = v === 'entrega_parcial' || (v === 'entregado' && cur === 'entrega_parcial') || (v === '' && cur === 'entrega_parcial');
        document.querySelectorAll('.chk-linea-entregado').forEach(function(ch) {
            ch.disabled = !allowChecks;
        });
    }
    sel.addEventListener('change', syncColEntregado);
    syncColEntregado();
    document.querySelectorAll('.chk-linea-entregado').forEach(function(ch) {
        ch.addEventListener('change', function() {
            const lbl = ch.closest('label') && ch.closest('label').querySelector('.lbl-entregado');
            if (lbl) lbl.textContent = ch.checked ? 'Sí' : 'No';
        });
    });
})();
</script>
@endpush
@endcan

@endsection
