@once('producto-search-js')
<script>
window.ProductoSearch = window.ProductoSearch || (function () {
    const searchUrl = @json(route('inventario.buscar-productos'));
    const timers = {};

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatLabel(p, showStock) {
        let label = (p.codigo || '') + ' — ' + (p.nombre || '');
        if (showStock && p.stock != null) {
            label += ' (stock: ' + parseFloat(p.stock).toFixed(2) + ')';
        }
        return label;
    }

    function closeResults(resultsId) {
        const el = document.getElementById(resultsId);
        if (el) el.classList.remove('show');
    }

    function applySelection(p, opts) {
        const hidden = document.getElementById(opts.hiddenId);
        const input = document.getElementById(opts.inputId);
        if (hidden) hidden.value = p.id ?? '';
        if (input) input.value = formatLabel(p, opts.showStock);
        closeResults(opts.resultsId);
        if (typeof opts.onSelect === 'function') opts.onSelect(p);
    }

    function clearSelection(opts) {
        const hidden = document.getElementById(opts.hiddenId);
        const input = document.getElementById(opts.inputId);
        if (hidden) hidden.value = '';
        if (input) input.value = '';
        closeResults(opts.resultsId);
        if (typeof opts.onClear === 'function') opts.onClear();
    }

    async function search(q, opts) {
        const box = document.getElementById(opts.resultsId);
        if (!box) return;

        try {
            const url = (opts.url || searchUrl) + '?q=' + encodeURIComponent(q);
            const r = await fetch(url);
            const data = await r.json();
            if (!Array.isArray(data) || !data.length) {
                box.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
            } else {
                box.innerHTML = data.map(p => {
                    const sub = escapeHtml(p.codigo || '') + (opts.showStock && p.stock != null
                        ? ' — stock: ' + parseFloat(p.stock).toFixed(2)
                        : '');
                    return `
                        <div class="autocomplete-item">
                            <div class="autocomplete-item-name">${escapeHtml(p.nombre)}</div>
                            <div class="autocomplete-item-sub">${sub}</div>
                        </div>
                    `;
                }).join('');
                box.querySelectorAll('.autocomplete-item').forEach((item, idx) => {
                    if (!data[idx]) return;
                    item.addEventListener('click', function () {
                        applySelection(data[idx], opts);
                    });
                });
            }
            box.classList.add('show');
        } catch (e) {
            console.error(e);
        }
    }

    function init(opts) {
        opts = Object.assign({
            inputId: 'buscarProducto',
            hiddenId: 'producto_id',
            resultsId: 'productoResults',
            minChars: 2,
            debounce: 280,
            allowEmpty: false,
            showStock: false,
        }, opts);

        const input = document.getElementById(opts.inputId);
        if (!input) return;

        input.addEventListener('input', function () {
            clearTimeout(timers[opts.inputId]);
            const q = this.value.trim();
            if (q.length === 0 && opts.allowEmpty) {
                clearSelection(opts);
                return;
            }
            if (q.length < opts.minChars) {
                closeResults(opts.resultsId);
                return;
            }
            timers[opts.inputId] = setTimeout(() => search(q, opts), opts.debounce);
        });

        if (opts.initial && opts.initial.id) {
            const hidden = document.getElementById(opts.hiddenId);
            const input = document.getElementById(opts.inputId);
            if (hidden) hidden.value = opts.initial.id ?? '';
            if (input) input.value = formatLabel(opts.initial, opts.showStock);
            if (opts.applyInitial !== false && typeof opts.onSelect === 'function') {
                opts.onSelect(opts.initial);
            }
        }
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.producto-search-box') && !e.target.closest('.producto-search-results')) {
            document.querySelectorAll('.producto-search-results.show').forEach(el => el.classList.remove('show'));
        }
    });

    return { init, clear: clearSelection, apply: applySelection, escapeHtml, closeResults, formatLabel };
})();
</script>
@endonce
