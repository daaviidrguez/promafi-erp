@extends('layouts.app')

@section('title', 'Clave producto/servicio')
@section('page-title', 'Clave producto/servicio')
@section('page-subtitle', 'Catálogo SAT (carga masiva por Excel)')

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Catálogos SAT', 'url' => route('catalogos-sat.index')],
    ['title' => 'Clave producto/servicio']
];
@endphp

@section('content')

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card">
    <div class="card-header">
        <div class="card-title">Lista</div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('catalogos-sat.claves-producto-servicio.plantilla') }}" class="btn btn-outline btn-sm">Descargar plantilla Excel</a>
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('formImportar').style.display=document.getElementById('formImportar').style.display==='none'?'block':'none'">Importar Excel</button>
            <a href="{{ route('catalogos-sat.claves-producto-servicio.create') }}" class="btn btn-primary">+ Nuevo</a>
        </div>
    </div>
    <div id="formImportar" class="card-body" style="display:none; border-bottom:1px solid #eee;">
        <form action="{{ route('catalogos-sat.claves-producto-servicio.importar') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <p class="text-muted small">Excel: primera fila encabezados clave, descripcion. Clave 8 caracteres.</p>
            <div style="display:flex; gap:12px; align-items:center;">
                <input type="file" name="archivo" accept=".xlsx,.xls" required>
                <button type="submit" class="btn btn-primary">Subir e importar</button>
            </div>
        </form>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-container" style="margin-bottom:0;">
            <table>
                <thead>
                    <tr>
                        <th>Clave</th>
                        <th>Descripción</th>
                        <th>Orden</th>
                        <th>Activo</th>
                        <th class="td-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td class="text-mono">{{ $item->clave }}</td>
                            <td>{{ $item->descripcion }}</td>
                            <td>{{ $item->orden }}</td>
                            <td>{{ $item->activo ? 'Sí' : 'No' }}</td>
                            <td class="td-right">
                                <a href="{{ route('catalogos-sat.claves-producto-servicio.edit', $item) }}" class="btn btn-light btn-sm">Editar</a>
                                <form action="{{ route('catalogos-sat.claves-producto-servicio.destroy', $item) }}" method="POST" style="display:inline;">@csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar?')">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align:center; padding:40px;">No hay registros. Descarga la plantilla y sube el Excel.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div style="margin-top:16px;">{{ $items->links() }}</div>

@endsection
