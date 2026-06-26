@php
    $inputId = $inputId ?? 'buscarProducto';
    $hiddenId = $hiddenId ?? 'producto_id';
    $hiddenName = $hiddenName ?? 'producto_id';
    $resultsId = $resultsId ?? 'productoResults';
    $label = $label ?? 'Producto';
    $showLabel = $showLabel ?? true;
    $required = $required ?? true;
    $allowEmpty = $allowEmpty ?? ! $required;
    $compact = $compact ?? false;
    $showStock = $showStock ?? false;
    $placeholder = $placeholder ?? 'Buscar producto por código o nombre...';
    $productoIdValue = (string) ($productoIdValue ?? old($hiddenName, $productoId ?? ''));
    $productoNombreValue = $productoNombreValue ?? ($productoNombre ?? '');

    if ($productoIdValue !== '' && $productoNombreValue === '' && ! empty($productos)) {
        $match = collect($productos)->firstWhere('id', (int) $productoIdValue);
        if ($match) {
            $productoNombreValue = $match->codigo . ' — ' . $match->nombre;
            if ($showStock && isset($match->stock)) {
                $productoNombreValue .= ' (stock: ' . number_format((float) $match->stock, 2) . ')';
            }
        }
    }

    $wrapperClass = 'search-box producto-search-box' . ($compact ? '' : ' form-group');
    $inputStyle = $compact
        ? 'width: auto; min-width: 220px; max-width: min(320px, 100vw);'
        : '';
@endphp

<div class="{{ $wrapperClass }}">
    @if($showLabel && $label !== '')
        <label class="form-label" for="{{ $inputId }}">
            {{ $label }} @if($required)<span class="req">*</span>@endif
        </label>
    @endif
    <input type="text"
           id="{{ $inputId }}"
           value="{{ $productoNombreValue }}"
           placeholder="{{ $placeholder }}"
           autocomplete="off"
           class="form-control"
           @if($inputStyle !== '') style="{{ $inputStyle }}" @endif>
    <input type="hidden"
           name="{{ $hiddenName }}"
           id="{{ $hiddenId }}"
           value="{{ $productoIdValue }}"
           @if($required) required @endif>
    <div id="{{ $resultsId }}" class="autocomplete-results producto-search-results"></div>
</div>
