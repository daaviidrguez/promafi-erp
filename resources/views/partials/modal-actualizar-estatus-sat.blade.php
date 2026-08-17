{{-- Modal confirmar consulta de estatus de cancelación ante SAT/PAC --}}
<div id="modalActualizarEstatusSat" class="modal" onclick="if(event.target===this)cerrarModalActualizarEstatusSat()">
    <div class="modal-box" style="max-width: 480px;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title">Consultar estatus SAT</div>
            <button type="button" class="modal-close" onclick="cerrarModalActualizarEstatusSat()" aria-label="Cerrar">✕</button>
        </div>
        <form id="formActualizarEstatusSat" method="POST" action="">
            @csrf
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom: 0;" id="modalActualizarEstatusSatMensaje">
                    ¿Consultar la respuesta actual del SAT?
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="cerrarModalActualizarEstatusSat()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnConfirmarActualizarEstatusSat">Consultar SAT</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function() {
    if (window.__promafiActualizarEstatusSatInit) {
        return;
    }
    window.__promafiActualizarEstatusSatInit = true;

    var enviandoConsultaEstatus = false;

    window.abrirModalActualizarEstatusSat = function(actionUrl, folio, tipo) {
        var form = document.getElementById('formActualizarEstatusSat');
        var mensaje = document.getElementById('modalActualizarEstatusSatMensaje');
        var btn = document.getElementById('btnConfirmarActualizarEstatusSat');
        if (!form || !mensaje) {
            return;
        }
        enviandoConsultaEstatus = false;
        form.action = actionUrl;
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Consultar SAT';
            btn.style.opacity = '';
            btn.style.cursor = '';
        }
        var folioHtml = folio ? ' <strong>' + folio + '</strong>' : '';
        if (tipo === 'complemento') {
            mensaje.innerHTML = 'Se consultará Facturama para el complemento' + folioHtml + '. El resultado dirá si sigue vigente, si está pendiente de aceptación o si ya se canceló.';
        } else {
            mensaje.innerHTML = 'Se consultará Facturama para la factura' + folioHtml + '. El resultado dirá si sigue vigente, si está pendiente de aceptación o si ya se canceló.';
        }
        document.getElementById('modalActualizarEstatusSat').classList.add('show');
    };

    window.cerrarModalActualizarEstatusSat = function() {
        if (enviandoConsultaEstatus) {
            return;
        }
        document.getElementById('modalActualizarEstatusSat')?.classList.remove('show');
    };

    document.addEventListener('click', function(event) {
        var btn = event.target.closest('.js-actualizar-estatus-sat');
        if (!btn) {
            return;
        }
        event.preventDefault();
        window.abrirModalActualizarEstatusSat(
            btn.getAttribute('data-action') || '',
            btn.getAttribute('data-folio') || '',
            btn.getAttribute('data-tipo') || 'factura'
        );
    });

    document.addEventListener('submit', function(event) {
        var form = event.target;
        if (!form || form.id !== 'formActualizarEstatusSat') {
            return;
        }
        if (enviandoConsultaEstatus) {
            event.preventDefault();
            return;
        }
        if (!form.action) {
            event.preventDefault();
            return;
        }
        enviandoConsultaEstatus = true;
        var btn = document.getElementById('btnConfirmarActualizarEstatusSat');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Consultando…';
            btn.style.opacity = '0.65';
            btn.style.cursor = 'wait';
        }
    }, true);
})();
</script>
@endpush
