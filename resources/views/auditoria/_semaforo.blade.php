{{-- $semaforo: resultado de RendimientoSemaforo; $abierto: lista visible (true) o plegada bajo la insignia (false). --}}
@php
    $abierto = $abierto ?? false;
    $clases = ['verde' => 'badge-success', 'amarillo' => 'badge-warning', 'rojo' => 'badge-danger'];
    $colores = ['verde' => 'var(--color-success)', 'amarillo' => 'var(--color-warning)', 'rojo' => 'var(--color-danger)'];
    $etiqueta = \App\Support\Rendimiento\RendimientoSemaforo::etiqueta($semaforo['nivel']);
@endphp

@if ($abierto)
    @if ($semaforo['motivos'] === [])
        <p class="text-muted" style="margin:8px 0 0; font-size:13px;">No se detectó riesgo en lo registrado para este periodo.</p>
    @else
        <ul style="margin:10px 0 0; padding-left:0; list-style:none; font-size:13px;">
            @foreach ($semaforo['motivos'] as $m)
                <li style="margin-bottom:6px; padding-left:14px; position:relative;">
                    <span style="position:absolute; left:0; top:1px; color:{{ $colores[$m['nivel']] }};" aria-hidden="true">●</span>
                    <strong>{{ $m['texto'] }}</strong>
                    <div class="text-muted">{{ $m['accion'] }}</div>
                </li>
            @endforeach
        </ul>
    @endif
@elseif ($semaforo['motivos'] === [])
    <span class="badge {{ $clases[$semaforo['nivel']] }}">{{ $etiqueta }}</span>
@else
    <details style="font-size:12px; min-width:170px; max-width:340px;">
        <summary style="cursor:pointer;"><span class="badge {{ $clases[$semaforo['nivel']] }}">{{ $etiqueta }}</span></summary>
        <ul style="margin:6px 0 0; padding-left:0; list-style:none;">
            @foreach ($semaforo['motivos'] as $m)
                <li style="margin-bottom:6px; padding-left:12px; position:relative;">
                    <span style="position:absolute; left:0; top:1px; color:{{ $colores[$m['nivel']] }};" aria-hidden="true">●</span>
                    <strong>{{ $m['texto'] }}</strong>
                    <div class="text-muted">{{ $m['accion'] }}</div>
                </li>
            @endforeach
        </ul>
    </details>
@endif
