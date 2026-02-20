@extends('layouts.app')
@section('title', 'Sugerencias')
@section('page-title', '💡 Sugerencias de partidas')
@section('page-subtitle', 'Partidas guardadas para autocompletar en cotizaciones (productos manuales)')
@section('page-actions')
    <a href="{{ route('sugerencias.create') }}" class="btn btn-primary">➕ Nueva Sugerencia</a>
@endsection

@php
$breadcrumbs = [['title' => 'Sugerencias']];
@endphp

@section('content')

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('sugerencias.index') }}" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar por código o descripción (mín. 3 caracteres)..." class="form-control" style="min-width:280px;">
            <button type="submit" class="btn btn-primary">🔍 Buscar</button>
            @if($search ?? false)
            <a href="{{ route('sugerencias.index') }}" class="btn btn-light">✕ Limpiar</a>
            @endif
        </form>
    </div>
</div>

<div class="table-container">
    @if($sugerencias->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Código / Clave</th>
                <th>Descripción</th>
                <th class="td-center">Unidad</th>
                <th class="td-right">Precio unit.</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sugerencias as $s)
            <tr>
                <td class="text-mono">{{ $s->codigo ?? '—' }}</td>
                <td><div class="fw-600 text-primary" style="font-size:13.5px;">{{ Str::limit($s->descripcion, 60) }}</div></td>
                <td class="td-center">{{ $s->unidad }}</td>
                <td class="td-right text-mono">${{ number_format($s->precio_unitario, 2) }}</td>
                <td class="td-actions">
                    <a href="{{ route('sugerencias.show', $s->id) }}" class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                    <a href="{{ route('sugerencias.edit', $s->id) }}" class="btn btn-warning btn-sm btn-icon" title="Editar">✏️</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px 20px; border-top:1px solid var(--color-gray-100);">{{ $sugerencias->withQueryString()->links() }}</div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">💡</div>
        <div class="empty-state-title">No hay sugerencias</div>
        <div class="empty-state-text">Crea sugerencias para que al cotizar productos manuales se autocompleten descripción, unidad y precio al escribir desde 3 caracteres (código o descripción).</div>
        <a href="{{ route('sugerencias.create') }}" class="btn btn-primary" style="margin-top:16px;">➕ Nueva Sugerencia</a>
    </div>
    @endif
</div>

@endsection
