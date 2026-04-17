@extends('layouts.app')
@section('title', 'Guardar compra desde CFDI')
@section('page-title', '📄 Compra desde CFDI')
@section('page-subtitle', 'Vincule cada línea a un producto (lupa en Código) para que "Recibir mercancía" registre la entrada en inventario')

@php
$breadcrumbs = [
    ['title' => 'Compras', 'url' => route('compras.index')],
    ['title' => 'Leer CFDI', 'url' => route('compras.upload-cfdi')],
    ['title' => 'Guardar compra'],
];
$conceptos = $datos['conceptos'] ?? [];
$fechaEmision = isset($datos['fecha_emision']) ? \Carbon\Carbon::parse($datos['fecha_emision'])->format('Y-m-d') : date('Y-m-d');
$conceptosCount = count($conceptos);
@endphp

@section('content')

@if(session('success'))
    <div class="alert alert-success mb-3">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger mb-3">{{ session('error') }}</div>
@endif

<form action="{{ route('compras.store-desde-cfdi') }}" method="POST" id="formCfdiCompra" data-cfdi-concepto-indices='@json(array_keys($conceptos))'>
@csrf

<div class="responsive-grid" style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">📋 Datos del CFDI</div></div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Fecha <span class="req">*</span></label>
                        <input type="date" name="fecha_emision" value="{{ old('fecha_emision', $fechaEmision) }}" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de pago</label>
                        <input type="text" class="form-control" value="{{ $datos['forma_pago'] ?? '—' }}" readonly style="background:var(--color-gray-50);">
                        <input type="hidden" name="forma_pago" value="{{ $datos['forma_pago'] ?? '01' }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Método de pago</label>
                        <select name="metodo_pago" class="form-control">
                            <option value="PUE" {{ ($datos['metodo_pago'] ?? 'PUE') === 'PUE' ? 'selected' : '' }}>PUE - Una exhibición</option>
                            <option value="PPD" {{ ($datos['metodo_pago'] ?? '') === 'PPD' ? 'selected' : '' }}>PPD - Pago diferido</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Uso del CFDI</label>
                        <input type="text" class="form-control" value="{{ $datos['uso_cfdi'] ?? '—' }}" readonly style="background:var(--color-gray-50);">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">🏭 Proveedor</div></div>
            <div class="card-body">
                <div class="form-group search-box">
                    <input type="text" id="buscarProveedor" placeholder="Buscar proveedor..." autocomplete="off" class="form-control"
                           value="{{ $proveedor ? $proveedor->nombre : ($datos['rfc_emisor'] ?? ($datos['nombre_emisor'] ?? '')) }}">
                    <input type="hidden" name="proveedor_id" id="proveedor_id" value="{{ $proveedor?->id ?? '' }}">
                    <div id="proveedorResults" class="autocomplete-results"></div>
                </div>
                <p class="text-muted small mt-2 mb-0">
                    Emisor CFDI: {{ $datos['nombre_emisor'] ?? '—' }} (RFC: {{ $datos['rfc_emisor'] ?? '—' }})
                </p>
                @if(!$proveedor && !empty($datos['rfc_emisor']))
                    <div class="mt-2">
                        <p class="text-danger small mb-1" style="font-weight:700;">no existe proveedor</p>
                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('modalAgregarProveedorCfdi').classList.add('show')">
                            ➕ Agregar
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">📦 Detalle</div></div>
            <div class="card-body" style="padding:0;">
                <p class="text-muted small" style="padding:0 16px 12px;">Use la lupa en <strong>Código</strong> para vincular cada línea a un producto; así "Recibir mercancía" registrará la entrada en inventario.</p>
                <div class="table-container" style="border:none;margin:0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th class="td-center">Cant.</th>
                                <th class="td-right">Costo unit.</th>
                                <th class="td-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($conceptos as $i => $c)
                            @php
                                $importeLinea = ($c['importe'] ?? 0) - ($c['descuento'] ?? 0);
                                $ivaLinea = collect($c['impuestos'] ?? [])->sum('importe');
                            $noIdent = trim((string) ($c['no_identificacion'] ?? ''));
                            $productoPorProveedorCodigo = null;
                            if ($proveedor && $noIdent !== '' && !empty($productoProveedorMap[strtoupper($noIdent)] ?? null)) {
                                $productoPorProveedorCodigo = $productoProveedorMap[strtoupper($noIdent)] ?? null;
                            }
                            $productoLinea = $productosPorLinea[$i] ?? null;
                            $productoVinculado = $productoPorProveedorCodigo ?? $productoLinea;
                            @endphp
                            <tr data-row="{{ $i }}">
                                <td>
                                    <div style="display:flex;flex-direction:column;align-items:flex-start;gap:6px;">
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <button type="button" class="btn btn-outline btn-sm btn-icon" title="Seleccionar producto" onclick="abrirModalProducto({{ $i }})">🔍</button>
                                        <input type="hidden" name="productos[{{ $i }}][concepto_index]" value="{{ $i }}">
                                        <input type="hidden" name="productos[{{ $i }}][no_identificacion]" id="no_identificacion_{{ $i }}" value="{{ $noIdent }}">
                                    <input type="hidden" name="productos[{{ $i }}][producto_id]" id="producto_id_{{ $i }}"
                                           value="{{ $productoVinculado?->id ?? '' }}">
                                    <span id="codigo_display_{{ $i }}" class="text-mono" style="font-size:13px;">
                                        @if($productoVinculado)
                                            {{ $noIdent !== '' ? $noIdent : $productoVinculado->codigo }}
                                        @else
                                            no existe producto
                                        @endif
                                    </span>
                                    </div>
                                    @if(!$productoVinculado && $proveedor)
                                        <button type="button" class="btn btn-sm btn-outline" style="font-weight:700;" title="Crear producto desde esta partida del CFDI" onclick="solicitarCrearProductoLineaCfdi({{ $i }})">➕ Agregar</button>
                                    @elseif(!$productoVinculado && !$proveedor)
                                        <span class="text-muted small">Cree el proveedor para usar ➕ Agregar</span>
                                    @endif
                                    </div>
                                </td>
                                <td>{{ $c['descripcion'] ?? '—' }}</td>
                                <td class="td-center text-mono">{{ number_format($c['cantidad'] ?? 0, 2) }}</td>
                                <td class="td-right text-mono">${{ number_format($c['valor_unitario'] ?? 0, 2) }}</td>
                                <td class="td-right text-mono fw-600">${{ number_format($importeLinea + $ivaLinea, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-body" style="display:flex;justify-content:flex-end;">
                <div class="totales-panel" style="min-width:260px;">
                    <div class="totales-row"><span>Subtotal</span><span class="monto text-mono">${{ number_format($datos['subtotal'] ?? 0, 2) }}</span></div>
                    @if(($datos['descuento'] ?? 0) > 0)<div class="totales-row descuento"><span>Descuento</span><span class="monto">−${{ number_format($datos['descuento'] ?? 0, 2) }}</span></div>@endif
                    <div class="totales-row grand"><span>TOTAL</span><span class="monto">${{ number_format($datos['total'] ?? 0, 2) }} MXN</span></div>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">Resumen</div></div>
            <div class="card-body">
                @php
                    $cfdiSerie = trim((string) ($datos['serie'] ?? ''));
                    $cfdiFolio = trim((string) ($datos['folio'] ?? ''));
                    $cfdiFiscal = ($cfdiSerie !== '' && $cfdiFolio !== '') ? ($cfdiSerie . '/' . $cfdiFolio) : ($cfdiSerie !== '' ? $cfdiSerie : ($cfdiFolio !== '' ? $cfdiFolio : ''));
                @endphp
                <p class="text-muted small">
                    Folio: <span class="text-mono fw-600">{{ $folioInterno }}@if($cfdiFiscal !== '') — {{ $cfdiFiscal }}@endif</span>
                </p>
                @if(!empty($datos['uuid']))<p class="text-muted small text-mono">UUID: {{ $datos['uuid'] }}</p>@endif
                <p class="small mt-2">Al guardar, la compra quedará en estado <strong>Registrada</strong>. En la ficha de la compra use <strong>Recibir mercancía</strong> para dar de alta la entrada en inventario (solo en líneas con producto vinculado).</p>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body" style="display:flex;gap:12px;justify-content:flex-end;">
        <a href="{{ route('compras.upload-cfdi') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">✓ Guardar compra</button>
    </div>
</div>

</form>

<form id="formCrearProductoLineaCfdi" method="POST" action="{{ route('compras.crear-desde-cfdi.crear-producto-linea') }}" style="display:none;">
    @csrf
    <input type="hidden" name="proveedor_id" id="crear_linea_proveedor_id" value="">
    <input type="hidden" name="concepto_index" id="crear_linea_concepto_index" value="">
    <input type="hidden" name="forzar_sin_validacion_similitud" id="crear_linea_forzar" value="0">
</form>

{{-- Modal seleccionar producto --}}
<div id="modalProducto" class="modal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <div class="modal-title">Seleccionar producto</div>
            <button type="button" class="modal-close" onclick="cerrarModalProducto()">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <input type="text" id="modalBuscarProducto" placeholder="Buscar por código o nombre..." class="form-control" autocomplete="off">
            </div>
            <div id="modalProductoLista" class="table-container" style="max-height:280px;overflow-y:auto;">
                <p class="text-muted text-center py-3">Escriba al menos 2 caracteres para buscar.</p>
            </div>
        </div>
    </div>
</div>

{{-- Modal agregar proveedor desde CFDI --}}
<div id="modalAgregarProveedorCfdi" class="modal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <div class="modal-title" style="color: var(--color-primary);">⚠️ no existe proveedor</div>
            <button type="button" class="modal-close" onclick="cerrarModalAgregarProveedorCfdi()">✕</button>
        </div>
        <form method="POST" action="{{ route('compras.crear-desde-cfdi.agregar-proveedor') }}" id="formAgregarProveedorCfdi">
            @csrf
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom:16px;">
                    no existe proveedor deseas agregarlo ?
                </p>

                <div class="form-group">
                    <label class="form-label">Nombre / Razón Social <span class="req">*</span></label>
                    <input type="text" name="nombre" class="form-control" required value="{{ $datos['nombre_emisor'] ?? '' }}">
                </div>

                <div class="form-group">
                    <label class="form-label">RFC <span class="req">*</span></label>
                    <input type="text" name="rfc" class="form-control text-mono" required value="{{ $datos['rfc_emisor'] ?? '' }}" maxlength="13" style="text-transform:uppercase;">
                </div>

                <div class="form-group">
                    <label class="form-label">Días de crédito</label>
                    <input type="number" name="dias_credito" class="form-control" min="0" value="0">
                    <span class="form-hint">Si indica días y el pago es <strong>PPD</strong>, se generará cuenta por pagar.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="cerrarModalAgregarProveedorCfdi()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Sí, agregar proveedor</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal crear productos desde CFDI --}}
<div id="modalCrearProductosCfdi" class="modal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <div class="modal-title">⚠️ Crear productos</div>
            <button type="button" class="modal-close" onclick="cerrarModalCrearProductosCfdi()">✕</button>
        </div>
        <form method="POST" action="{{ route('compras.crear-desde-cfdi.crear-productos') }}" id="formCrearProductosCfdi">
            @csrf
            <input type="hidden" name="proveedor_id" id="crear_productos_proveedor_id" value="{{ $proveedor?->id ?? '' }}">
            <input type="hidden" name="forzar_sin_validacion_similitud" id="crear_productos_forzar" value="0">
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom:16px;">
                    No se pudo relacionar producto ya que no existe código de proveedor relacionado al producto.<br>
                    Verifica si el producto existe y está relacionado con la lupita. Si no existe deseas crear el producto?
                </p>
                <p class="text-muted small" style="margin-bottom:0;">
                    Si el sistema advierte de similitud con el catálogo y usted confirma que es <strong>otra referencia</strong> (p. ej. otra talla), use <strong>Crear productos de todas formas</strong> para omitir esa comprobación y crear igualmente con los datos del CFDI.
                </p>
            </div>
            <div class="modal-footer" style="flex-wrap:wrap; gap:8px; justify-content:flex-end;">
                <button type="button" class="btn btn-light" onclick="cerrarModalCrearProductosCfdi()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="confirmarCrearProductosCfdiNormal()">Sí, crear productos</button>
                <button type="button" class="btn btn-warning" onclick="confirmarCrearProductosCfdiForzado()">Sí, crear productos de todas formas</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal crear producto desde una sola partida del CFDI (➕ Agregar) --}}
<div id="modalCrearProductoLineaCfdi" class="modal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <div class="modal-title">⚠️ Crear productos</div>
            <button type="button" class="modal-close" onclick="cerrarModalCrearProductoLineaCfdi()">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom:16px;">
                No se pudo relacionar producto ya que no existe código de proveedor relacionado al producto.<br>
                Verifica si el producto existe y está relacionado con la lupita. Si no existe deseas crear el producto?
            </p>
            <p class="text-muted small" style="margin-bottom:0;">
                Si aparece el aviso de similitud con el catálogo y es <strong>otra pieza o variante</strong>, use <strong>Crear producto de todas formas</strong>: se creará con la descripción del CFDI, folio <span class="text-mono">PSI-…</span> consecutivo y relación con el proveedor cuando haya <span class="text-mono">NoIdentificación</span>.
            </p>
        </div>
        <div class="modal-footer" style="flex-wrap:wrap; gap:8px; justify-content:flex-end;">
            <button type="button" class="btn btn-light" onclick="cerrarModalCrearProductoLineaCfdi()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="confirmarCrearProductoLineaCfdi()">Sí, crear producto</button>
            <button type="button" class="btn btn-warning" onclick="confirmarCrearProductoLineaCfdiForzado()">Crear producto de todas formas</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const listarUrl = '{{ route("compras.buscar-productos") }}';
    const urlVerificarSimilitud = '{{ route("compras.crear-desde-cfdi.verificar-similitud-descripcion") }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    window.CFDI_DESCRIPCION_POR_INDICE = @json($descripcionPorIndiceLineaCfdi ?? []);
    window.CFDI_DESCRIPCIONES_CON_NOIDENT = @json($descripcionesConNoIdentCfdi ?? []);
    let filaActual = null;
    let timerModal = null;
    window.CFDI_IDX_LINEA_A_CREAR = null;

    window.abrirModalProducto = function(rowIndex) {
        filaActual = rowIndex;
        document.getElementById('modalProducto').classList.add('show');
        document.getElementById('modalBuscarProducto').value = '';
        document.getElementById('modalBuscarProducto').focus();
        document.getElementById('modalProductoLista').innerHTML = '<p class="text-muted text-center py-3">Escriba al menos 2 caracteres para buscar.</p>';
    };

    window.cerrarModalProducto = function() {
        document.getElementById('modalProducto').classList.remove('show');
        filaActual = null;
    };

    window.solicitarCrearProductoLineaCfdi = function(idx) {
        var pid = document.getElementById('proveedor_id').value;
        if (!pid || String(pid).trim() === '') {
            alert('Indique o cree el proveedor primero.');
            return;
        }
        window.CFDI_IDX_LINEA_A_CREAR = idx;
        var forzarInp = document.getElementById('crear_linea_forzar');
        if (forzarInp) forzarInp.value = '0';
        document.getElementById('modalCrearProductoLineaCfdi').classList.add('show');
    };

    window.cerrarModalAgregarProveedorCfdi = function() {
        document.getElementById('modalAgregarProveedorCfdi').classList.remove('show');
    };

    window.cerrarModalCrearProductosCfdi = function() {
        document.getElementById('modalCrearProductosCfdi').classList.remove('show');
    };

    window.cerrarModalCrearProductoLineaCfdi = function() {
        document.getElementById('modalCrearProductoLineaCfdi').classList.remove('show');
        window.CFDI_IDX_LINEA_A_CREAR = null;
    };

    window.confirmarCrearProductoLineaCfdi = function() {
        var idx = window.CFDI_IDX_LINEA_A_CREAR;
        if (idx === null || idx === undefined) return;
        var pid = document.getElementById('proveedor_id').value;
        if (!pid || String(pid).trim() === '') {
            alert('Indique o cree el proveedor primero.');
            return;
        }

        var map = window.CFDI_DESCRIPCION_POR_INDICE || {};
        var desc = (map[idx] !== undefined && map[idx] !== null) ? map[idx] : map[String(idx)];
        if (desc === undefined || desc === null) {
            desc = '';
        }

        fetch(urlVerificarSimilitud, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ descripcion: String(desc) })
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.similar && data.message) {
                    alert(data.message);
                    return;
                }
                document.getElementById('crear_linea_forzar').value = '0';
                document.getElementById('crear_linea_proveedor_id').value = pid;
                document.getElementById('crear_linea_concepto_index').value = idx;
                document.getElementById('modalCrearProductoLineaCfdi').classList.remove('show');
                document.getElementById('formCrearProductoLineaCfdi').submit();
            })
            .catch(function() {
                alert('No se pudo verificar similitud con el catálogo. Intente de nuevo.');
            });
    };

    window.confirmarCrearProductoLineaCfdiForzado = function() {
        var idx = window.CFDI_IDX_LINEA_A_CREAR;
        if (idx === null || idx === undefined) return;
        var pid = document.getElementById('proveedor_id').value;
        if (!pid || String(pid).trim() === '') {
            alert('Indique o cree el proveedor primero.');
            return;
        }
        if (!confirm('Se creará el producto con los datos leídos del CFDI (código PSI consecutivo y relación con el proveedor si aplica). Se omite la comprobación de similitud con el catálogo. ¿Desea continuar?')) {
            return;
        }
        document.getElementById('crear_linea_forzar').value = '1';
        document.getElementById('crear_linea_proveedor_id').value = pid;
        document.getElementById('crear_linea_concepto_index').value = idx;
        document.getElementById('modalCrearProductoLineaCfdi').classList.remove('show');
        document.getElementById('formCrearProductoLineaCfdi').submit();
    };

    window.confirmarCrearProductosCfdiNormal = function() {
        var proveedorId = document.getElementById('proveedor_id').value;
        if (!proveedorId || String(proveedorId).trim() === '') {
            alert('Indique o cree el proveedor primero.');
            return;
        }
        document.getElementById('crear_productos_proveedor_id').value = proveedorId;
        document.getElementById('crear_productos_forzar').value = '0';
        var list = window.CFDI_DESCRIPCIONES_CON_NOIDENT || [];
        fetch(urlVerificarSimilitud, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ descripciones: list })
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.similar && data.message) {
                    alert(data.message);
                    return;
                }
                document.getElementById('modalCrearProductosCfdi').classList.remove('show');
                document.getElementById('formCrearProductosCfdi').submit();
            })
            .catch(function() {
                alert('No se pudo verificar similitud con el catálogo. Intente de nuevo.');
            });
    };

    window.confirmarCrearProductosCfdiForzado = function() {
        var proveedorId = document.getElementById('proveedor_id').value;
        if (!proveedorId || String(proveedorId).trim() === '') {
            alert('Indique o cree el proveedor primero.');
            return;
        }
        if (!confirm('Se crearán los productos faltantes con los datos del CFDI (códigos PSI consecutivos y relación proveedor por NoIdentificación). Se omite la comprobación de similitud con el catálogo. ¿Desea continuar?')) {
            return;
        }
        document.getElementById('crear_productos_proveedor_id').value = proveedorId;
        document.getElementById('crear_productos_forzar').value = '1';
        document.getElementById('modalCrearProductosCfdi').classList.remove('show');
        document.getElementById('formCrearProductosCfdi').submit();
    };

    document.getElementById('modalBuscarProducto').addEventListener('input', function() {
        clearTimeout(timerModal);
        const q = this.value.trim();
        if (q.length < 2) {
            document.getElementById('modalProductoLista').innerHTML = '<p class="text-muted text-center py-3">Escriba al menos 2 caracteres para buscar.</p>';
            return;
        }
        timerModal = setTimeout(function() {
            fetch(listarUrl + '?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(list) {
                    const div = document.getElementById('modalProductoLista');
                    if (!list.length) {
                        div.innerHTML = '<p class="text-muted text-center py-3">Sin resultados.</p>';
                        return;
                    }
                    div.innerHTML = '<table><thead><tr><th>Código</th><th>Nombre</th><th></th></tr></thead><tbody>' +
                        list.map(function(p) {
                            const codigo = (p.codigo || '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            const nombre = (p.nombre || '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            return '<tr><td class="text-mono">' + codigo + '</td><td>' + nombre + '</td><td><button type="button" class="btn btn-primary btn-sm" data-id="' + p.id + '" data-codigo="' + codigo + '">Seleccionar</button></td></tr>';
                        }).join('') + '</tbody></table>';
                    div.querySelectorAll('button[data-id]').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            const id = this.getAttribute('data-id');
                            const codigo = this.getAttribute('data-codigo');
                            if (filaActual !== null) {
                                document.getElementById('producto_id_' + filaActual).value = id;
                                    const noIdent = document.getElementById('no_identificacion_' + filaActual)?.value || '';
                                    document.getElementById('codigo_display_' + filaActual).textContent = noIdent || codigo || id;
                            }
                            cerrarModalProducto();
                        });
                    });
                })
                .catch(function() {
                    document.getElementById('modalProductoLista').innerHTML = '<p class="text-danger text-center py-3">Error al buscar.</p>';
                });
        }, 280);
    });

    // Interceptar el guardado para mostrar modales cuando falte proveedor o productos.
    document.getElementById('formCfdiCompra').addEventListener('submit', function(e) {
        var proveedorId = document.getElementById('proveedor_id').value;
        if (!proveedorId) {
            e.preventDefault();
            document.getElementById('modalAgregarProveedorCfdi').classList.add('show');
            return;
        }

        var formEl = document.getElementById('formCfdiCompra');
        var indices = [];
        try {
            indices = JSON.parse(formEl.getAttribute('data-cfdi-concepto-indices') || '[]');
        } catch (err) {
            indices = [];
        }
        if (!indices.length) {
            formEl.querySelectorAll('input[name*="[producto_id]"]').forEach(function(inp) {
                var m = (inp.name || '').match(/productos\[([^\]]+)\]\[producto_id\]/);
                if (m && indices.indexOf(m[1]) === -1) indices.push(m[1]);
            });
        }

        var faltaProducto = false;
        var faltaProductoConNoIdent = false;
        var faltaProductoSinNoIdent = false;

        indices.forEach(function(idx) {
            var inp = document.getElementById('producto_id_' + idx);
            if (!inp) return;
            var v = String(inp.value || '').trim();
            if (v === '') faltaProducto = true;
        });

        if (faltaProducto) {
            e.preventDefault();
            indices.forEach(function(idx) {
                var inp = document.getElementById('producto_id_' + idx);
                if (!inp || String(inp.value || '').trim() !== '') return;
                var noIdentInput = document.getElementById('no_identificacion_' + idx);
                var noIdent = noIdentInput ? String(noIdentInput.value || '').trim() : '';
                if (noIdent) faltaProductoConNoIdent = true;
                else faltaProductoSinNoIdent = true;
            });

            if (faltaProductoSinNoIdent && !faltaProductoConNoIdent) {
                alert('Hay líneas sin NoIdentificacion en el CFDI. Selecciona el producto manualmente con la lupa en el detalle.');
                return;
            }

            if (faltaProductoConNoIdent) {
                document.getElementById('crear_productos_proveedor_id').value = proveedorId;
                var fp = document.getElementById('crear_productos_forzar');
                if (fp) fp.value = '0';
                document.getElementById('modalCrearProductosCfdi').classList.add('show');
                return;
            }

            alert('Faltan productos por vincular. Usa la lupa en el detalle o completa la relación.');
        }
    });

    @if($proveedor)
    document.getElementById('proveedor_id').value = '{{ $proveedor->id }}';
    document.getElementById('buscarProveedor').value = {!! json_encode($proveedor->nombre) !!};
    @endif
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var buscarProveedor = document.getElementById('buscarProveedor');
    var proveedorResults = document.getElementById('proveedorResults');
    var proveedorId = document.getElementById('proveedor_id');
    var timerP = null;
    buscarProveedor.addEventListener('input', function() {
        clearTimeout(timerP);
        var q = this.value.trim();
        if (q.length < 2) { proveedorResults.classList.remove('show'); proveedorResults.innerHTML = ''; return; }
        timerP = setTimeout(function() {
            fetch('{{ route("compras.buscar-proveedores") }}?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    proveedorResults.innerHTML = data.length ? data.map(function(c) {
                        return '<div class="autocomplete-item" data-id="' + c.id + '" data-nombre="' + (c.nombre || '').replace(/"/g, '&quot;') + '"><div class="autocomplete-item-name">' + (c.nombre || '').replace(/</g, '&lt;') + '</div><div class="autocomplete-item-sub">' + (c.rfc || '') + '</div></div>';
                    }).join('') : '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
                    proveedorResults.classList.add('show');
                    proveedorResults.querySelectorAll('.autocomplete-item[data-id]').forEach(function(el) {
                        el.addEventListener('click', function() {
                            proveedorId.value = this.getAttribute('data-id');
                            buscarProveedor.value = this.getAttribute('data-nombre');
                            proveedorResults.classList.remove('show');
                        });
                    });
                });
        }, 280);
    });
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-box')) proveedorResults.classList.remove('show');
    });
});
</script>
@endpush

@endsection
