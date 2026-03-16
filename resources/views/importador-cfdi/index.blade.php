@extends('layouts.app')

@section('title', 'Importador CFDI')
@section('page-title', '📥 Importador CFDI')
@section('page-subtitle', 'Importar facturas y complementos de pago desde archivos XML (CFDI 4.0)')

@php
$breadcrumbs = [['title' => 'Importador CFDI']];
@endphp

@section('content')

@if(session('importador_resultados'))
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <div class="card-title">Resultado de la importación</div>
    </div>
    <div class="card-body">
        <p style="margin-bottom: 16px;">
            <strong>Importados:</strong> {{ session('importador_importados', 0) }}
            &nbsp;|&nbsp;
            <strong>Fallidos:</strong> {{ session('importador_fallidos', 0) }}
        </p>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(session('importador_resultados') as $r)
                    <tr>
                        <td class="text-mono">{{ $r['archivo'] }}</td>
                        <td>{{ $r['tipo'] === 'factura' ? 'Factura' : ($r['tipo'] === 'complemento' ? 'Complemento de pago' : ($r['tipo'] === 'acuse_cancelacion' ? 'Acuse cancelación' : '—')) }}</td>
                        <td>
                            @if($r['success'])
                                <span class="badge badge-success">Importado</span>
                            @else
                                <span class="badge badge-danger">Error</span>
                            @endif
                        </td>
                        <td>
                            @if($r['success'] && !empty($r['modelo_id']))
                                @if($r['tipo'] === 'factura' || $r['tipo'] === 'acuse_cancelacion')
                                    <a href="{{ route('facturas.show', $r['modelo_id']) }}" class="btn btn-outline btn-sm">{{ $r['tipo'] === 'acuse_cancelacion' ? 'Ver factura cancelada' : 'Ver factura' }}</a>
                                @elseif($r['tipo'] === 'complemento')
                                    <a href="{{ route('complementos.show', $r['modelo_id']) }}" class="btn btn-outline btn-sm">Ver complemento</a>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @if(!empty($r['errors']) || !empty($r['warnings']))
                    <tr>
                        <td colspan="4" style="padding-top: 0; padding-left: 24px; font-size: 13px;">
                            @foreach($r['errors'] as $err)
                                <div style="color: var(--color-danger);">✗ {{ $err }}</div>
                            @endforeach
                            @foreach($r['warnings'] as $w)
                                <div style="color: #B45309;">⚠ {{ $w }}</div>
                            @endforeach
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="responsive-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Subir archivos XML</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('importador-cfdi.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label class="form-label">Archivos XML (CFDI 4.0)</label>
                    <input type="file" name="archivos[]" multiple accept=".xml,application/xml" class="form-control" required>
                    <span class="form-hint">Puede seleccionar varios archivos. Soporta facturas (I/E) y complementos de pago (P).</span>
                    @error('archivos')
                        <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                    @enderror
                    @error('archivos.*')
                        <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                    @enderror
                </div>
                <div style="display: flex; gap: 12px; margin-top: 16px;">
                    <button type="submit" class="btn btn-primary">📥 Importar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Requisitos</div>
        </div>
        <div class="card-body">
            <ul style="margin: 0; padding-left: 20px; line-height: 1.8; color: var(--color-gray-700);">
                <li>Los clientes (receptor) deben existir en <strong>Administración &gt; Clientes</strong> con el mismo RFC que el XML.</li>
                <li>La empresa emisora debe estar configurada en <strong>Sistema &gt; Configuración</strong> (RFC del emisor).</li>
                <li>Para que los <strong>complementos de pago</strong> actualicen cuentas por cobrar, importe primero las <strong>facturas</strong> relacionadas.</li>
                <li><strong>Facturas canceladas:</strong> importe primero el XML de la factura y después el XML del <strong>acuse de cancelación</strong>; la factura quedará en estado cancelada y podrá descargar el XML cancelado.</li>
                <li>No se duplican comprobantes: si el UUID ya existe, se omite el archivo.</li>
                <li>Facturas PPD generan automáticamente la cuenta por cobrar; los complementos aplican el pago con <code>registrarPago</code>.</li>
            </ul>
            <p style="margin-top: 16px; margin-bottom: 0; font-size: 13px; color: var(--color-gray-600);">
                <strong>Tipos soportados:</strong> I (ingreso), E (egreso), P (complemento de pago), y acuse de cancelación SAT (XML con ReferenciaUUID). CFDI 4.0.
            </p>
        </div>
    </div>
</div>

@endsection
