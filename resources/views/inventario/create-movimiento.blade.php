@extends('layouts.app')
@section('title', 'Entrada / Salida manual')
@section('page-title', '➕ Entrada o salida manual')
@section('page-subtitle', 'Registrar movimiento de inventario')

@php
$breadcrumbs = [['title' => 'Inventario', 'url' => route('inventario.index')], ['title' => 'Movimientos', 'url' => route('inventario.movimientos')], ['title' => 'Nuevo']];
@endphp

@section('content')
<form method="POST" action="{{ route('inventario.store-movimiento') }}">
    @csrf
    <div class="card">
        <div class="card-header"><div class="card-title">📦 Movimiento</div></div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Producto <span class="req">*</span></label>
                <select name="producto_id" class="form-control" required>
                    <option value="">Seleccionar producto...</option>
                    @foreach($productos as $p)
                        <option value="{{ $p->id }}" {{ old('producto_id', $productoId ?? null) == $p->id ? 'selected' : '' }}>{{ $p->codigo }} — {{ $p->nombre }} (stock: {{ number_format($p->stock, 2) }})</option>
                    @endforeach
                </select>
                @error('producto_id')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Tipo <span class="req">*</span></label>
                    <select name="tipo" class="form-control" required>
                        <option value="entrada_manual" {{ old('tipo', 'entrada_manual') == 'entrada_manual' ? 'selected' : '' }}>Entrada manual</option>
                        <option value="salida_manual" {{ old('tipo') == 'salida_manual' ? 'selected' : '' }}>Salida manual</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Cantidad <span class="req">*</span></label>
                    <input type="number" name="cantidad" class="form-control" value="{{ old('cantidad') }}" min="0.01" step="0.01" required>
                    @error('cantidad')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Observaciones</label>
                <input type="text" name="observaciones" class="form-control" value="{{ old('observaciones') }}" placeholder="Opcional" maxlength="500">
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="display:flex;gap:12px;justify-content:flex-end;">
            <a href="{{ route('inventario.movimientos') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Registrar movimiento</button>
        </div>
    </div>
</form>
@endsection
