@extends('layouts.app')
@section('title', 'Catálogo Truper')
@section('page-title', '🔧 Catálogo Truper')
@section('page-subtitle', 'Productos Truper: costo (distribuidor) y venta (medio mayoreo) sin IVA')
@section('page-actions')
    @can('catalogo_truper.importar')
    <button type="button" class="btn btn-primary" onclick="toggleImportarTruper()">📥 Importar</button>
    @endcan
@endsection

@php
    $breadcrumbs = [['title' => 'Catálogo Truper']];
@endphp

@section('content')

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
@endif

@can('catalogo_truper.importar')
<div id="formImportar" class="card" style="display:none; margin-bottom:16px;">
    <div class="card-body">
        <p class="text-muted" style="margin:0 0 12px; font-size:13px;">
            Excel con columnas: <strong>código</strong>, <strong>clave</strong>, <strong>descripcion</strong>, <strong>unidad</strong>,
            <strong>COSTO: precio distribuidor sin IVA</strong>, <strong>VENTA: Precio Medio Mayoreo sin IVA</strong>,
            <strong>Codigo SAT</strong>, <strong>Peso[Kg]</strong>, <strong>Volumen[cm3]</strong>.
            Se sube en bloques de 500 y verás el avance en pantalla.
        </p>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <input type="file" id="truperExcelInput" accept=".xlsx,.xls,.csv" class="form-control" style="max-width:360px;">
            <button type="button" id="truperImportBtn" class="btn btn-primary" onclick="iniciarImportTruper()">Subir e importar</button>
        </div>
        <div id="truperImportError" class="alert alert-danger" style="display:none;margin-top:12px;"></div>
    </div>
</div>

<div id="truperProgressOverlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:28px 32px; width:min(440px, 92vw); box-shadow:0 20px 50px rgba(0,0,0,.25);">
        <div style="font-size:17px; font-weight:700; margin-bottom:6px;">Importando catálogo Truper</div>
        <div id="truperProgressLabel" class="text-muted" style="font-size:13px; margin-bottom:14px;">Preparando archivo…</div>
        <div style="height:12px; background:#e5e7eb; border-radius:999px; overflow:hidden;">
            <div id="truperProgressBar" style="height:100%; width:0%; background:var(--color-primary, #1e3a5f); transition:width .2s ease;"></div>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:10px; font-size:13px; font-variant-numeric:tabular-nums;">
            <span id="truperProgressCount">0 / 0</span>
            <span id="truperProgressPct">0%</span>
        </div>
        <div id="truperProgressStats" class="text-muted" style="font-size:12px; margin-top:10px;">No cierres ni cambies de página.</div>
    </div>
</div>
@endcan

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('catalogo-truper.index') }}" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar por código, clave o descripción (mín. 3 caracteres)..." class="form-control" style="min-width:280px;">
            <button type="submit" class="btn btn-primary">🔍 Buscar</button>
            @if($search ?? false)
            <a href="{{ route('catalogo-truper.index') }}" class="btn btn-light">✕ Limpiar</a>
            @endif
        </form>
    </div>
</div>

<div class="table-container">
    @if($items->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Clave</th>
                <th>Descripción</th>
                <th class="td-center">Unidad</th>
                <th class="td-right">COSTO</th>
                <th class="td-right">VENTA</th>
                <th>Código SAT</th>
                <th class="td-right">Peso [Kg]</th>
                <th class="td-right">Volumen [cm³]</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td class="text-mono">{{ $item->codigo }}</td>
                <td class="text-mono">{{ $item->clave ?? '—' }}</td>
                <td><div class="fw-600 text-primary" style="font-size:13.5px;">{{ Str::limit($item->descripcion, 60) }}</div></td>
                <td class="td-center">{{ $item->unidad }}</td>
                <td class="td-right text-mono">${{ number_format($item->costo, 2) }}</td>
                <td class="td-right text-mono">${{ number_format($item->venta, 2) }}</td>
                <td class="text-mono">{{ $item->codigo_sat ?? '—' }}</td>
                <td class="td-right text-mono">{{ $item->peso_kg !== null ? number_format($item->peso_kg, 3) : '—' }}</td>
                <td class="td-right text-mono">{{ $item->volumen_cm3 !== null ? number_format($item->volumen_cm3, 2) : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px 20px; border-top:1px solid var(--color-gray-100);">{{ $items->withQueryString()->links() }}</div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">🔧</div>
        <div class="empty-state-title">No hay productos en el catálogo Truper</div>
        <div class="empty-state-text">Importa un Excel con las columnas del catálogo Truper. Las reimportaciones actualizarán costo y precio si el código ya existe.</div>
        @can('catalogo_truper.importar')
        <button type="button" class="btn btn-primary" style="margin-top:16px;" onclick="toggleImportarTruper(true)">📥 Importar Excel</button>
        @endcan
    </div>
    @endif
</div>

@endsection

@can('catalogo_truper.importar')
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
(function () {
    const LOTE = 500;
    const URL_LOTE = @json(route('catalogo-truper.importar-lote'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    window.toggleImportarTruper = function (forceShow) {
        const el = document.getElementById('formImportar');
        if (!el) return;
        if (forceShow === true) {
            el.style.display = 'block';
            return;
        }
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    };

    function normKey(k) {
        return String(k || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]/g, '');
    }

    function pick(row, keys, opts) {
        const prefix = opts && opts.prefix;
        const map = {};
        Object.keys(row || {}).forEach(function (k) { map[normKey(k)] = row[k]; });
        for (let i = 0; i < keys.length; i++) {
            const t = normKey(keys[i]);
            if (map[t] !== undefined && map[t] !== null && map[t] !== '') return map[t];
        }
        if (prefix) {
            for (let i = 0; i < keys.length; i++) {
                const t = normKey(keys[i]);
                const entries = Object.entries(map);
                for (let j = 0; j < entries.length; j++) {
                    const k = entries[j][0];
                    const v = entries[j][1];
                    if ((k === t || k.indexOf(t) === 0) && v !== undefined && v !== null && v !== '') return v;
                }
            }
        }
        return '';
    }

    function toNum(v) {
        if (v === null || v === undefined || v === '') return 0;
        if (typeof v === 'number') return v;
        const s = String(v).replace(/[$,\s]/g, '');
        const n = parseFloat(s);
        return Number.isFinite(n) ? n : 0;
    }

    function toNumOrNull(v) {
        if (v === null || v === undefined || v === '') return null;
        return toNum(v);
    }

    function toCodigo(v) {
        if (v === null || v === undefined || v === '') return '';
        if (typeof v === 'number') return String(Math.trunc(v));
        return String(v).trim();
    }

    function mapRow(row) {
        return {
            codigo: toCodigo(pick(row, ['codigo', 'code'])),
            clave: String(pick(row, ['clave']) || '').trim(),
            descripcion: String(pick(row, ['descripcion', 'description']) || '').trim(),
            unidad: String(pick(row, ['unidad']) || '').trim() || 'PZA',
            costo: toNum(pick(row, ['costo'], { prefix: true })),
            venta: toNum(pick(row, ['venta'], { prefix: true })),
            codigo_sat: toCodigo(pick(row, ['codigosat', 'clavesat', 'codigo_sat'])),
            peso_kg: toNumOrNull(pick(row, ['pesokg', 'peso'], { prefix: true })),
            volumen_cm3: toNumOrNull(pick(row, ['volumencm3', 'volumen'], { prefix: true })),
        };
    }

    function showError(msg) {
        const el = document.getElementById('truperImportError');
        if (!el) return;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function setProgress(done, total, stats) {
        const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        const overlay = document.getElementById('truperProgressOverlay');
        const bar = document.getElementById('truperProgressBar');
        if (overlay) overlay.style.display = 'flex';
        if (bar) bar.style.width = pct + '%';
        document.getElementById('truperProgressCount').textContent = done.toLocaleString() + ' / ' + total.toLocaleString();
        document.getElementById('truperProgressPct').textContent = pct + '%';
        document.getElementById('truperProgressLabel').textContent = done < total
            ? 'Procesando bloque… no cierres ni cambies de página.'
            : 'Importación terminada.';
        if (stats) {
            document.getElementById('truperProgressStats').textContent =
                'Creados: ' + stats.creados.toLocaleString()
                + ' · Actualizados: ' + stats.actualizados.toLocaleString()
                + ' · Omitidos: ' + stats.omitidos.toLocaleString();
        }
    }

    async function enviarLote(items) {
        const res = await fetch(URL_LOTE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ items: items }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
            const msg = data.message
                || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                || ('Error HTTP ' + res.status);
            throw new Error(msg);
        }
        return data;
    }

    window.iniciarImportTruper = async function () {
        const input = document.getElementById('truperExcelInput');
        const btn = document.getElementById('truperImportBtn');
        const err = document.getElementById('truperImportError');
        if (err) err.style.display = 'none';

        if (!input || !input.files || !input.files.length) {
            showError('Selecciona un archivo Excel.');
            return;
        }
        if (typeof XLSX === 'undefined') {
            showError('No se pudo cargar la librería Excel. Recarga la página.');
            return;
        }

        btn.disabled = true;
        window.__truperImporting = true;
        window.onbeforeunload = function () {
            return window.__truperImporting ? 'La importación está en curso. Si sales se cancelará.' : undefined;
        };
        setProgress(0, 0, { creados: 0, actualizados: 0, omitidos: 0 });
        document.getElementById('truperProgressLabel').textContent = 'Leyendo Excel…';

        try {
            const buf = await input.files[0].arrayBuffer();
            const wb = XLSX.read(buf, { type: 'array' });
            const sheet = wb.Sheets[wb.SheetNames[0]];
            const rawRows = XLSX.utils.sheet_to_json(sheet, { defval: '' });
            if (!rawRows.length) throw new Error('El Excel no tiene filas de datos.');

            const items = rawRows.map(mapRow).filter(function (r) { return r.codigo || r.descripcion; });
            const total = items.length;
            let done = 0;
            const stats = { creados: 0, actualizados: 0, omitidos: 0 };
            setProgress(0, total, stats);

            for (let i = 0; i < total; i += LOTE) {
                const lote = items.slice(i, i + LOTE);
                const result = await enviarLote(lote);
                done += lote.length;
                stats.creados += result.creados || 0;
                stats.actualizados += result.actualizados || 0;
                stats.omitidos += result.omitidos || 0;
                setProgress(done, total, stats);
            }

            window.__truperImporting = false;
            window.onbeforeunload = null;
            window.location.href = @json(route('catalogo-truper.index'))
                + '?imported=1&c=' + stats.creados + '&a=' + stats.actualizados + '&o=' + stats.omitidos;
        } catch (e) {
            console.error(e);
            window.__truperImporting = false;
            window.onbeforeunload = null;
            document.getElementById('truperProgressOverlay').style.display = 'none';
            showError(e.message || 'Error al importar.');
            btn.disabled = false;
        }
    };
})();
</script>
@endpush
@endcan
