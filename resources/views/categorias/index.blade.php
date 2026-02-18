@extends('layouts.app')

@section('title', 'Categorías')
@section('page-title', '🗂️ Categorías')
@section('page-subtitle', 'Administra las categorías de productos')

@php
$breadcrumbs = [
    ['title' => 'Categorías']
];
@endphp

@section('content')

<div class="card">
    <div class="card-header">
        <div class="card-title">Lista de Categorías</div>
        <a href="{{ route('categorias.create') }}" class="btn btn-primary">
            + Nueva Categoría
        </a>
    </div>

    <div class="card-body" style="padding:0;">
        <div class="table-container" style="margin-bottom:0;">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Código</th>
                        <th>Padre</th>
                        <th>Orden</th>
                        <th class="td-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categorias as $categoria)
                        <tr>
                            <td>
                                {{ $categoria->icono }}
                                {{ $categoria->nombre }}
                            </td>
                            <td class="text-mono">{{ $categoria->codigo }}</td>
                            <td>{{ optional($categoria->parent)->nombre ?? '-' }}</td>
                            <td>{{ $categoria->orden }}</td>
                            <td class="td-right">
                                <a href="{{ route('categorias.edit', $categoria) }}" class="btn btn-light btn-sm">
                                    Editar
                                </a>

                                <form action="{{ route('categorias.destroy', $categoria) }}"
                                      method="POST"
                                      style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('¿Eliminar categoría?')">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center; padding:40px;">
                                No hay categorías registradas
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div style="margin-top:16px;">
    {{ $categorias->links() }}
</div>

@endsection