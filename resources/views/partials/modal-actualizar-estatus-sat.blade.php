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
                <button type="submit" class="btn btn-primary">Consultar SAT</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function() {
    window.abrirModalActualizarEstatusSat = function(actionUrl, folio, tipo) {
        var form = document.getElementById('formActualizarEstatusSat');
        var mensaje = document.getElementById('modalActualizarEstatusSatMensaje');
        if (!form || !mensaje) {
            return;
        }
        form.action = actionUrl;
        var folioHtml = folio ? ' <strong>' + folio + '</strong>' : '';
        if (tipo === 'complemento') {
            mensaje.innerHTML = 'Se consultará Facturama y el SAT para el complemento' + folioHtml + '. El resultado dirá si sigue vigente, si está pendiente de aceptación o si ya se canceló. No se asume un código 201.';
        } else {
            mensaje.innerHTML = 'Se consultará Facturama y el SAT para la factura' + folioHtml + '. El resultado dirá si sigue vigente, si está pendiente de aceptación o si ya se canceló. No se asume un código 201.';
        }
        document.getElementById('modalActualizarEstatusSat').classList.add('show');
    };
    window.cerrarModalActualizarEstatusSat = function() {
        document.getElementById('modalActualizarEstatusSat')?.classList.remove('show');
    };
    document.querySelectorAll('.js-actualizar-estatus-sat').forEach(function(btn) {
        btn.addEventListener('click', function() {
            window.abrirModalActualizarEstatusSat(
                this.getAttribute('data-action') || '',
                this.getAttribute('data-folio') || '',
                this.getAttribute('data-tipo') || 'factura'
            );
        });
    });
})();
</script>
@endpush
