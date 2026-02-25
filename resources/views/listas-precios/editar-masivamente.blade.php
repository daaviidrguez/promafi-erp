@extends('layouts.app')

@section('title', 'Editar masivamente: ' . $listaPrecio->nombre)
@section('page-title', '📊 Editar masivamente')
@section('page-subtitle', $listaPrecio->nombre)

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Listas de Precios', 'url' => route('listas-precios.index')],
    ['title' => $listaPrecio->nombre, 'url' => route('listas-precios.show', $listaPrecio)],
    ['title' => 'Editar masivamente']
];
@endphp

@section('content')

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card">
    <div class="card-header"><div class="card-title">📥 Paso 1: Descargar plantilla</div></div>
    <div class="card-body">
        <p class="mb-3">Descarga el Excel con los productos de esta lista. Solo modifica las columnas <strong>tipo_utilidad</strong>, <strong>valor_utilidad</strong> y <strong>activo</strong>.</p>
        <a href="{{ route('listas-precios.descargar-plantilla', $listaPrecio) }}" class="btn btn-primary">⬇️ Descargar plantilla Excel</a>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><div class="card-title">📤 Paso 2: Subir archivo modificado</div></div>
    <div class="card-body">
        <form action="{{ route('listas-precios.importar-masivo', $listaPrecio) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="form-label">Archivo Excel</label>
                <input type="file" name="archivo" accept=".xlsx,.xls,.csv" required class="form-control">
            </div>
            <div class="form-hint mb-3">
                <strong>Columnas editables:</strong><br>
                • tipo_utilidad: 1 = Factorizado (Markup), 2 = Utilidad Real (Margen)<br>
                • valor_utilidad: Porcentaje (1 a 99)<br>
                • activo: 1 = Activo, 2 = Desactivado
            </div>
            <button type="submit" class="btn btn-primary">✓ Guardar cambios</button>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body" style="display:flex;gap:12px;align-items:center;">
        <a href="{{ route('listas-precios.show', $listaPrecio) }}" class="btn btn-light">← Volver a la lista</a>
    </div>
</div>

@endsection
