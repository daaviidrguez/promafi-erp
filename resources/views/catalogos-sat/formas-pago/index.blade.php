@extends('layouts.app')

@section('title', 'Formas de pago')
@section('page-title', '💳 Formas de pago')
@section('page-subtitle', 'Catálogo SAT de formas de pago')

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Catálogos SAT', 'url' => route('catalogos-sat.index')],
    ['title' => 'Formas de pago']
];
@endphp

@section('content')

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card">
    <div class="card-header">
        <div class="card-title">Lista</div>
        <a href="{{ route('catalogos-sat.formas-pago.create') }}" class="btn btn-primary">+ Nuevo</a>
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
                                <a href="{{ route('catalogos-sat.formas-pago.edit', $item) }}" class="btn btn-light btn-sm">Editar</a>
                                <form action="{{ route('catalogos-sat.formas-pago.destroy', $item) }}" method="POST" style="display:inline;">@csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar?')">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align:center; padding:40px;">No hay registros</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div style="margin-top:16px;">{{ $items->links() }}</div>

@endsection
