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

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
@endif

<div class="card">
    <div class="card-header">
        <div class="card-title">Lista de Categorías</div>
        <button type="button" class="btn btn-primary" onclick="abrirModalCrearCategoria()">
            + Nueva Categoría
        </button>
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
                                <button type="button"
                                        class="btn btn-light btn-sm"
                                        onclick="abrirModalEditarCategoria({{ $categoria->id }})">
                                    Editar
                                </button>

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

{{-- Modal crear --}}
<div id="modalCrearCategoria" class="modal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div class="modal-title">Nueva categoría</div>
            <button type="button" class="modal-close" onclick="cerrarModalCrearCategoria()">✕</button>
        </div>
        <form id="formCrearCategoria" method="POST" action="{{ route('categorias.store') }}">
            @csrf
            <input type="hidden" name="_form_context" value="create">
            <div class="modal-body">
                @if($errors->any() && old('_form_context') === 'create')
                <div class="alert alert-danger" style="margin-bottom:12px;">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
                <div class="form-group">
                    <label class="form-label">Nombre <span class="req">*</span></label>
                    <input type="text" name="nombre" class="form-control" required value="{{ old('_form_context') === 'create' ? old('nombre') : '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Código</label>
                    <input type="text" name="codigo" class="form-control text-mono" value="{{ old('_form_context') === 'create' ? old('codigo') : '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Categoría padre</label>
                    <select name="parent_id" class="form-control">
                        <option value="">Sin padre (raíz)</option>
                        @foreach($categoriasPadre as $cat)
                        <option value="{{ $cat->id }}" @selected(old('_form_context') === 'create' && (string)old('parent_id') === (string)$cat->id)>{{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2">{{ old('_form_context') === 'create' ? old('descripcion') : '' }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="text" name="color" class="form-control" placeholder="#hex o nombre" value="{{ old('_form_context') === 'create' ? old('color') : '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Icono</label>
                    <input type="text" name="icono" class="form-control" placeholder="Ej: 📦" value="{{ old('_form_context') === 'create' ? old('icono') : '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Orden</label>
                    <input type="number" name="orden" class="form-control" value="{{ old('_form_context') === 'create' ? old('orden', 0) : 0 }}">
                </div>
            </div>
            <div class="modal-footer" style="display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" class="btn btn-light" onclick="cerrarModalCrearCategoria()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal editar --}}
<div id="modalEditarCategoria" class="modal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div class="modal-title">Editar categoría</div>
            <button type="button" class="modal-close" onclick="cerrarModalEditarCategoria()">✕</button>
        </div>
        <form id="formEditarCategoria" method="POST" action="">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form_context" value="edit">
            <input type="hidden" name="categoria_edit_id" id="categoria_edit_id" value="">
            <div class="modal-body">
                @if($errors->any() && old('_form_context') === 'edit')
                <div class="alert alert-danger" style="margin-bottom:12px;">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
                <div class="form-group">
                    <label class="form-label">Nombre <span class="req">*</span></label>
                    <input type="text" name="nombre" id="edit_nombre" class="form-control" required value="{{ old('_form_context') === 'edit' ? old('nombre') : '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Código</label>
                    <input type="text" name="codigo" id="edit_codigo" class="form-control text-mono" value="{{ old('_form_context') === 'edit' ? old('codigo') : '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Categoría padre</label>
                    <select name="parent_id" id="edit_parent_id" class="form-control"></select>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" id="edit_descripcion" class="form-control" rows="2">{{ old('_form_context') === 'edit' ? old('descripcion') : '' }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="text" name="color" id="edit_color" class="form-control" value="{{ old('_form_context') === 'edit' ? old('color') : '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Icono</label>
                    <input type="text" name="icono" id="edit_icono" class="form-control" value="{{ old('_form_context') === 'edit' ? old('icono') : '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Orden</label>
                    <input type="number" name="orden" id="edit_orden" class="form-control" value="{{ old('_form_context') === 'edit' ? old('orden', 0) : '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label" style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" name="activo" id="edit_activo" value="1">
                        Activa
                    </label>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" class="btn btn-light" onclick="cerrarModalEditarCategoria()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Actualizar</button>
            </div>
        </form>
    </div>
</div>

@endsection

@php
$categoriasPadreLista = $categoriasPadre->map(function ($c) {
    return [
        'id' => $c->id,
        'nombre' => $c->nombre
    ];
});

$categoriasPorId = $categorias->getCollection()
    ->keyBy('id')
    ->map(function ($c) {
        return [
            'nombre' => $c->nombre,
            'codigo' => $c->codigo ?? '',
            'descripcion' => $c->descripcion ?? '',
            'parent_id' => $c->parent_id,
            'color' => $c->color ?? '',
            'icono' => $c->icono ?? '',
            'orden' => $c->orden ?? 0,
            'activo' => $c->activo ? 1 : 0,
        ];
    });
@endphp

@push('scripts')
<script>
const categoriasPadreLista = @json($categoriasPadreLista);
const categoriasPorId = @json($categoriasPorId);
const categoriasBaseUrl = @json(url('/categorias'));

function llenarSelectPadre(selectEl, excluirId, parentIdSeleccionado) {
    selectEl.innerHTML = '';

    const optRaiz = document.createElement('option');
    optRaiz.value = '';
    optRaiz.textContent = 'Sin padre (raíz)';
    if (!parentIdSeleccionado) optRaiz.selected = true;
    selectEl.appendChild(optRaiz);

    const excl = excluirId != null ? String(excluirId) : null;

    categoriasPadreLista.forEach(function (c) {
        if (excl && String(c.id) === excl) return;

        const o = document.createElement('option');
        o.value = c.id;
        o.textContent = c.nombre;

        if (parentIdSeleccionado != null && String(parentIdSeleccionado) === String(c.id)) {
            o.selected = true;
        }

        selectEl.appendChild(o);
    });
}

function abrirModalCrearCategoria() {
    document.getElementById('modalCrearCategoria').classList.add('show');
}

function cerrarModalCrearCategoria() {
    document.getElementById('modalCrearCategoria').classList.remove('show');
}

function abrirModalEditarCategoria(id) {
    const row = categoriasPorId[id];
    if (!row) return;

    document.getElementById('formEditarCategoria').action = categoriasBaseUrl + '/' + id;
    document.getElementById('categoria_edit_id').value = id;

    document.getElementById('edit_nombre').value = row.nombre || '';
    document.getElementById('edit_codigo').value = row.codigo || '';
    document.getElementById('edit_descripcion').value = row.descripcion || '';
    document.getElementById('edit_color').value = row.color || '';
    document.getElementById('edit_icono').value = row.icono || '';
    document.getElementById('edit_orden').value = row.orden != null ? String(row.orden) : '0';

    llenarSelectPadre(
        document.getElementById('edit_parent_id'),
        id,
        row.parent_id || ''
    );

    document.getElementById('edit_activo').checked = row.activo === 1 || row.activo === true;

    document.getElementById('modalEditarCategoria').classList.add('show');
}

function cerrarModalEditarCategoria() {
    document.getElementById('modalEditarCategoria').classList.remove('show');
}

document.getElementById('modalCrearCategoria')?.addEventListener('click', function (e) {
    if (e.target === this) cerrarModalCrearCategoria();
});

document.getElementById('modalEditarCategoria')?.addEventListener('click', function (e) {
    if (e.target === this) cerrarModalEditarCategoria();
});

document.addEventListener('DOMContentLoaded', function () {

    @if($errors->any() && old('_form_context') === 'create')
        abrirModalCrearCategoria();
    @endif

    @if($errors->any() && old('_form_context') === 'edit')
        (function () {
            const id = @json(old('categoria_edit_id'));

            document.getElementById('formEditarCategoria').action = categoriasBaseUrl + '/' + id;
            document.getElementById('categoria_edit_id').value = id;

            llenarSelectPadre(
                document.getElementById('edit_parent_id'),
                id,
                @json(old('parent_id'))
            );

            document.getElementById('edit_activo').checked =
                @json(filter_var(old('activo', false), FILTER_VALIDATE_BOOLEAN));

            document.getElementById('modalEditarCategoria').classList.add('show');
        })();
    @endif

});
</script>
@endpush