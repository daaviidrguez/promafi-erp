{{-- Modales de borrador local (vanilla JS) --}}
<div id="promafiAutosaveModalRestore" class="modal" aria-hidden="true">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div class="modal-title">¿Restaurar borrador local?</div>
            <button type="button" class="modal-close" data-promafi-autosave="postpone-restore" aria-label="Cerrar">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin: 0; line-height: 1.6;">
                Hay un borrador sin guardar en este equipo (por ejemplo, si se cerró el navegador o se fue la luz).
                Puede recuperarlo o empezar de nuevo. Está guardado solo en este navegador; no necesita internet para restaurarlo.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" data-promafi-autosave="discard">Descartar</button>
            <button type="button" class="btn btn-primary" data-promafi-autosave="restore">Restaurar</button>
        </div>
    </div>
</div>

<div id="promafiAutosaveModalRedirect" class="modal" aria-hidden="true">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div class="modal-title">Continuar en editar</div>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin: 0; line-height: 1.6;">
                Hay un borrador local de
                <strong id="promafiAutosaveRedirectFolio">un documento ya guardado</strong>.
                Para no crear un duplicado, continúe en la pantalla de editar.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" data-promafi-autosave="discard-redirect">Descartar y crear nueva</button>
            <button type="button" class="btn btn-primary" data-promafi-autosave="go-edit">Ir a editar</button>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.PromafiFormAutosave) return;

    function $(sel, root) {
        if (!sel) return null;
        if (typeof sel === 'string') {
            return (root || document).querySelector(sel);
        }
        return sel;
    }

    function readStorage(key) {
        if (!key) return null;
        try {
            const raw = localStorage.getItem(key);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || parsed.v !== 1 || !parsed.data) return null;
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function formatStatusText(savedAt) {
        if (!savedAt) return '';
        const secs = Math.max(0, Math.round((Date.now() - savedAt) / 1000));
        if (secs < 5) return 'Borrador local guardado hace un momento';
        if (secs < 60) return 'Borrador local guardado hace ' + secs + ' s';
        if (secs < 3600) return 'Borrador local guardado hace ' + Math.round(secs / 60) + ' min';
        return 'Borrador local guardado hace ' + Math.round(secs / 3600) + ' h';
    }

    window.PromafiFormAutosave = {
        create: function (opts) {
            opts = opts || {};
            const form = $(opts.form);
            const statusEl = $(opts.statusEl);
            const modalRestore = document.getElementById('promafiAutosaveModalRestore');
            const modalRedirect = document.getElementById('promafiAutosaveModalRedirect');
            const redirectFolioEl = document.getElementById('promafiAutosaveRedirectFolio');

            const inst = {
                key: opts.key || '',
                pointerKey: opts.pointerKey || '',
                modo: opts.modo || 'create',
                editUrl: opts.editUrl || '',
                folio: opts.folio || '',
                entityId: opts.entityId ?? null,
                entityIdField: opts.entityIdField || 'entityId',
                serialize: typeof opts.serialize === 'function' ? opts.serialize : function () { return {}; },
                hasContent: typeof opts.hasContent === 'function' ? opts.hasContent : function () { return false; },
                restore: typeof opts.restore === 'function' ? opts.restore : function () {},
                _closed: false,
                _omit: false,
                _pending: null,
                _savedAt: null,
                _timer: null,
                _tickTimer: null,
                _redirectUrl: '',
                _redirectFolio: '',
                _modalRestoreOpen: false,
                _modalRedirectOpen: false,
                _available: false,
            };

            function guardActive() {
                return inst._closed || inst._omit || inst._modalRestoreOpen || inst._modalRedirectOpen || inst._available;
            }

            function showModal(el) {
                if (!el) return;
                el.classList.add('show');
                el.setAttribute('aria-hidden', 'false');
            }

            function hideModal(el) {
                if (!el) return;
                el.classList.remove('show');
                el.setAttribute('aria-hidden', 'true');
            }

            function updateStatusUi() {
                if (!statusEl) return;
                if (inst._available && inst._pending) {
                    statusEl.innerHTML = 'Hay un borrador local disponible. '
                        + '<button type="button" class="promafi-autosave-status__link" data-promafi-autosave-instance="restore">Restaurar</button>'
                        + ' · '
                        + '<button type="button" class="promafi-autosave-status__link" data-promafi-autosave-instance="discard-inline">Descartar</button>';
                    statusEl.style.display = '';
                    statusEl.querySelectorAll('[data-promafi-autosave-instance]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            if (btn.getAttribute('data-promafi-autosave-instance') === 'restore') {
                                inst.restoreDraft();
                            } else {
                                inst.discard();
                            }
                        });
                    });
                    return;
                }
                const text = formatStatusText(inst._savedAt);
                statusEl.textContent = text;
                statusEl.style.display = text ? '' : 'none';
            }

            function readPointer() {
                if (!inst.pointerKey) return null;
                try {
                    const raw = localStorage.getItem(inst.pointerKey);
                    if (!raw) return null;
                    const parsed = JSON.parse(raw);
                    if (!parsed || !parsed.tipo || !parsed.key) return null;
                    return parsed;
                } catch (e) {
                    return null;
                }
            }

            function writePointer(extra) {
                if (!inst.pointerKey || !inst.key) return;
                try {
                    const payload = {
                        tipo: inst.modo,
                        key: inst.key,
                        editUrl: inst.editUrl || '',
                        folio: inst.folio || '',
                        savedAt: Date.now(),
                    };
                    payload[inst.entityIdField] = inst.entityId || null;
                    if (extra && typeof extra === 'object') {
                        Object.assign(payload, extra);
                    }
                    localStorage.setItem(inst.pointerKey, JSON.stringify(payload));
                } catch (e) {}
            }

            function clearPointerIfMatch() {
                if (!inst.pointerKey) return;
                try {
                    const pointer = readPointer();
                    if (pointer && pointer.key === inst.key) {
                        localStorage.removeItem(inst.pointerKey);
                    }
                } catch (e) {}
            }

            function persist() {
                if (guardActive() || !inst.key) return;
                const data = inst.serialize();
                if (!inst.hasContent(data)) return;
                const payload = { v: 1, savedAt: Date.now(), data: data };
                try {
                    localStorage.setItem(inst.key, JSON.stringify(payload));
                    writePointer({ savedAt: payload.savedAt, folio: inst.folio || (data.contexto && data.contexto.folio) || '' });
                    inst._savedAt = payload.savedAt;
                    inst._available = false;
                    inst._pending = null;
                    updateStatusUi();
                } catch (e) {}
            }

            function schedule() {
                if (guardActive() || !inst.key) return;
                clearTimeout(inst._timer);
                inst._timer = setTimeout(persist, 700);
            }

            function flush() {
                if (guardActive() || !inst.key) return;
                clearTimeout(inst._timer);
                persist();
            }

            inst.schedule = schedule;
            inst.flush = flush;

            inst.close = function () {
                inst._closed = true;
                clearTimeout(inst._timer);
                if (inst.key) {
                    try { localStorage.removeItem(inst.key); } catch (e) {}
                }
                clearPointerIfMatch();
                inst._savedAt = null;
                inst._available = false;
                inst._pending = null;
                updateStatusUi();
            };

            inst.discard = function () {
                inst.close();
                inst._closed = false;
                hideModal(modalRestore);
                inst._modalRestoreOpen = false;
                inst._pending = null;
                inst._available = false;
                updateStatusUi();
            };

            inst.postponeRestore = function () {
                hideModal(modalRestore);
                inst._modalRestoreOpen = false;
                inst._available = !!inst._pending;
                updateStatusUi();
            };

            inst.restoreDraft = function () {
                const pendiente = inst._pending;
                if (!pendiente || !pendiente.data) {
                    hideModal(modalRestore);
                    inst._modalRestoreOpen = false;
                    return;
                }
                inst._omit = true;
                hideModal(modalRestore);
                inst._modalRestoreOpen = false;
                try {
                    inst.restore(pendiente.data);
                } catch (e) {
                    console.error(e);
                }
                inst._savedAt = pendiente.savedAt || Date.now();
                inst._pending = null;
                inst._available = false;
                updateStatusUi();
                setTimeout(function () {
                    inst._omit = false;
                    persist();
                }, 0);
            };

            inst.goToEdit = function () {
                if (!inst._redirectUrl) {
                    hideModal(modalRedirect);
                    inst._modalRedirectOpen = false;
                    return;
                }
                inst._closed = true;
                window.location.href = inst._redirectUrl;
            };

            inst.discardRedirect = function () {
                const pointer = readPointer();
                if (pointer && pointer.key) {
                    try { localStorage.removeItem(pointer.key); } catch (e) {}
                }
                if (inst.pointerKey) {
                    try { localStorage.removeItem(inst.pointerKey); } catch (e) {}
                }
                hideModal(modalRedirect);
                inst._modalRedirectOpen = false;
                inst._redirectUrl = '';
                inst._redirectFolio = '';

                const pendiente = readStorage(inst.key);
                if (pendiente && inst.hasContent(pendiente.data)) {
                    inst._pending = pendiente;
                    inst._modalRestoreOpen = true;
                    showModal(modalRestore);
                }
            };

            function shouldRedirectByPointer() {
                const pointer = readPointer();
                if (!pointer || pointer.tipo !== 'edit' || !pointer.editUrl || !pointer.key) return false;
                const draft = readStorage(pointer.key);
                if (!draft || !inst.hasContent(draft.data)) return false;
                inst._redirectUrl = pointer.editUrl;
                inst._redirectFolio = pointer.folio || (draft.data.contexto && draft.data.contexto.folio) || '';
                if (redirectFolioEl) {
                    redirectFolioEl.textContent = inst._redirectFolio || 'un documento ya guardado';
                }
                inst._modalRedirectOpen = true;
                showModal(modalRedirect);
                return true;
            }

            function bindFormListeners() {
                if (!form) return;
                form.addEventListener('input', schedule);
                form.addEventListener('change', schedule);
            }

            function bindGlobalActions() {
                document.addEventListener('click', function (e) {
                    const action = e.target.closest('[data-promafi-autosave]');
                    if (!action) return;
                    const type = action.getAttribute('data-promafi-autosave');
                    if (type === 'restore') inst.restoreDraft();
                    else if (type === 'discard') inst.discard();
                    else if (type === 'postpone-restore') inst.postponeRestore();
                    else if (type === 'go-edit') inst.goToEdit();
                    else if (type === 'discard-redirect') inst.discardRedirect();
                });
            }

            inst._init = function () {
                inst._closed = false;
                inst._omit = false;
                bindFormListeners();
                bindGlobalActions();

                window.addEventListener('beforeunload', flush);
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'hidden') flush();
                });

                inst._tickTimer = setInterval(updateStatusUi, 10000);

                if (opts.skipRestore || !inst.key) return;

                if (inst.modo === 'create' && shouldRedirectByPointer()) return;

                const pendiente = readStorage(inst.key);
                if (pendiente && inst.hasContent(pendiente.data)) {
                    const ctx = pendiente.data.contexto || {};
                    if (inst.modo === 'create' && ctx.tipo === 'edit' && ctx.editUrl) {
                        inst._redirectUrl = ctx.editUrl;
                        inst._redirectFolio = ctx.folio || '';
                        if (redirectFolioEl) {
                            redirectFolioEl.textContent = inst._redirectFolio || 'un documento ya guardado';
                        }
                        inst._modalRedirectOpen = true;
                        showModal(modalRedirect);
                        return;
                    }
                    inst._pending = pendiente;
                    inst._modalRestoreOpen = true;
                    showModal(modalRestore);
                }
            };

            inst._init();
            return inst;
        },

        limpiarClaves: function (keys) {
            (keys || []).forEach(function (key) {
                if (!key) return;
                try { localStorage.removeItem(key); } catch (e) {}
            });
        },
    };
})();
</script>
