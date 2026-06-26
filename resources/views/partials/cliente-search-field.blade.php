@php
    $inputId = $inputId ?? 'buscarCliente';
    $hiddenId = $hiddenId ?? 'cliente_id';
    $hiddenName = $hiddenName ?? 'cliente_id';
    $resultsId = $resultsId ?? 'clienteResults';
    $label = $label ?? 'Cliente';
    $showLabel = $showLabel ?? true;
    $required = $required ?? true;
    $allowEmpty = $allowEmpty ?? ! $required;
    $compact = $compact ?? false;
    $placeholder = $placeholder ?? 'Buscar cliente por nombre, RFC o código...';
    $clienteIdValue = (string) ($clienteIdValue ?? old($hiddenName, $clienteId ?? ''));
    $clienteNombreValue = $clienteNombreValue ?? ($clienteNombre ?? '');

    if ($clienteIdValue !== '' && $clienteNombreValue === '' && ! empty($clientes)) {
        $match = collect($clientes)->firstWhere('id', (int) $clienteIdValue);
        $clienteNombreValue = $match->nombre ?? '';
    }

    $wrapperClass = 'search-box cliente-search-box' . ($compact ? '' : ' form-group');
    $inputStyle = $compact
        ? 'width: auto; min-width: 200px; max-width: min(280px, 100vw);'
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
           value="{{ $clienteNombreValue }}"
           placeholder="{{ $placeholder }}"
           autocomplete="off"
           class="form-control"
           @if($inputStyle !== '') style="{{ $inputStyle }}" @endif>
    <input type="hidden"
           name="{{ $hiddenName }}"
           id="{{ $hiddenId }}"
           value="{{ $clienteIdValue }}"
           @if($required) required @endif>
    <div id="{{ $resultsId }}" class="autocomplete-results cliente-search-results"></div>
</div>
