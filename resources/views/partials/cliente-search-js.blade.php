@once('cliente-search-js')
<script>
window.ClienteSearch = window.ClienteSearch || (function () {
    const searchUrl = @json(route('clientes.buscar'));
    const timers = {};

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function closeResults(resultsId) {
        const el = document.getElementById(resultsId);
        if (el) el.classList.remove('show');
    }

    function applySelection(c, opts) {
        const hidden = document.getElementById(opts.hiddenId);
        const input = document.getElementById(opts.inputId);
        if (hidden) hidden.value = c.id ?? '';
        if (input) input.value = c.nombre ?? '';
        closeResults(opts.resultsId);
        if (typeof opts.onSelect === 'function') opts.onSelect(c);
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
                box.innerHTML = data.map(c => `
                    <div class="autocomplete-item">
                        <div class="autocomplete-item-name">${escapeHtml(c.nombre)}</div>
                        <div class="autocomplete-item-sub">${escapeHtml(c.rfc || '')}${c.codigo ? ' · ' + escapeHtml(c.codigo) : ''}</div>
                    </div>
                `).join('');
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
            inputId: 'buscarCliente',
            hiddenId: 'cliente_id',
            resultsId: 'clienteResults',
            minChars: 2,
            debounce: 280,
            allowEmpty: false,
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
            if (input) input.value = opts.initial.nombre ?? '';
            if (opts.applyInitial !== false && typeof opts.onSelect === 'function') {
                opts.onSelect(opts.initial);
            }
        }
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.cliente-search-box') && !e.target.closest('.cliente-search-results')) {
            document.querySelectorAll('.cliente-search-results.show').forEach(el => el.classList.remove('show'));
        }
    });

    return { init, clear: clearSelection, apply: applySelection, escapeHtml, closeResults };
})();
</script>
@endonce
