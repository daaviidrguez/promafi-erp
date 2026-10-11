@extends('layouts.app')

@section('title', 'Auditoría · Rendimiento')
@section('page-title', '🩺 Auditoría')
@section('page-subtitle', 'Peticiones lentas, peticiones cortadas por PHP y errores de MySQL que pueden saturar el hosting. Solo lectura.')

@php
$breadcrumbs = [
    ['title' => 'Sistema'],
    ['title' => 'Auditoría'],
];
$fmtSeg = fn ($s) => number_format((float) $s, 1).' s';
$fmtMs = fn ($ms) => $ms === null ? '—' : ((float) $ms >= 1000 ? number_format($ms / 1000, 1).' s' : number_format((float) $ms, 0).' ms');
$nombreUsuario = fn ($id) => $id ? ($usuarios[$id] ?? '#'.$id) : '—';
$Semaforo = \App\Support\Rendimiento\RendimientoSemaforo::class;
$Lector = \App\Support\Rendimiento\RendimientoLogLector::class;
$claseTiempo = fn ($s) => (float) $s >= $Semaforo::SEGUNDOS_ROJO ? 'badge-danger' : 'badge-warning';
$colorNivel = ['verde' => 'var(--color-success)', 'amarillo' => 'var(--color-warning)', 'rojo' => 'var(--color-danger)'];
$claseNivel = ['verde' => 'badge-success', 'amarillo' => 'badge-warning', 'rojo' => 'badge-danger'];
$filtrosBase = ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()];
@endphp

@section('content')

@include('auditoria._tabs')

<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <form method="GET" action="{{ route('auditoria.rendimiento') }}" class="filtros-bar" style="flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="desde">Desde</label>
                <input id="desde" type="date" name="desde" class="form-control" value="{{ $desde->toDateString() }}">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label" for="hasta">Hasta</label>
                <input id="hasta" type="date" name="hasta" class="form-control" value="{{ $hasta->toDateString() }}">
            </div>
            <div class="form-group" style="margin:0; flex:1; min-width:220px;">
                <label class="form-label" for="ruta">Ruta (detalle)</label>
                <select id="ruta" name="ruta" class="form-control">
                    <option value="">Todas</option>
                    @foreach ($resumen as $r)
                        <option value="{{ $r['ruta'] }}" @selected($ruta === $r['ruta'])>{{ $r['ruta'] }} ({{ $r['veces'] }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="{{ route('auditoria.rendimiento') }}" class="btn btn-light">Hoy</a>
        </form>
        <p class="form-hint" style="margin:12px 0 0;">
            Se registran las peticiones que tardan <strong>{{ $umbral }} s o más</strong>, las que PHP corta por memoria o tiempo agotado
            y los errores de MySQL por saturación o bloqueo. Varias peticiones lentas en el mismo minuto indican que los procesos PHP
            del hosting se llenaron y el resto de usuarios quedó esperando (causa típica del error 503/504).
            Se conservan {{ $Lector::MAX_DIAS }} días.
        </p>
    </div>
</div>

@if ($truncado)
    <div class="alert alert-warning" style="margin-bottom:16px;">
        Se alcanzó el máximo de {{ number_format($Lector::MAX_ENTRADAS) }} registros leídos; reduce el rango de fechas para ver todo.
    </div>
@endif

<div class="card" style="margin-bottom:16px; border-left:4px solid {{ $colorNivel[$semaforo['nivel']] }};">
    <div class="card-body">
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span class="badge {{ $claseNivel[$semaforo['nivel']] }}" style="font-size:13px;">{{ $Semaforo::etiqueta($semaforo['nivel']) }}</span>
            <strong>Semáforo del periodo</strong>
            @if ($semaforo['prioridad'])
                <span style="font-size:13px;">
                    · Empieza por:
                    <a href="{{ route('auditoria.rendimiento', $filtrosBase + ['ruta' => $semaforo['prioridad']['ruta']]) }}" class="fw-600">{{ $semaforo['prioridad']['ruta'] }}</a>
                    <span class="text-muted">— {{ $semaforo['prioridad']['motivo']['texto'] }}</span>
                </span>
            @endif
        </div>
        @include('auditoria._semaforo', ['semaforo' => $semaforo, 'abierto' => true])
        <details style="margin-top:10px; font-size:12px;">
            <summary class="text-muted" style="cursor:pointer;">¿Cómo se calcula?</summary>
            <ul class="text-muted" style="margin:6px 0 0; padding-left:18px;">
                <li><strong>Petición:</strong> rojo si PHP la cortó, tarda {{ $Semaforo::SEGUNDOS_ROJO }} s o más, hace {{ $Semaforo::CONSULTAS_ROJO }} consultas o más, falla una API externa o responde error 5xx; amarillo si tarda {{ $umbral }} s o más, hace {{ $Semaforo::CONSULTAS_AMARILLO }} consultas o más, tiene consultas de más de 1 s o una API externa lenta.</li>
                <li><strong>Ruta:</strong> el peor color de sus peticiones; además rojo si participó en un minuto con saturación, promedia {{ $Semaforo::PROMEDIO_RUTA_ROJO }} s o más, o se repite {{ $Semaforo::VECES_POR_DIA_RUTA_ROJO }} veces o más por día.</li>
                <li><strong>Periodo:</strong> rojo con cualquier petición cortada por PHP, minuto con saturación o error de MySQL; amarillo con alguna ruta en rojo o {{ $Semaforo::LENTAS_POR_DIA_AMARILLO }} peticiones lentas o más por día.</li>
                <li>Verde significa que no se detectó riesgo en lo registrado: si el servidor web corta la conexión por fuera de PHP (error 504 del hosting), esa petición no alcanza a quedar en este registro.</li>
            </ul>
        </details>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card {{ $totales['peticiones'] > 0 ? 'stat-warning' : 'stat-success' }}">
        <div class="stat-info-box">
            <div class="stat-label">Peticiones lentas</div>
            <div class="stat-value">{{ $totales['peticiones'] }}</div>
            <div class="stat-sub">Más lenta: {{ $fmtSeg($totales['maximo']) }}</div>
        </div>
        <div class="stat-icon">🐢</div>
    </div>
    <div class="stat-card {{ $totales['abortadas'] > 0 ? 'stat-danger' : 'stat-success' }}">
        <div class="stat-info-box">
            <div class="stat-label">Cortadas por PHP</div>
            <div class="stat-value">{{ $totales['abortadas'] }}</div>
            <div class="stat-sub">Memoria o tiempo agotado</div>
        </div>
        <div class="stat-icon">💥</div>
    </div>
    <div class="stat-card {{ count($picos) > 0 ? 'stat-danger' : 'stat-success' }}">
        <div class="stat-info-box">
            <div class="stat-label">Minutos con saturación</div>
            <div class="stat-value">{{ count($picos) }}</div>
            <div class="stat-sub">{{ $Lector::MIN_PETICIONES_PICO }}+ lentas en el mismo minuto</div>
        </div>
        <div class="stat-icon">🔥</div>
    </div>
    <div class="stat-card {{ $totales['errores_mysql'] > 0 ? 'stat-danger' : 'stat-success' }}">
        <div class="stat-info-box">
            <div class="stat-label">Errores MySQL</div>
            <div class="stat-value">{{ $totales['errores_mysql'] }}</div>
            <div class="stat-sub">Saturación o bloqueo</div>
        </div>
        <div class="stat-icon">🗄️</div>
    </div>
    <div class="stat-card {{ $totales['con_http_externo'] > 0 ? 'stat-warning' : 'stat-success' }}">
        <div class="stat-info-box">
            <div class="stat-label">Con API externa lenta</div>
            <div class="stat-value">{{ $totales['con_http_externo'] }}</div>
            <div class="stat-sub">Facturama u otros proveedores</div>
        </div>
        <div class="stat-icon">🌐</div>
    </div>
</div>

@if ($totales['peticiones'] === 0 && $totales['errores_mysql'] === 0)
    <div class="card" style="margin-bottom:16px;">
        <div class="card-body text-center text-muted" style="padding:32px;">
            Sin peticiones lentas ni errores de MySQL en el rango seleccionado.
        </div>
    </div>
@endif

@if ($picos !== [])
    <h3 style="font-size:15px; margin:20px 0 4px;">Minutos con saturación</h3>
    <p class="form-hint" style="margin:0 0 8px;">{{ $Lector::MIN_PETICIONES_PICO }} o más peticiones lentas terminando en el mismo minuto.</p>
    <div class="table-container table-container--scroll" style="margin-bottom:16px;">
        <table>
            <thead>
                <tr>
                    <th>Minuto</th>
                    <th>Peticiones</th>
                    <th>Más lenta</th>
                    <th>Usuarios</th>
                    <th>Rutas</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($picos as $p)
                    <tr>
                        <td style="white-space:nowrap;">{{ $p['minuto']->format('d/m/Y H:i') }}</td>
                        <td><span class="badge badge-warning">{{ $p['peticiones'] }}</span></td>
                        <td>{{ $fmtSeg($p['maximo']) }}</td>
                        <td>{{ $p['usuarios'] }}</td>
                        <td class="text-muted" style="font-size:12px;">{{ implode(', ', $p['rutas']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($resumen !== [])
    <h3 style="font-size:15px; margin:20px 0 4px;">Resumen por ruta</h3>
    <p class="form-hint" style="margin:0 0 8px;">
        Ordenado por tiempo total acumulado: las primeras son las que más ocupan al servidor.
        «% SQL» es la parte del tiempo que se fue en la base de datos; si es baja, el tiempo se fue en PHP, PDF, Excel o API externa (o esperando turno).
    </p>
    <div class="table-container table-container--scroll" style="margin-bottom:16px;">
        <table>
            <thead>
                <tr>
                    <th>Riesgo</th>
                    <th>Ruta</th>
                    <th class="td-right">Veces</th>
                    <th class="td-right">Promedio</th>
                    <th class="td-right">Máximo</th>
                    <th class="td-right">Total</th>
                    <th class="td-right">% SQL</th>
                    <th class="td-right">Usuarios</th>
                    <th>Última</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($resumen as $r)
                    <tr>
                        <td>@include('auditoria._semaforo', ['semaforo' => $r['semaforo']])</td>
                        <td>
                            <a href="{{ route('auditoria.rendimiento', $filtrosBase + ['ruta' => $r['ruta']]) }}" class="fw-600">{{ $r['ruta'] }}</a>
                            <div class="text-muted" style="font-size:12px;">{{ $r['path'] }}</div>
                        </td>
                        <td class="td-right">{{ $r['veces'] }}</td>
                        <td class="td-right">{{ $fmtSeg($r['promedio']) }}</td>
                        <td class="td-right"><span class="badge {{ $claseTiempo($r['maximo']) }}">{{ $fmtSeg($r['maximo']) }}</span></td>
                        <td class="td-right">{{ $fmtSeg($r['total']) }}</td>
                        <td class="td-right">{{ $r['pct_sql'] === null ? '—' : $r['pct_sql'].'%' }}</td>
                        <td class="td-right">{{ $r['usuarios'] }}</td>
                        <td class="text-muted" style="font-size:12px; white-space:nowrap;">{{ $r['ultima']->format('d/m H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($erroresMysql !== [])
    <h3 style="font-size:15px; margin:20px 0 8px;">Errores de MySQL</h3>
    <div class="table-container table-container--scroll" style="margin-bottom:16px;">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Ruta</th>
                    <th>Usuario</th>
                    <th>Mensaje</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($erroresMysql as $e)
                    @php $d = $e['datos']; @endphp
                    <tr>
                        <td style="white-space:nowrap;">{{ $e['fecha']->format('d/m/Y H:i:s') }}</td>
                        <td><span class="badge badge-danger">{{ $d['codigo'] ?? '?' }}</span> {{ $d['tipo'] ?? '' }}</td>
                        <td>{{ $d['route'] ?? ($d['path'] ?? '—') }}</td>
                        <td>{{ $nombreUsuario($d['user_id'] ?? null) }}</td>
                        <td class="text-muted" style="font-size:12px; max-width:480px; word-break:break-word;">{{ $d['mensaje'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($detalle !== [])
    <h3 style="font-size:15px; margin:20px 0 8px;">
        Detalle{{ $ruta !== '' ? ': '.$ruta : '' }}
        @if ($detalleTotal > count($detalle))
            <span class="text-muted" style="font-size:12px; font-weight:400;">(últimas {{ count($detalle) }} de {{ $detalleTotal }})</span>
        @endif
    </h3>
    <div class="table-container table-container--scroll" style="margin-bottom:16px;">
        <table>
            <thead>
                <tr>
                    <th>Riesgo</th>
                    <th>Fecha</th>
                    <th>Tiempo</th>
                    <th>Ruta</th>
                    <th>Usuario</th>
                    <th>SQL</th>
                    <th>Consulta más lenta</th>
                    <th>API externa</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($detalle as $e)
                    @php
                        $d = $e['datos'];
                        $masLenta = is_array($d['sql_mas_lenta'] ?? null) ? $d['sql_mas_lenta'] : null;
                        $externas = is_array($d['http_externas'] ?? null) ? $d['http_externas'] : [];
                    @endphp
                    <tr>
                        <td>@include('auditoria._semaforo', ['semaforo' => $e['semaforo']])</td>
                        <td style="white-space:nowrap;">{{ $e['fecha']->format('d/m H:i:s') }}</td>
                        <td><span class="badge {{ $claseTiempo($d['segundos'] ?? 0) }}">{{ $fmtSeg($d['segundos'] ?? 0) }}</span></td>
                        <td>
                            {{ $d['route'] ?? '—' }}
                            <div class="text-muted" style="font-size:12px;">{{ $d['method'] ?? '' }} {{ $d['path'] ?? '' }} · {{ $d['status'] ?? '' }}</div>
                            @if (! empty($d['error_fatal']))
                                <div style="font-size:12px; color:var(--color-danger); max-width:320px; word-break:break-word;">
                                    {{ $d['error_fatal'] }}
                                    @if (! empty($d['archivo']))<div class="text-muted text-mono">{{ $d['archivo'] }}</div>@endif
                                </div>
                            @endif
                        </td>
                        <td>{{ $nombreUsuario($d['user_id'] ?? null) }}</td>
                        <td style="white-space:nowrap; font-size:12px;">
                            @if (isset($d['sql_consultas']))
                                {{ number_format($d['sql_consultas']) }} consultas
                                <div class="text-muted">{{ $fmtMs($d['sql_total_ms'] ?? null) }} total</div>
                                @if (($d['sql_lentas'] ?? 0) > 0)
                                    <div><span class="badge badge-warning">{{ $d['sql_lentas'] }} de más de 1 s</span></div>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td style="font-size:12px; max-width:420px;">
                            @if ($masLenta)
                                <details>
                                    <summary style="cursor:pointer;"><strong>{{ $fmtMs($masLenta['ms'] ?? null) }}</strong>@if (! empty($masLenta['origen'])) · <span class="text-muted text-mono">{{ $masLenta['origen'] }}</span>@endif</summary>
                                    <code style="display:block; white-space:pre-wrap; word-break:break-word; margin-top:4px;">{{ $masLenta['sql'] ?? '' }}</code>
                                </details>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td style="font-size:12px;">
                            @forelse ($externas as $x)
                                <div>
                                    {{ $x['metodo'] ?? '' }} {{ $x['host'] ?? '' }}:
                                    @if (! empty($x['error']))
                                        <span class="badge badge-danger" title="{{ $x['error'] }}">falló</span>
                                    @else
                                        {{ $fmtMs($x['ms'] ?? null) }}
                                    @endif
                                </div>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@endsection
