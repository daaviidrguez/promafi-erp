@extends('layouts.app')
{{-- resources/views/cotizaciones/create.blade.php --}}

@section('title', isset($cotizacion) ? 'Editar Cotización' : 'Nueva Cotización')
@section('page-title', isset($cotizacion) ? '✏️ Editar Cotización' : '📝 Nueva Cotización')
@section('page-subtitle', isset($cotizacion) 
    ? 'Modifica los datos del presupuesto'
    : 'Crear presupuesto para tu cliente')

@php
$isEdit = isset($cotizacion);

$breadcrumbs = [
    ['title' => 'Cotizaciones', 'url' => route('cotizaciones.index')],
    [
        'title' => $isEdit
            ? 'Editar Cotización'
            : 'Nueva Cotización'
    ]
];
@endphp

@section('content')

<form action="{{ route('cotizaciones.store') }}" method="POST" id="cotizacionForm" enctype="multipart/form-data">
@csrf
@if($isEdit)
    <input type="hidden" name="cotizacion_id" value="{{ $cotizacion->id }}">
@endif
<div id="imagenesHiddenContainer" style="display:none;" aria-hidden="true"></div>

<div style="display:flex; flex-direction:column; gap:20px;">

    {{-- Fila 1: Cliente | Condiciones | Información General --}}
    <div class="responsive-grid cotizacion-create-top-grid">
        <div class="card card-search">
            <div class="card-header">
                <div class="card-title">👤 Cliente</div>
            </div>
            <div class="card-body">
                <div class="form-group search-box">
                    <label class="form-label">Buscar Cliente <span class="req">*</span></label>
                    <input type="text"
                           id="buscarCliente"
                           value="{{ $isEdit ? $cotizacion->cliente->nombre : '' }}"
                           placeholder="Escribe nombre o RFC..."
                           autocomplete="off"
                           class="form-control">

                    <input type="hidden" name="cliente_id" id="cliente_id"
                           value="{{ $isEdit ? $cotizacion->cliente_id : '' }}" required>

                    <div id="clienteResults" class="autocomplete-results"></div>
                </div>

                <div id="clienteInfo" style="display:none; margin-top:14px;">
                    <div style="background: var(--color-gray-50);
                                border:1.5px solid var(--color-gray-200);
                                border-radius: var(--radius-md);
                                padding:12px 16px;
                                display:flex;
                                justify-content:space-between;
                                align-items:center;">
                        <div>
                            <div class="fw-bold" id="clienteNombre" style="font-size:14px;"></div>
                            <div class="text-muted mt-4" id="clienteRfc" style="font-size:13px;"></div>
                        </div>
                        <button type="button" onclick="limpiarCliente()" class="btn btn-light btn-sm">
                            Cambiar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">💳 Condiciones</div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Tipo de Venta <span class="req">*</span></label>
                    <select name="tipo_venta" id="tipoVenta"
                            onchange="onTipoVentaChange()" class="form-control">
                        <option value="contado" {{ ($isEdit && $cotizacion->tipo_venta === 'contado') ? 'selected' : '' }}>
                            Contado
                        </option>
                        <option value="credito" {{ ($isEdit && $cotizacion->tipo_venta === 'credito') ? 'selected' : '' }}>
                            Crédito
                        </option>
                    </select>
                </div>
                <div class="form-group" id="diasCreditoGroup" style="display:none;">
                    <label class="form-label">Días de Crédito</label>
                    <input type="number"
                        name="dias_credito"
                        id="diasCredito"
                        value="{{ $isEdit ? (int) $cotizacion->dias_credito_aplicados : 0 }}"
                        min="0"
                        class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Forma de pago</label>
                    <select name="forma_pago" id="formaPagoCotizacion" class="form-control">
                        @foreach($formasPago ?? [] as $fp)
                            <option value="{{ $fp->clave }}" {{ ($isEdit && ($cotizacion->forma_pago ?? '03') == $fp->clave) || (!$isEdit && old('forma_pago', '03') == $fp->clave) ? 'selected' : '' }}>{{ $fp->etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Información General</div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Folio</label>
                    <input type="text"
                           value="{{ $isEdit ? $cotizacion->folio : $folio }}"
                           readonly
                           class="form-control text-mono fw-bold"
                           style="background: var(--color-gray-100);">
                </div>
                <div class="form-row responsive-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Fecha de Emisión <span class="req">*</span></label>
                        <input type="date"
                               name="fecha"
                               value="{{ $isEdit ? $cotizacion->fecha->format('Y-m-d') : date('Y-m-d') }}"
                               required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Válida Hasta <span class="req">*</span></label>
                        <input type="date"
                               name="fecha_vencimiento"
                               value="{{ $isEdit ? $cotizacion->fecha_vencimiento->format('Y-m-d') : date('Y-m-d', strtotime('+15 days')) }}"
                               required class="form-control">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Cargar desde Lista de Precios (visible cuando hay cliente) --}}
        <div class="card card-search" id="cardListaPrecios" style="display:none;">
            <div class="card-header">
                <div class="card-title">💰 Cargar desde Lista de Precios</div>
            </div>
            <div class="card-body" id="cardListaPreciosBody" style="display:grid;grid-template-columns:1fr;gap:24px;align-items:start;">
                <div class="form-group">
                    <label class="form-label">Lista asignada al cliente</label>
                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <select id="selectListaPrecio" class="form-control" style="flex:1;min-width:200px;">
                            <option value="">— Selecciona una lista —</option>
                        </select>
                        <button type="button" id="btnCargarLista" class="btn btn-primary" disabled>
                            Cargar todos
                        </button>
                    </div>
                </div>
                <div class="form-group search-box" id="searchListaPrecioBox" style="display:none; position:relative; z-index:1600;">
                    <label class="form-label">Buscar producto en la lista</label>
                    <input type="text" id="buscarProductoLista" placeholder="Buscar por código o nombre..." autocomplete="off" class="form-control">
                    <div id="productoListaResults" class="autocomplete-results" style="min-width:100%; z-index:1600;"></div>
                    <span class="form-hint" style="margin-top:8px;display:block;">Selecciona una lista y busca productos para cargarlos con el precio de la lista.</span>
                </div>
            </div>
        </div>

    {{-- Fila 2: Productos y Servicios (ancho completo) --}}
    <div class="card card-search">
            <div class="card-header">
                <div class="card-title">📦 Productos y Servicios</div>
                <div class="cotizacion-excel-actions">
                    <button type="button" onclick="importarExcel()">Importar</button>
                    <button type="button" onclick="exportarExcel()">Exportar</button>
                    <button type="button" onclick="descargarPlantilla()">Plantilla</button>
                    <button type="button" onclick="agregarManual()" class="btn btn-primary btn-sm">
                        ➕ Agregar
                    </button>
                    <input type="file" id="importExcelInput" accept=".xlsx,.xls,.csv" style="display:none" onchange="procesarImportExcel(event)">
                </div>
            </div>

            <div class="card-body" style="padding:0;">

                <div class="search-box" style="padding:16px;">
                    <input type="text"
                           id="buscarProducto"
                           placeholder="Buscar por código o nombre..."
                           autocomplete="off"
                           class="form-control">
                    <div id="productoResults" class="autocomplete-results"></div>
                </div>

                <div class="table-container" style="border:none; box-shadow:none; border-radius:0; margin-bottom:0;">
                    <table class="table-productos-cotizacion" style="table-layout:fixed; width:100%;">
                        <colgroup>
                            <col style="width:92px;">
                            <col>
                            <col style="width:76px;">
                            <col style="width:64px;">
                            <col style="width:92px;">
                            <col style="width:60px;">
                            <col style="width:70px;">
                            <col style="width:92px;">
                            <col style="width:96px;">
                            <col style="width:96px;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="td-center">Origen</th>
                                <th>Descripción</th>
                                <th class="td-center">Cantidad</th>
                                <th class="td-center">Unidad</th>
                                <th class="td-right">Precio</th>
                                <th class="td-center">Desc%</th>
                                <th class="td-center">IVA</th>
                                <th class="td-right">Subtotal</th>
                                <th class="td-right">Total</th>
                                <th class="td-right"></th>
                            </tr>
                        </thead>
                        <tbody id="productosBody">
                            <tr id="emptyRow">
                                <td colspan="10">
                                    <div style="padding:40px 20px; text-align:center; color:var(--color-gray-500);">
                                        <div style="font-size:36px; margin-bottom:10px; opacity:0.3;">📦</div>
                                        <div class="fw-600">Sin productos agregados</div>
                                        <div style="font-size:13px; margin-top:4px;">
                                            Usa el buscador para añadir productos
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                {{-- Dropdown de sugerencias posicionado fuera de la tabla para que se vea encima (igual que cliente/producto) --}}
                <div id="sugerenciaResultsFlotante" class="autocomplete-results autocomplete-results-flotante" style="display:none; position:fixed; z-index:2000;"></div>

            </div>
        </div>

    {{-- Fila 3: Condiciones y Observaciones | Totales --}}
    <div class="responsive-grid cotizacion-create-bottom-grid">
        <div class="card">
            <div class="card-header">
                <div class="card-title">📄 Condiciones y Observaciones</div>
            </div>
            <div class="card-body">

                <div class="form-group">
                    <label class="form-label">Condiciones Comerciales</label>
                    <textarea name="condiciones_pago" class="form-control" rows="3">{{ 
                $isEdit 
                    ? $cotizacion->condiciones_pago 
                    : "Precios más IVA. Vigencia 15 días.\ntoda cancelación / devolución genera un 20% de penalización y se tiene un plazo máximo de 5 días a partir de la recepción del material para solicitarla y queda sujeta a previa autorización." 
                }}</textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="3">
    {{ $isEdit ? $cotizacion->observaciones : '' }}
                    </textarea>
                </div>

            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">🔗 Referencia</div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Referencia comercial</label>
                    <input type="text" name="referencia_comercial" class="form-control"
                           value="{{ old('referencia_comercial', $isEdit ? ($cotizacion->referencia_comercial ?? '') : '') }}"
                           placeholder="Ej. publicación, SKU externo…"
                           autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">URL</label>
                    <input type="text" name="referencia_url" class="form-control"
                           value="{{ old('referencia_url', $isEdit ? ($cotizacion->referencia_url ?? '') : '') }}"
                           placeholder="https://…"
                           autocomplete="off">
                </div>
                <div class="form-group">
                    <label class="form-label">URL adicional</label>
                    <input type="text" name="referencia_url_2" class="form-control"
                           value="{{ old('referencia_url_2', $isEdit ? ($cotizacion->referencia_url_2 ?? '') : '') }}"
                           placeholder="https://…"
                           autocomplete="off">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Otra URL</label>
                    <input type="text" name="referencia_url_3" class="form-control"
                           value="{{ old('referencia_url_3', $isEdit ? ($cotizacion->referencia_url_3 ?? '') : '') }}"
                           placeholder="https://…"
                           autocomplete="off">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">💰 Totales</div>
            </div>
            <div class="card-body">
                <div class="totales-panel">
                    <div class="totales-row">
                        <span>Subtotal</span>
                        <span class="monto text-mono" id="tSubtotal">$0.00</span>
                    </div>

                    <div class="totales-row descuento" id="rowDescuento" style="display:none;">
                        <span>Descuento</span>
                        <span class="monto text-mono" id="tDescuento">−$0.00</span>
                    </div>

                    <div class="totales-row">
                        <span>IVA</span>
                        <span class="monto text-mono" id="tIva">$0.00</span>
                    </div>

                    <div class="totales-row descuento" id="rowIsrRetenido" style="display:none;">
                        <span>ISR retenido</span>
                        <span class="monto text-mono" id="tIsrRetenido">−$0.00</span>
                    </div>

                    <div class="totales-row grand">
                        <span>TOTAL</span>
                        <span class="monto" id="tTotal">$0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Botones --}}
<div class="card">
    <div class="card-body" style="display:flex; gap:12px; justify-content:flex-end;">
        <a href="{{ route('cotizaciones.index') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            ✓ Guardar Cotización
        </button>
    </div>
</div>

</form>

{{-- Modal: cambios sin guardar --}}
<div id="modalCambiosSinGuardar" class="modal">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-header">
            <div class="modal-title">Cambios sin guardar</div>
            <button type="button" class="modal-close" onclick="continuarEditando()">✕</button>
        </div>
        <div class="modal-body">
            <p style="margin:0; line-height:1.6; color:var(--color-gray-700);">
                Tienes cambios que aún no se han guardado.
                ¿Deseas continuar {{ $isEdit ? 'editando' : 'creando' }} la cotización o salir sin guardar?
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" onclick="salirSinGuardar()">Salir sin guardar</button>
            <button type="button" class="btn btn-primary" onclick="continuarEditando()">
                Continuar {{ $isEdit ? 'editando' : 'creando' }}
            </button>
        </div>
    </div>
</div>

{{-- Modal: importar partidas con partidas existentes --}}
<div id="modalImportacionPartidas" class="modal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div class="modal-title">Importar partidas</div>
            <button type="button" class="modal-close" onclick="cerrarModalImportacion()">✕</button>
        </div>
        <div class="modal-body">
            <p style="margin:0; line-height:1.6; color:var(--color-gray-700);">
                Ya tienes partidas en la cotización. ¿Cómo deseas aplicar el archivo importado?
            </p>
            <ul style="margin:14px 0 0; padding-left:20px; color:var(--color-gray-700); line-height:1.6; font-size:14px;">
                <li style="margin-bottom:8px;"><strong>Reemplazar todo:</strong> elimina las partidas actuales y usa solo las del archivo.</li>
                <li><strong>Actualizar y agregar:</strong> actualiza cada fila existente y agrega las nuevas al final.</li>
            </ul>
        </div>
        <div class="modal-footer" style="flex-wrap:wrap;">
            <button type="button" class="btn btn-light" onclick="cerrarModalImportacion()">Cancelar importación</button>
            <button type="button" class="btn btn-light" onclick="importarActualizarFilas()">Actualizar y agregar</button>
            <button type="button" class="btn btn-primary" onclick="importarReemplazarTodo()">Reemplazar todo</button>
        </div>
    </div>
</div>

@endsection

@php
    $detallesIniciales = [];
    if (isset($cotizacion) && $cotizacion->detalles->count() > 0) {
        $detallesIniciales = $cotizacion->detalles->sortBy('orden')->values()->map(function ($d) {
            return [
                'id'        => $d->producto_id,
                'codigo'    => ($d->codigo === 'MANUAL' ? '-' : $d->codigo) ?? '-',
                'origen'    => $d->origen ?? '',
                'nombre'    => $d->descripcion ?? '',
                'cantidad'  => (float) $d->cantidad,
                'unidad'    => $d->unidad ?? $d->producto->unidad ?? 'PZA',
                'precio'    => (float) $d->precio_unitario,
                'descuento' => (float) ($d->descuento_porcentaje ?? 0),
                'tasa_iva'  => $d->tasa_iva !== null ? (float) $d->tasa_iva : null,
                'manual'    => (bool) $d->es_producto_manual,
                'sugerencia_id' => $d->sugerencia_id,
                'imagenes_existentes' => $d->rutasImagenes(),
                'imagenes_urls' => $d->imagenes_urls,
            ];
        })->all();
    }
@endphp

@push('styles')
<style>
/* Tabla productos: scroll horizontal en móvil */
.table-container .table-productos-cotizacion { min-width: 640px; }
/* Descripción ancha, Origen = ancho Precio; columnas numéricas compactas */
.table-productos-cotizacion thead th:nth-child(2) { padding: 11px 16px; }
.table-productos-cotizacion thead th:not(:nth-child(2)) { padding: 11px 4px; white-space: nowrap; }
.table-productos-cotizacion tbody td:nth-child(2) { padding: 12px 16px; }
.table-productos-cotizacion tbody td:not(:nth-child(2)) { padding: 8px 4px; }
.table-productos-cotizacion tbody td:nth-child(9) { padding-right: 6px; }
.table-productos-cotizacion tbody td:last-child { padding: 8px 4px 8px 2px; }
.partida-acciones { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.partida-acciones-btns { display: flex; gap: 3px; flex-wrap: wrap; justify-content: flex-end; }
.partida-orden {
    display: flex;
    align-items: center;
    gap: 2px;
    margin-bottom: 2px;
}
.partida-orden-num {
    font-size: 11px;
    font-weight: 700;
    color: var(--color-gray-500);
    min-width: 18px;
    text-align: center;
    font-variant-numeric: tabular-nums;
}
.partida-orden .btn-icon {
    width: 24px;
    height: 24px;
    padding: 0;
    font-size: 11px;
    line-height: 1;
}
.partida-imagenes-preview { display: flex; flex-wrap: wrap; gap: 3px; justify-content: flex-end; max-width: 68px; }
.partida-imagen-thumb {
    position: relative;
    width: 30px;
    height: 30px;
    border-radius: 4px;
    overflow: hidden;
    border: 1px solid var(--color-gray-200);
    background: var(--color-gray-50);
}
.partida-imagen-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.partida-imagen-thumb button {
    position: absolute;
    top: 0;
    right: 0;
    width: 14px;
    height: 14px;
    padding: 0;
    border: none;
    background: rgba(220, 38, 38, 0.9);
    color: #fff;
    font-size: 9px;
    line-height: 1;
    cursor: pointer;
    border-radius: 0 0 0 3px;
}
/* Inputs numéricos: padding horizontal reducido */
.table-productos-cotizacion .form-control-numeric { padding: 9px 6px; font-size: 13px; }
.table-productos-cotizacion .form-control-numeric:focus { padding: 9px 6px; }

.cotizacion-excel-actions {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
.cotizacion-excel-actions button:not(.btn) {
    background: none;
    border: none;
    padding: 0;
    color: var(--color-primary);
    font-size: 13px;
    font-weight: 600;
    text-decoration: underline;
    cursor: pointer;
}
.cotizacion-excel-actions button:not(.btn):hover {
    color: var(--color-secondary);
}

@media (max-width: 768px) {
    /* Solo móvil: descripción más ancha y scroll horizontal estable */
    .table-container .table-productos-cotizacion {
        min-width: calc(100vw + 220px);
    }
    .table-productos-cotizacion colgroup col:first-child {
        width: 48vw !important;
        min-width: 260px;
    }
    .table-productos-cotizacion tbody td:first-child,
    .table-productos-cotizacion thead th:nth-child(2) {
        min-width: 260px;
    }
    .table-productos-cotizacion .manual-desc-mobile {
        min-height: 88px;
        resize: vertical;
    }
}

</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
let productos = [];
let timerCliente, timerProducto, timerSugerencia = {};
let lastSugerenciaRowIndex = 0;
let clienteTipoPersona = @json($isEdit ? ($cotizacion->cliente->tipo_persona ?? 'fisica') : 'fisica');
const empresaIsrConfig = {
    tipo_persona: @json($empresa->tipo_persona ?? 'moral'),
    regimen_fiscal: @json($empresa->regimen_fiscal ?? ''),
    regimen_resico: @json(config('isr_resico.regimen_clave', '626')),
    tasa_retencion: @json((float) config('isr_resico.tasa_retencion_pm_a_resico', 0.0125)),
};
const cotizacionDetallesIniciales = @json($detallesIniciales);
const isEditMode = @json($isEdit);
const MAX_IMAGENES_PARTIDA = 3;

let initialSnapshot = '';
let allowNavigation = false;
let pendingNavUrl = null;
let pendingImportRows = null;

const EXCEL_COLS = ['Origen', 'Descripción', 'Cantidad', 'Unidad', 'Precio', 'Desc%', 'IVA'];

function getFormSnapshot() {
    const form = document.getElementById('cotizacionForm');
    const data = {
        cliente_id: document.getElementById('cliente_id').value,
        tipo_venta: document.getElementById('tipoVenta').value,
        dias_credito: document.getElementById('diasCredito').value,
        forma_pago: form.querySelector('[name="forma_pago"]')?.value || '',
        fecha: form.querySelector('[name="fecha"]')?.value || '',
        fecha_vencimiento: form.querySelector('[name="fecha_vencimiento"]')?.value || '',
        condiciones_pago: form.querySelector('[name="condiciones_pago"]')?.value || '',
        observaciones: (form.querySelector('[name="observaciones"]')?.value || '').trim(),
        referencia_comercial: form.querySelector('[name="referencia_comercial"]')?.value || '',
        referencia_url: form.querySelector('[name="referencia_url"]')?.value || '',
        referencia_url_2: form.querySelector('[name="referencia_url_2"]')?.value || '',
        referencia_url_3: form.querySelector('[name="referencia_url_3"]')?.value || '',
        productos: productos.map(p => ({
            id: p.id,
            codigo: p.codigo,
            origen: p.origen || '',
            nombre: p.nombre,
            cantidad: p.cantidad,
            unidad: p.unidad,
            precio: p.precio,
            descuento: p.descuento,
            tasa_iva: p.tasa_iva,
            manual: p.manual,
            sugerencia_id: p.sugerencia_id || null,
        })),
    };
    return JSON.stringify(data);
}

function isFormDirty() {
    return getFormSnapshot() !== initialSnapshot;
}

function captureInitialSnapshot() {
    initialSnapshot = getFormSnapshot();
}

function showUnsavedModal() {
    document.getElementById('modalCambiosSinGuardar').classList.add('show');
}

function continuarEditando() {
    pendingNavUrl = null;
    document.getElementById('modalCambiosSinGuardar').classList.remove('show');
}

function salirSinGuardar() {
    allowNavigation = true;
    document.getElementById('modalCambiosSinGuardar').classList.remove('show');
    if (pendingNavUrl) {
        window.location.href = pendingNavUrl;
    }
}

function initUnsavedGuard() {
    window.addEventListener('beforeunload', (e) => {
        if (!allowNavigation && isFormDirty()) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    document.addEventListener('click', (e) => {
        if (allowNavigation || !isFormDirty()) return;
        if (e.target.closest('#modalCambiosSinGuardar')) return;
        if (e.target.closest('#modalImportacionPartidas')) return;
        if (e.target.closest('#importExcelInput')) return;

        const link = e.target.closest('a[href]');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
        if (link.target === '_blank' || link.hasAttribute('download')) return;

        let targetUrl;
        try {
            targetUrl = new URL(link.href, window.location.origin);
        } catch (_) {
            return;
        }
        if (targetUrl.origin !== window.location.origin) return;
        if (targetUrl.pathname === window.location.pathname && targetUrl.search === window.location.search) return;

        e.preventDefault();
        e.stopPropagation();
        pendingNavUrl = link.href;
        showUnsavedModal();
    }, true);
}

function normalizeExcelKey(key) {
    return String(key || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function getExcelCell(row, aliases) {
    for (const [key, value] of Object.entries(row)) {
        const normalized = normalizeExcelKey(key);
        if (aliases.includes(normalized)) {
            return value;
        }
    }
    return '';
}

function tasaIvaToLabel(tasa) {
    if (tasa === null || tasa === undefined) return 'Exento';
    if (Number(tasa) === 0) return '0%';
    return '16%';
}

function parseTasaIva(val) {
    const v = String(val ?? '').trim().toLowerCase();
    if (!v || v === 'exento' || v === 'exe') return null;
    if (v === '0%' || v === '0' || v === '0.0') return 0;
    if (v === '16%' || v === '16' || v === '0.16') return 0.16;
    const num = parseFloat(v.replace('%', ''));
    if (!Number.isNaN(num)) {
        return num > 1 ? num / 100 : num;
    }
    return 0.16;
}

function descargarPlantilla() {
    if (typeof XLSX === 'undefined') {
        alert('No se pudo cargar la librería de Excel. Recarga la página e intenta de nuevo.');
        return;
    }
    const ws = XLSX.utils.aoa_to_sheet([
        EXCEL_COLS,
        ['', 'Ejemplo de producto o servicio', 1, 'PZA', 100, 0, '16%'],
    ]);
    ws['!cols'] = [{ wch: 12 }, { wch: 42 }, { wch: 10 }, { wch: 8 }, { wch: 12 }, { wch: 8 }, { wch: 10 }];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Partidas');
    XLSX.writeFile(wb, 'plantilla_cotizacion.xlsx');
}

function exportarExcel() {
    if (typeof XLSX === 'undefined') {
        alert('No se pudo cargar la librería de Excel. Recarga la página e intenta de nuevo.');
        return;
    }
    if (!productos.length) {
        alert('No hay partidas para exportar.');
        return;
    }
    const rows = productos.map(p => [
        p.origen || '',
        p.nombre || '',
        p.cantidad,
        p.unidad || 'PZA',
        p.precio,
        p.descuento || 0,
        tasaIvaToLabel(p.tasa_iva),
    ]);
    const ws = XLSX.utils.aoa_to_sheet([EXCEL_COLS, ...rows]);
    ws['!cols'] = [{ wch: 12 }, { wch: 42 }, { wch: 10 }, { wch: 8 }, { wch: 12 }, { wch: 8 }, { wch: 10 }];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Partidas');
    XLSX.writeFile(wb, 'cotizacion_partidas.xlsx');
}

function importarExcel() {
    document.getElementById('importExcelInput').click();
}

function parseImportRows(rows) {
    const aliases = {
        origen: ['origen', 'source', 'fuente'],
        descripcion: ['descripcion', 'description', 'producto', 'nombre'],
        cantidad: ['cantidad', 'qty', 'quantity'],
        unidad: ['unidad', 'unit', 'uom'],
        precio: ['precio', 'precio unitario', 'precio_unitario', 'price'],
        descuento: ['desc%', 'desc', 'descuento', 'descuento_porcentaje'],
        iva: ['iva', 'tasa_iva', 'tasa iva'],
    };

    return rows.map((row) => {
        const descripcion = String(getExcelCell(row, aliases.descripcion) || '').trim();
        if (!descripcion) return null;

        const origen = String(getExcelCell(row, aliases.origen) || '').trim().slice(0, 100);
        const cantidad = Math.max(0.01, parseFloat(getExcelCell(row, aliases.cantidad)) || 1);
        const unidad = String(getExcelCell(row, aliases.unidad) || 'PZA').trim().slice(0, 10) || 'PZA';
        const precio = Math.max(0, parseFloat(getExcelCell(row, aliases.precio)) || 0);
        const descuento = Math.min(100, Math.max(0, parseFloat(getExcelCell(row, aliases.descuento)) || 0));
        const tasa_iva = parseTasaIva(getExcelCell(row, aliases.iva));

        return {
            id: null,
            codigo: '-',
            origen,
            nombre: descripcion,
            cantidad,
            unidad,
            precio,
            descuento,
            tasa_iva,
            manual: true,
            sugerencia_id: null,
            imagenesExistentes: [], imagenesUrls: [], imagenesNuevas: [], previewNuevas: [],
        };
    }).filter(Boolean);
}

function aplicarImportacion(imported) {
    if (!productos.length) {
        productos = imported;
        renderProductos();
        return;
    }

    pendingImportRows = imported;
    document.getElementById('modalImportacionPartidas').classList.add('show');
}

function cerrarModalImportacion() {
    pendingImportRows = null;
    document.getElementById('modalImportacionPartidas').classList.remove('show');
}

function importarReemplazarTodo() {
    if (!pendingImportRows) return;
    productos = pendingImportRows;
    cerrarModalImportacion();
    renderProductos();
}

function importarActualizarFilas() {
    if (!pendingImportRows) return;
    const imported = pendingImportRows;
    cerrarModalImportacion();
    imported.forEach((item, idx) => {
        if (idx < productos.length) {
            const existing = productos[idx];
            productos[idx] = {
                ...existing,
                origen: item.origen || '',
                nombre: item.nombre,
                cantidad: item.cantidad,
                unidad: item.unidad,
                precio: item.precio,
                descuento: item.descuento,
                tasa_iva: item.tasa_iva,
            };
        } else {
            productos.push(item);
        }
    });
    renderProductos();
}

function procesarImportExcel(event) {
    const file = event.target.files[0];
    if (!file) return;

    if (typeof XLSX === 'undefined') {
        alert('No se pudo cargar la librería de Excel. Recarga la página e intenta de nuevo.');
        event.target.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        try {
            const wb = XLSX.read(e.target.result, { type: 'array' });
            const sheet = wb.Sheets[wb.SheetNames[0]];
            const rows = XLSX.utils.sheet_to_json(sheet, { defval: '' });
            const imported = parseImportRows(rows);

            if (!imported.length) {
                alert('El archivo no contiene partidas válidas. Verifica las columnas de la plantilla.');
                return;
            }

            aplicarImportacion(imported);
        } catch (err) {
            console.error(err);
            alert('No se pudo leer el archivo. Verifica que sea un Excel válido.');
        } finally {
            event.target.value = '';
        }
    };
    reader.readAsArrayBuffer(file);
}

document.addEventListener('DOMContentLoaded', () => {

    // Cargar productos existentes al editar (misma coherencia que al crear)
    if (Array.isArray(cotizacionDetallesIniciales) && cotizacionDetallesIniciales.length > 0) {
        productos = cotizacionDetallesIniciales.map(function (d) {
            return {
                id: d.id,
                codigo: d.codigo,
                origen: d.origen || '',
                nombre: d.nombre,
                cantidad: d.cantidad,
                unidad: d.unidad || 'PZA',
                precio: d.precio,
                descuento: d.descuento,
                tasa_iva: d.tasa_iva,
                manual: d.manual,
                sugerencia_id: d.sugerencia_id || null,
                imagenesExistentes: Array.isArray(d.imagenes_existentes) ? d.imagenes_existentes.slice() : [],
                imagenesUrls: Array.isArray(d.imagenes_urls) ? d.imagenes_urls.slice() : [],
                imagenesNuevas: [],
                previewNuevas: [],
            };
        });
        renderProductos();
    }

    initUnsavedGuard();

    // Sincroniza visibilidad, disabled y validación HTML5 de días (evita "not focusable" si está oculto con min incompatible)
    onTipoVentaChange();

    // Buscador cliente
    document.getElementById('buscarCliente').addEventListener('input', function() {
        clearTimeout(timerCliente);
        const q = this.value.trim();
        if (q.length < 2) { closeDropdown('clienteResults'); return; }
        timerCliente = setTimeout(() => buscarClientes(q), 280);
    });

    // Buscador producto
    document.getElementById('buscarProducto').addEventListener('input', function() {
        clearTimeout(timerProducto);
        const q = this.value.trim();
        if (q.length < 2) { closeDropdown('productoResults'); return; }
        timerProducto = setTimeout(() => buscarProductos(q), 280);
    });

    // Cerrar dropdowns al hacer click fuera (no cerrar sugerencias si el clic es dentro del flotante)
    document.addEventListener('click', e => {
        if (!e.target.closest('.search-box') && !e.target.closest('#sugerenciaResultsFlotante')) {
            closeDropdown('clienteResults');
            closeDropdown('productoResults');
            closeDropdown('productoListaResults');
            closeSugerenciaFlotante();
        }
    });

    @if($isEdit)
    document.getElementById('clienteInfo').style.display = 'block';
    document.getElementById('clienteNombre').textContent = '{{ $cotizacion->cliente->nombre }}';
    document.getElementById('clienteRfc').textContent = 'RFC: {{ $cotizacion->cliente->rfc }}';
    document.getElementById('cardListaPrecios').style.display = 'block';
    fetchListasPreciosCliente({{ $cotizacion->cliente_id }});
    @endif

    captureInitialSnapshot();
});

function closeDropdown(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('show');
}

async function buscarClientes(q) {
    try {
        const r = await fetch(`{{ route('clientes.buscar') }}?q=${encodeURIComponent(q)}`);
        const data = await r.json();
        const box = document.getElementById('clienteResults');
        if (!data.length) {
            box.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
        } else {
            box.innerHTML = data.map(c => `
                <div class="autocomplete-item" onclick='seleccionarCliente(${JSON.stringify(c)})'>
                    <div class="autocomplete-item-name">${c.nombre}</div>
                    <div class="autocomplete-item-sub">RFC: ${c.rfc}</div>
                </div>
            `).join('');
        }
        box.classList.add('show');
    } catch(e) { console.error(e); }
}

function aplicaRetencionIsrPm(tipoPersona) {
    const esResico = empresaIsrConfig.tipo_persona === 'fisica'
        && empresaIsrConfig.regimen_fiscal === empresaIsrConfig.regimen_resico;
    return esResico && tipoPersona === 'moral';
}

function calcularRetencionIsrPm(subtotal, descuento) {
    const base = Math.max(0, subtotal - descuento);
    return Math.round(base * empresaIsrConfig.tasa_retencion * 100) / 100;
}

function seleccionarCliente(c) {
    document.getElementById('cliente_id').value = c.id;
    document.getElementById('buscarCliente').value = c.nombre;
    document.getElementById('clienteNombre').textContent = c.nombre;
    document.getElementById('clienteRfc').textContent = `RFC: ${c.rfc}`;
    clienteTipoPersona = c.tipo_persona || 'fisica';
    document.getElementById('clienteInfo').style.display = 'block';
    document.getElementById('cardListaPrecios').style.display = 'block';
    closeDropdown('clienteResults');
    fetchListasPreciosCliente(c.id);
    // Auto-configurar crédito y forma de pago
    if (c.dias_credito > 0) {
        document.getElementById('tipoVenta').value = 'credito';
        document.getElementById('diasCredito').value = c.dias_credito;
    } else {
        document.getElementById('tipoVenta').value = 'contado';
        document.getElementById('diasCredito').value = '0';
    }
    onTipoVentaChange();
    const fpSelect = document.getElementById('formaPagoCotizacion');
    if (fpSelect && c.forma_pago) {
        fpSelect.value = c.forma_pago;
    }
    calcTotales();
}

function limpiarCliente() {
    document.getElementById('cliente_id').value = '';
    document.getElementById('buscarCliente').value = '';
    clienteTipoPersona = 'fisica';
    document.getElementById('clienteInfo').style.display = 'none';
    document.getElementById('cardListaPrecios').style.display = 'none';
    document.getElementById('selectListaPrecio').innerHTML = '<option value="">— Selecciona una lista —</option>';
    document.getElementById('btnCargarLista').disabled = true;
    document.getElementById('searchListaPrecioBox').style.display = 'none';
    document.getElementById('buscarProductoLista').value = '';
    document.getElementById('productoListaResults').classList.remove('show');
    productosDeLista = [];
    document.getElementById('buscarCliente').focus();
    calcTotales();
}

function onTipoVentaChange() {
    const tipo = document.getElementById('tipoVenta').value;
    const grupo = document.getElementById('diasCreditoGroup');
    const input = document.getElementById('diasCredito');
    const esCredito = tipo === 'credito';
    grupo.style.display = esCredito ? 'block' : 'none';
    // Los campos disabled no participan en la validación del formulario (evita error al guardar en contado con grupo oculto)
    input.disabled = !esCredito;
    if (!esCredito) {
        input.value = '0';
    }
}

async function fetchListasPreciosCliente(clienteId) {
    try {
        const r = await fetch(`{{ route('cotizaciones.listas-precios-cliente') }}?cliente_id=${clienteId}`);
        const data = await r.json();
        const sel = document.getElementById('selectListaPrecio');
        sel.innerHTML = '<option value="">— Selecciona una lista —</option>' +
            (data.length ? data.map(l => `<option value="${l.id}">${l.nombre}</option>`).join('') : '');
        document.getElementById('btnCargarLista').disabled = !data.length;
    } catch (e) { console.error(e); }
}

let productosDeLista = [];
let timerBuscarLista;

const selLista = document.getElementById('selectListaPrecio');
const btnCargar = document.getElementById('btnCargarLista');
if (selLista) selLista.addEventListener('change', async function() {
    const listaId = this.value;
    if (btnCargar) btnCargar.disabled = !listaId;
    const searchBox = document.getElementById('searchListaPrecioBox');
    const buscarInput = document.getElementById('buscarProductoLista');
    if (listaId) {
        searchBox.style.display = 'block';
        document.getElementById('cardListaPreciosBody').style.gridTemplateColumns = '1fr 1fr';
        try {
            const r = await fetch(`{{ route('cotizaciones.productos-lista-precio') }}?lista_id=${listaId}`);
            productosDeLista = await r.json();
        } catch (e) { productosDeLista = []; }
        buscarInput.value = '';
        document.getElementById('productoListaResults').classList.remove('show');
    } else {
        searchBox.style.display = 'none';
        document.getElementById('cardListaPreciosBody').style.gridTemplateColumns = '1fr';
        productosDeLista = [];
    }
});

document.getElementById('buscarProductoLista')?.addEventListener('input', function() {
    clearTimeout(timerBuscarLista);
    const q = this.value.trim();
    const box = document.getElementById('productoListaResults');
    if (q.length < 2) { box.classList.remove('show'); return; }
    timerBuscarLista = setTimeout(() => {
        const ql = q.toLowerCase();
        const list = productosDeLista.filter(p =>
            (p.nombre || '').toLowerCase().includes(ql) || (p.codigo || '').toLowerCase().includes(ql)
        ).slice(0, 10);
        window._productosListaFiltrados = list;
        box.innerHTML = list.length ? list.map((p, i) => `
            <div class="autocomplete-item" onclick="cargarProductoListaByIdx(${i})">
                <div class="autocomplete-item-name">${(p.nombre || '').replace(/</g,'&lt;')}</div>
                <div class="autocomplete-item-sub">${(p.codigo || '')} — $${parseFloat(p.precio).toFixed(2)}</div>
            </div>
        `).join('') : '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
        box.classList.add('show');
    }, 200);
});

function cargarProductoListaByIdx(i) {
    const p = (window._productosListaFiltrados || [])[i];
    if (!p) return;
    if (productos.find(x => x.id === p.id)) { alert('Este producto ya está en la cotización'); return; }
    productos.push({
        id: p.id, codigo: p.codigo, origen: '', nombre: p.nombre,
        cantidad: 1, unidad: p.unidad || 'PZA', precio: parseFloat(p.precio),
        descuento: 0, tasa_iva: p.tasa_iva, manual: false,
        imagenesExistentes: [], imagenesUrls: [], imagenesNuevas: [], previewNuevas: [],
    });
    document.getElementById('buscarProductoLista').value = '';
    document.getElementById('productoListaResults').classList.remove('show');
    renderProductos();
}

if (btnCargar) btnCargar.addEventListener('click', async function() {
    const listaId = document.getElementById('selectListaPrecio')?.value;
    if (!listaId) return;
    try {
        const r = await fetch(`{{ route('cotizaciones.productos-lista-precio') }}?lista_id=${listaId}`);
        const items = await r.json();
        if (!items.length) { alert('La lista no tiene productos.'); return; }
        items.forEach(p => {
            if (!productos.find(x => x.id === p.id)) {
                productos.push({
                    id: p.id, codigo: p.codigo, origen: '', nombre: p.nombre,
                    cantidad: 1, unidad: p.unidad || 'PZA', precio: parseFloat(p.precio),
                    descuento: 0, tasa_iva: p.tasa_iva, manual: false,
                    imagenesExistentes: [], imagenesUrls: [], imagenesNuevas: [], previewNuevas: [],
                });
            }
        });
        renderProductos();
    } catch (e) { console.error(e); alert('Error al cargar la lista.'); }
});

async function buscarProductos(q) {
    try {
        const r = await fetch(`{{ route('cotizaciones.buscar-productos') }}?q=${encodeURIComponent(q)}`);
        const data = await r.json();
        window._busquedaProductosTemp = data;
        const box = document.getElementById('productoResults');
        if (!data.length) {
            box.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
        } else {
            box.innerHTML = data.map((item, idx) => {
                const esc = (v) => (v || '').replace(/</g,'&lt;').replace(/"/g,'&quot;');
                const precio = item.tipo === 'sugerencia' ? item.precio_unitario : item.precio_venta;
                const label = item.codigo ? `${esc(item.codigo)} — ${esc(item.nombre)}` : esc(item.nombre);
                return `<div class="autocomplete-item ${item.tipo === 'sugerencia' ? 'autocomplete-item-sugerencia' : ''}" data-idx="${idx}" onclick="agregarDesdeBusqueda(window._busquedaProductosTemp[this.dataset.idx])">
                    <div class="autocomplete-item-name">${label}</div>
                    <div class="autocomplete-item-sub">${item.tipo === 'sugerencia' ? '💡 Sugerencia' : '📦 Producto'} — $${parseFloat(precio).toFixed(2)}</div>
                </div>`;
            }).join('');
        }
        box.classList.add('show');
    } catch(e) { console.error(e); }
}

function agregarDesdeBusqueda(item) {
    if (item.tipo === 'sugerencia') {
        if (productos.find(x => x.sugerencia_id === item.id)) { alert('Esta sugerencia ya está en la cotización'); return; }
        productos.push({
            id: null, codigo: item.codigo || '-', origen: '', nombre: item.nombre,
            cantidad: 1, unidad: item.unidad || 'PZA', precio: parseFloat(item.precio_unitario),
            descuento: 0, tasa_iva: 0.16, manual: true, sugerencia_id: item.id,
            imagenesExistentes: [], imagenesUrls: [], imagenesNuevas: [], previewNuevas: [],
        });
    } else {
        if (productos.find(x => x.id === item.id && !x.manual)) { alert('Este producto ya está en la cotización'); return; }
        productos.push({
            id: item.id, codigo: item.codigo, origen: '', nombre: item.nombre,
            cantidad: 1, unidad: item.unidad || 'PZA', precio: parseFloat(item.precio_venta),
            descuento: 0, tasa_iva: item.tasa_iva, manual: false,
            imagenesExistentes: [], imagenesUrls: [], imagenesNuevas: [], previewNuevas: [],
        });
    }
    document.getElementById('buscarProducto').value = '';
    closeDropdown('productoResults');
    renderProductos();
}

function agregarProducto(p) {
    if (productos.find(x => x.id === p.id && !x.manual)) { alert('Este producto ya está en la lista'); return; }
    productos.push({
        id: p.id, codigo: p.codigo, origen: '', nombre: p.nombre,
        cantidad: 1, unidad: p.unidad || 'PZA', precio: parseFloat(p.precio_venta),
        descuento: 0, tasa_iva: p.tasa_iva, manual: false,
        imagenesExistentes: [], imagenesUrls: [], imagenesNuevas: [], previewNuevas: [],
    });
    document.getElementById('buscarProducto').value = '';
    closeDropdown('productoResults');
    renderProductos();
}

function agregarManual() {
    productos.push({
        id: null, codigo: '-', origen: '', nombre: '', cantidad: 1, unidad: 'PZA', precio: 0, descuento: 0, tasa_iva: 0.16,
        manual: true, sugerencia_id: null,
        imagenesExistentes: [], imagenesUrls: [], imagenesNuevas: [], previewNuevas: [],
    });
    renderProductos();
}

function initImagenesPartida(p) {
    if (!p.imagenesExistentes) p.imagenesExistentes = [];
    if (!p.imagenesUrls) p.imagenesUrls = [];
    if (!p.imagenesNuevas) p.imagenesNuevas = [];
    if (!p.previewNuevas) p.previewNuevas = [];
}

function contarImagenesPartida(p) {
    initImagenesPartida(p);
    return (p.imagenesExistentes?.length || 0) + (p.imagenesNuevas?.length || 0);
}

function liberarPreviewNuevas(p) {
    (p.previewNuevas || []).forEach(url => { try { URL.revokeObjectURL(url); } catch (e) {} });
    p.previewNuevas = [];
}

function agregarImagenPartida(i) {
    const p = productos[i];
    if (!p) return;
    initImagenesPartida(p);
    if (contarImagenesPartida(p) >= MAX_IMAGENES_PARTIDA) {
        alert('Máximo ' + MAX_IMAGENES_PARTIDA + ' imágenes por partida.');
        return;
    }
    const input = document.getElementById('imagenInput_' + i);
    if (input) {
        input.value = '';
        input.click();
    }
}

function onImagenPartidaSelected(i, input) {
    const p = productos[i];
    if (!p || !input?.files?.length) return;
    initImagenesPartida(p);
    const restantes = MAX_IMAGENES_PARTIDA - contarImagenesPartida(p);
    if (restantes <= 0) {
        alert('Máximo ' + MAX_IMAGENES_PARTIDA + ' imágenes por partida.');
        input.value = '';
        return;
    }
    const aceptados = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    Array.from(input.files).slice(0, restantes).forEach(file => {
        if (!aceptados.includes(file.type)) return;
        if (file.size > 5 * 1024 * 1024) {
            alert('Cada imagen no debe superar 5 MB.');
            return;
        }
        p.imagenesNuevas.push(file);
        p.previewNuevas.push(URL.createObjectURL(file));
    });
    input.value = '';
    renderProductos();
}

function quitarImagenExistente(i, idx) {
    const p = productos[i];
    if (!p?.imagenesExistentes) return;
    p.imagenesExistentes.splice(idx, 1);
    p.imagenesUrls.splice(idx, 1);
    renderProductos();
}

function quitarImagenNueva(i, idx) {
    const p = productos[i];
    if (!p?.imagenesNuevas) return;
    if (p.previewNuevas[idx]) {
        try { URL.revokeObjectURL(p.previewNuevas[idx]); } catch (e) {}
    }
    p.imagenesNuevas.splice(idx, 1);
    p.previewNuevas.splice(idx, 1);
    renderProductos();
}

function renderImagenesPreview(i, p) {
    initImagenesPartida(p);
    const items = [];
    (p.imagenesUrls || []).forEach((url, j) => {
        const src = (url || '').replace(/"/g, '&quot;');
        items.push(`<div class="partida-imagen-thumb" title="Imagen guardada">
            <img src="${src}" alt="">
            <button type="button" onclick="quitarImagenExistente(${i}, ${j})" title="Quitar">×</button>
        </div>`);
    });
    (p.previewNuevas || []).forEach((url, j) => {
        const src = (url || '').replace(/"/g, '&quot;');
        items.push(`<div class="partida-imagen-thumb" title="Nueva imagen">
            <img src="${src}" alt="">
            <button type="button" onclick="quitarImagenNueva(${i}, ${j})" title="Quitar">×</button>
        </div>`);
    });
    return items.length ? `<div class="partida-imagenes-preview">${items.join('')}</div>` : '';
}

function syncImagenesAlFormulario() {
    const container = document.getElementById('imagenesHiddenContainer');
    if (!container) return;
    container.innerHTML = '';
    productos.forEach((p, i) => {
        initImagenesPartida(p);
        (p.imagenesExistentes || []).forEach(path => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = `productos[${i}][imagenes_mantener][]`;
            inp.value = path;
            container.appendChild(inp);
        });
        (p.imagenesNuevas || []).forEach(file => {
            const dt = new DataTransfer();
            dt.items.add(file);
            const inp = document.createElement('input');
            inp.type = 'file';
            inp.name = `productos[${i}][imagenes][]`;
            inp.files = dt.files;
            inp.style.display = 'none';
            container.appendChild(inp);
        });
    });
}

function renderProductos() {
    const tbody = document.getElementById('productosBody');
    if (!productos.length) {
        tbody.innerHTML = `<tr id="emptyRow"><td colspan="10">
            <div class="empty-state" style="padding:28px 20px;">
                <div class="empty-state-icon">📦</div>
                <div class="empty-state-title">Sin productos</div>
                <div class="empty-state-text">Usa el buscador para agregar</div>
            </div></td></tr>`;
        calcTotales(); return;
    }
    tbody.innerHTML = productos.map((p, i) => {
        const isMobile = window.matchMedia('(max-width: 768px)').matches;
        const origenEsc = (p.origen || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
        const nombreEsc = (p.nombre || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
        const sub = p.cantidad * p.precio;
        const desc = sub * (p.descuento / 100);
        const base = sub - desc;
        const iva = p.tasa_iva != null ? base * p.tasa_iva : 0;
        const total = base + iva;
        return `<tr>
            <td class="td-center">
                <input type="text" name="productos[${i}][origen]" value="${origenEsc}" maxlength="100"
                       onchange="upd(${i},'origen',this.value)" oninput="upd(${i},'origen',this.value)"
                       placeholder="—" class="form-control form-control-numeric" style="text-align:center; width:100%; font-size:13px;" autocomplete="off">
            </td>
            <td>
                ${p.manual
                    ? `<div class="search-box search-box-manual">
                       ${isMobile
                            ? `<textarea id="manualDesc_${i}" onchange="upd(${i},'nombre',this.value)" oninput="onManualDescInput(${i},this.value)" onkeydown="onManualDescKeydown(${i},event)" onfocus="lastSugerenciaRowIndex=${i}" placeholder="Descripción o código (3+ caracteres)..." class="form-control manual-desc-mobile" style="font-size:13px;" autocomplete="off" data-row="${i}">${nombreEsc}</textarea>`
                            : `<input type="text" id="manualDesc_${i}" value="${nombreEsc}" onchange="upd(${i},'nombre',this.value)" oninput="onManualDescInput(${i},this.value)" onkeydown="onManualDescKeydown(${i},event)" onfocus="lastSugerenciaRowIndex=${i}" placeholder="Descripción o código (3+ caracteres)..." class="form-control" style="font-size:13px;" autocomplete="off" data-row="${i}">`
                        }
                       </div>
                       <input type="hidden" name="productos[${i}][es_producto_manual]" value="1">
                       <input type="hidden" name="productos[${i}][sugerencia_id]" value="${p.sugerencia_id || ''}">`
                    : `<div class="fw-600" style="font-size:13.5px;">${p.nombre}</div>
                       <span class="producto-row-code">${p.codigo}</span>`}
                <input type="hidden" name="productos[${i}][producto_id]" value="${p.id || ''}">
                <input type="hidden" name="productos[${i}][descripcion]" value="${(p.nombre||'').replace(/"/g,'&quot;')}">
            </td>
            <td class="td-center">
                <input type="number" name="productos[${i}][cantidad]" value="${p.cantidad}" min="0.01" step="0.01"
                       onchange="upd(${i},'cantidad',+this.value)" class="form-control form-control-numeric" style="text-align:center; width:100%;">
            </td>
            <td class="td-center">
                <input type="text" name="productos[${i}][unidad]" value="${(p.unidad || 'PZA').replace(/"/g, '&quot;')}" 
                       onchange="upd(${i},'unidad',this.value)" class="form-control form-control-numeric" style="text-align:center; width:100%;" placeholder="PZA" maxlength="10">
            </td>
            <td class="td-center">
                <input type="number" name="productos[${i}][precio_unitario]" value="${p.precio.toFixed(2)}" min="0" step="0.01"
                       onchange="upd(${i},'precio',+this.value)" class="form-control form-control-numeric" style="text-align:right; width:100%;">
            </td>
            <td class="td-center">
                <input type="number" name="productos[${i}][descuento_porcentaje]" value="${p.descuento}" min="0" max="100"
                       onchange="upd(${i},'descuento',+this.value)" class="form-control form-control-numeric" style="text-align:center; width:100%;">
            </td>
            <td class="td-center">
                ${p.manual
                    ? `<select name="productos[${i}][tasa_iva]" onchange="upd(${i},'tasa_iva',this.value===''?null:+this.value)" class="form-control form-control-numeric" style="width:100%;">
                           <option value="0.16" ${p.tasa_iva==0.16?'selected':''}>16%</option>
                           <option value="0"    ${p.tasa_iva==0?'selected':''}>0%</option>
                           <option value=""     ${p.tasa_iva==null?'selected':''}>Exento</option>
                       </select>`
                    : `<span class="fw-600" style="font-size:13px;">${p.tasa_iva == null ? 'Exento' : (p.tasa_iva*100)+'%'}</span>
                       <input type="hidden" name="productos[${i}][tasa_iva]" value="${p.tasa_iva!=null?p.tasa_iva:''}">`}
            </td>
            <td class="td-right text-mono" style="font-size:13px;">$${fmtMonto(sub)}</td>
            <td class="td-right text-mono fw-bold" style="color: var(--color-secondary); font-size:13.5px;">$${fmtMonto(total)}</td>
            <td class="td-right">
                <div class="partida-acciones">
                    <div class="partida-orden">
                        <button type="button" onclick="moverProducto(${i}, -1)" class="btn btn-outline btn-icon btn-sm"
                                title="Subir partida" ${i === 0 ? 'disabled' : ''}>▲</button>
                        <span class="partida-orden-num" title="Partida ${i + 1}">${i + 1}</span>
                        <button type="button" onclick="moverProducto(${i}, 1)" class="btn btn-outline btn-icon btn-sm"
                                title="Bajar partida" ${i === productos.length - 1 ? 'disabled' : ''}>▼</button>
                    </div>
                    <div class="partida-acciones-btns">
                        <button type="button" onclick="agregarImagenPartida(${i})" class="btn btn-outline btn-icon btn-sm"
                                title="Agregar foto (${contarImagenesPartida(p)}/${MAX_IMAGENES_PARTIDA})"
                                ${contarImagenesPartida(p) >= MAX_IMAGENES_PARTIDA ? 'disabled' : ''}>+</button>
                        <button type="button" onclick="quitarProducto(${i})" class="btn btn-danger btn-icon btn-sm" title="Quitar partida">✕</button>
                    </div>
                    ${renderImagenesPreview(i, p)}
                </div>
                <input type="file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" multiple
                       id="imagenInput_${i}" style="display:none" onchange="onImagenPartidaSelected(${i}, this)">
            </td>
        </tr>`;
    }).join('');
    calcTotales();
}

function moverProducto(i, direccion) {
    const destino = i + direccion;
    if (destino < 0 || destino >= productos.length) return;
    closeSugerenciaFlotante();
    const tmp = productos[i];
    productos[i] = productos[destino];
    productos[destino] = tmp;
    renderProductos();
}

function upd(i, field, val) {
    productos[i][field] = val;
    if (field === 'nombre' || field === 'origen') {
        if (field === 'nombre') {
            document.querySelectorAll(`input[name="productos[${i}][descripcion]"]`).forEach(el => el.value = val);
        }
    } else {
        renderProductos();
    }
}

function onManualDescInput(rowIndex, value) {
    upd(rowIndex, 'nombre', value);
    clearTimeout(timerSugerencia[rowIndex]);
    const q = (value || '').trim();
    if (q.length < 3) {
        closeSugerenciaFlotante();
        return;
    }
    timerSugerencia[rowIndex] = setTimeout(() => buscarSugerencias(rowIndex, q), 280);
}

function closeSugerenciaFlotante() {
    const flotante = document.getElementById('sugerenciaResultsFlotante');
    if (flotante) { flotante.innerHTML = ''; flotante.classList.remove('show'); flotante.style.display = 'none'; }
}

function onManualDescKeydown(rowIndex, e) {
    if (e.key !== 'Enter') return;
    const flotante = document.getElementById('sugerenciaResultsFlotante');
    if (!flotante || !flotante.classList.contains('show')) return;
    const first = flotante.querySelector('.autocomplete-item');
    if (first) { first.click(); e.preventDefault(); }
}

async function buscarSugerencias(rowIndex, q) {
    try {
        const r = await fetch(`{{ route('sugerencias.buscar') }}?q=${encodeURIComponent(q)}`);
        const data = await r.json();
        const input = document.getElementById('manualDesc_' + rowIndex);
        const flotante = document.getElementById('sugerenciaResultsFlotante');
        if (!input || !flotante) return;
        closeSugerenciaFlotante();
        const rect = input.getBoundingClientRect();
        flotante.style.top = (rect.bottom + 6) + 'px';
        flotante.style.left = rect.left + 'px';
        flotante.style.width = Math.max(rect.width, 420) + 'px';
        flotante.style.minWidth = '380px';
        if (!data.length) {
            flotante.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin sugerencias</div></div>';
        } else {
            flotante.innerHTML = data.map(s => {
                const descCompleta = (s.descripcion || '').replace(/</g,'&lt;').replace(/"/g,'&quot;');
                const label = (s.codigo ? (s.codigo + ' — ') : '') + (s.descripcion || '');
                const labelEscaped = label.replace(/</g,'&lt;').replace(/"/g,'&quot;');
                return `<div class="autocomplete-item autocomplete-item-sugerencia" data-id="${s.id}" data-desc="${(s.descripcion||'').replace(/"/g,'&quot;')}" data-unidad="${(s.unidad||'PZA').replace(/"/g,'&quot;')}" data-precio="${s.precio_unitario}" onclick="aplicarSugerencia(${rowIndex}, this)">
                    <div class="autocomplete-item-name autocomplete-item-desc-full">${labelEscaped}</div>
                    <div class="autocomplete-item-sub">${(s.unidad||'PZA')} — $${parseFloat(s.precio_unitario).toFixed(2)}</div>
                </div>`;
            }).join('');
        }
        flotante.classList.add('show');
        flotante.style.display = 'block';
    } catch (e) { console.error(e); }
}

function aplicarSugerencia(rowIndex, el) {
    if (!el || !el.dataset) return;
    const id = el.dataset.id, desc = el.dataset.desc || '', unidad = el.dataset.unidad || 'PZA', precio = parseFloat(el.dataset.precio) || 0;
    if (!id) return;
    productos[rowIndex].nombre = desc;
    productos[rowIndex].unidad = unidad;
    productos[rowIndex].precio = precio;
    productos[rowIndex].sugerencia_id = id;
    closeSugerenciaFlotante();
    renderProductos();
}

function quitarProducto(i) {
    const p = productos[i];
    if (p) liberarPreviewNuevas(p);
    closeSugerenciaFlotante();
    productos.splice(i, 1);
    renderProductos();
}

    function fmtMonto(n) {
        return (typeof n === 'number' ? n : parseFloat(n) || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

function calcTotales() {
    let sub = 0, desc = 0, iva = 0;
    productos.forEach(p => {
        const s = p.cantidad * p.precio;
        const d = s * (p.descuento / 100);
        sub += s; desc += d;
        if (p.tasa_iva != null) iva += (s - d) * p.tasa_iva;
    });
    const isr = aplicaRetencionIsrPm(clienteTipoPersona) ? calcularRetencionIsrPm(sub, desc) : 0;
    document.getElementById('tSubtotal').textContent = '$' + fmtMonto(sub);
    document.getElementById('tDescuento').textContent = '−$' + fmtMonto(desc);
    document.getElementById('tIva').textContent = '$' + fmtMonto(iva);
    document.getElementById('tIsrRetenido').textContent = '−$' + fmtMonto(isr);
    document.getElementById('tTotal').textContent = '$' + fmtMonto((sub - desc) + iva - isr);
    document.getElementById('rowDescuento').style.display = desc > 0 ? 'flex' : 'none';
    document.getElementById('rowIsrRetenido').style.display = isr !== 0 ? 'flex' : 'none';
}

document.getElementById('cotizacionForm').addEventListener('submit', e => {
    if (!document.getElementById('cliente_id').value) {
        e.preventDefault();
        alert('⚠️ Selecciona un cliente');
        document.getElementById('buscarCliente').focus();
        return;
    }
    if (!productos.length) {
        e.preventDefault();
        alert('⚠️ Agrega al menos un producto');
        document.getElementById('buscarProducto').focus();
        return;
    }
    syncImagenesAlFormulario();
    allowNavigation = true;
});
</script>
@endpush