@extends('layouts.app')

@section('title', 'Auditoría · Errores del sistema')
@section('page-title', '🩺 Auditoría')
@section('page-subtitle', 'Excepciones no controladas (errores 500) que vieron los usuarios. Solo lectura.')

@php
$breadcrumbs = [
    ['title' => 'Sistema'],
    ['title' => 'Auditoría'],
];
$claseBase = fn (string $clase) => class_basename($clase);
@endphp

@section('content')

@include('auditoria._tabs')

<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <form method="GET" action="{{ route('auditoria.errores') }}" class="filtros-bar" style="flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="desde">Desde</label>
                <input id="desde" type="date" name="desde" class="form-control" value="{{ $desde->toDateString() }}">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="hasta">Hasta</label>
                <input id="hasta" type="date" name="hasta" class="form-control" value="{{ $hasta->toDateString() }}">
            </div>
            <div class="form-group" style="margin:0; flex:1; min-width:220px;">
                <label class="form-label" for="q">Buscar</label>
                <input id="q" type="text" name="q" class="form-control" value="{{ $q }}" placeholder="Excepción, mensaje, archivo o ruta…">
            </div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            @if(request()->anyFilled(['desde', 'hasta', 'q']))
                <a href="{{ route('auditoria.errores') }}" class="btn btn-light">Limpiar</a>
            @endif
        </form>
        <p class="form-hint" style="margin:12px 0 0;">
            No incluye errores esperados (validaciones, sesión expirada, sin permiso, registro no encontrado ni páginas 404).
            Si MySQL está saturado el error no se guarda aquí para no empeorarlo; queda en <strong>Rendimiento → Errores de MySQL</strong>.
            Se conservan {{ $diasRetencion }} días.
        </p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card {{ $hoy > 0 ? 'stat-danger' : 'stat-success' }}">
        <div class="stat-info-box">
            <div class="stat-label">Errores hoy</div>
            <div class="stat-value">{{ $hoy }}</div>
        </div>
        <div class="stat-icon">🧯</div>
    </div>
    <div class="stat-card {{ $registros->total() > 0 ? 'stat-warning' : 'stat-success' }}">
        <div class="stat-info-box">
            <div class="stat-label">En el periodo</div>
            <div class="stat-value">{{ $registros->total() }}</div>
            <div class="stat-sub">{{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}</div>
        </div>
        <div class="stat-icon">📅</div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-info-box">
            <div class="stat-label">Errores distintos</div>
            <div class="stat-value">{{ $frecuentes->count() }}{{ $frecuentes->count() >= 10 ? '+' : '' }}</div>
            <div class="stat-sub">Por excepción y archivo</div>
        </div>
        <div class="stat-icon">🧩</div>
    </div>
</div>

@if ($frecuentes->isNotEmpty())
    <h3 style="font-size:15px; margin:20px 0 4px;">Más frecuentes</h3>
    <p class="form-hint" style="margin:0 0 8px;">Agrupados por excepción y archivo de origen: corrige primero los de arriba.</p>
    <div class="table-container table-container--scroll" style="margin-bottom:16px;">
        <table>
            <thead>
                <tr>
                    <th class="td-right">Veces</th>
                    <th>Excepción</th>
                    <th>Origen</th>
                    <th class="td-right">Rutas</th>
                    <th>Último</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($frecuentes as $f)
                    <tr>
                        <td class="td-right"><span class="badge {{ $f->veces >= 10 ? 'badge-danger' : 'badge-warning' }}">{{ $f->veces }}</span></td>
                        <td>
                            <div class="fw-600" title="{{ $f->excepcion }}">{{ $claseBase($f->excepcion) }}</div>
                            <div class="text-muted" style="font-size:12px; max-width:480px; word-break:break-word;">{{ \Illuminate\Support\Str::limit((string) $f->mensaje, 200) }}</div>
                        </td>
                        <td class="text-mono" style="font-size:12px;">{{ $f->archivo ?? '—' }}</td>
                        <td class="td-right">{{ $f->rutas }}</td>
                        <td class="text-muted" style="font-size:12px; white-space:nowrap;">{{ \Carbon\Carbon::parse($f->ultima)->format('d/m H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

<h3 style="font-size:15px; margin:20px 0 8px;">Bitácora</h3>
<div class="table-container table-container--scroll">
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Excepción</th>
                <th>Ruta</th>
                <th>Origen</th>
                <th>Usuario</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($registros as $r)
                <tr>
                    <td style="white-space:nowrap;">{{ $r->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td style="max-width:480px;">
                        <div class="fw-600" title="{{ $r->excepcion }}">{{ $claseBase($r->excepcion) }}</div>
                        @if ($r->mensaje)
                            <details style="font-size:12px;">
                                <summary class="text-muted" style="cursor:pointer; word-break:break-word;">{{ \Illuminate\Support\Str::limit($r->mensaje, 140) }}</summary>
                                <code style="display:block; white-space:pre-wrap; word-break:break-word; margin-top:4px;">{{ $r->mensaje }}</code>
                            </details>
                        @endif
                    </td>
                    <td>
                        {{ $r->ruta_nombre ?? '—' }}
                        <div class="text-muted" style="font-size:12px;">{{ $r->metodo }} {{ $r->ruta }}</div>
                    </td>
                    <td class="text-mono" style="font-size:12px;">{{ $r->archivo ?? '—' }}</td>
                    <td>{{ $r->usuario?->name ?? '—' }}</td>
                    <td class="text-muted" style="font-size:12px;">{{ $r->ip ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted" style="padding:40px;">
                        {{ $q !== '' ? 'No hay errores que coincidan con la búsqueda.' : 'Sin errores no controlados en el periodo. 🎉' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    @if ($registros->hasPages())
        <div style="padding:16px 20px; border-top:1px solid var(--color-gray-100);">
            {{ $registros->links() }}
        </div>
    @endif
</div>

@endsection
