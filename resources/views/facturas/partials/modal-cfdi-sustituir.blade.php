{{-- Modal seleccionar CFDI a sustituir (relación tipo 04) --}}
<div id="modalSeleccionarCfdiSustituir" class="modal">
    <div class="modal-box" style="max-width: 640px;">
        <div class="modal-header">
            <div class="modal-title">Seleccionar CFDI a sustituir</div>
            <button type="button" class="modal-close" onclick="cerrarModalCfdiSustituir()">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom: 12px;">{{ $descripcion }}</p>
            <div id="alertaCfdiSustituir" class="alert alert-success" role="status" aria-live="polite" style="display: none; margin-bottom: 12px;">
                <span id="alertaCfdiSustituirIcono">✓</span>
                <span id="alertaCfdiSustituirMsg"></span>
            </div>
            <div class="table-container" style="max-height: 320px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Serie / Folio</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="listaFacturasSustituir"></tbody>
                </table>
            </div>
            <div id="cargandoCfdiSustituir" style="text-align: center; padding: 20px; color: var(--color-gray-500);">Cargando facturas...</div>
            <div id="sinFacturasCfdiSustituir" style="display: none; text-align: center; padding: 20px; color: var(--color-gray-500);">No hay facturas disponibles (timbradas o canceladas administrativamente pendientes de PAC).</div>
        </div>
    </div>
</div>
