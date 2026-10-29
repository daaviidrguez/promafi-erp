{{-- Botón de retorno contextual (solo si ?from= es un origen allowlisted) --}}
@php
    $origenVolver = docShowVolverOrigen();
@endphp
@if($origenVolver)
<a href="{{ $origenVolver['url'] }}" class="btn btn-light">{{ $origenVolver['label'] }}</a>
@endif
