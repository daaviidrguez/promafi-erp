const listarParaRelacionUrl = '{{ route("facturas.listar-para-relacion") }}';
const formatoMontoCfdiSustituir = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
let alertaCfdiSustituirTimer = null;

function folioCfdiSustituir(f) {
    const partes = [f.serie, f.folio].filter(v => v !== null && v !== undefined && String(v).trim() !== '');
    return partes.length ? partes.join('-') : 'sin folio';
}

function uuidsCfdiSustituirActuales() {
    return (document.getElementById('inputUuidReferencia').value || '').split(',').map(s => s.trim()).filter(Boolean);
}

function ocultarAlertaCfdiSustituir() {
    clearTimeout(alertaCfdiSustituirTimer);
    document.getElementById('alertaCfdiSustituir').style.display = 'none';
}

// partes: strings como texto plano, {strong: '...'} como texto en negritas.
function mostrarAlertaCfdiSustituir(tipo, partes) {
    const box = document.getElementById('alertaCfdiSustituir');
    const msg = document.getElementById('alertaCfdiSustituirMsg');
    box.className = 'alert alert-' + tipo;
    document.getElementById('alertaCfdiSustituirIcono').textContent = tipo === 'success' ? '✓' : '⚠';
    msg.replaceChildren(...partes.map(p => {
        if (typeof p === 'string') return document.createTextNode(p);
        const strong = document.createElement('strong');
        strong.textContent = p.strong;
        return strong;
    }));
    box.style.display = 'flex';
    clearTimeout(alertaCfdiSustituirTimer);
    alertaCfdiSustituirTimer = setTimeout(ocultarAlertaCfdiSustituir, 4000);
}

function marcarBotonCfdiSustituirAgregado(btn) {
    btn.textContent = '✓ Agregada';
    btn.classList.remove('btn-primary');
    btn.classList.add('btn-success');
}

function agregarCfdiSustituir(f, btn) {
    if (!f.uuid) return;
    const folio = folioCfdiSustituir(f);
    const current = uuidsCfdiSustituirActuales();
    if (current.includes(f.uuid)) {
        mostrarAlertaCfdiSustituir('warning', ['La factura ', { strong: folio }, ' ya estaba agregada como CFDI a sustituir.']);
        return;
    }
    current.push(f.uuid);
    const inputHidden = document.getElementById('inputUuidReferencia');
    inputHidden.value = current.join(', ');
    document.getElementById('inputUuidReferenciaDisplay').value = inputHidden.value;
    document.getElementById('inputTipoRelacion').value = '04';
    marcarBotonCfdiSustituirAgregado(btn);
    mostrarAlertaCfdiSustituir('success', [
        'Se agregó la factura ', { strong: folio },
        ' por ', { strong: formatoMontoCfdiSustituir.format(Number(f.total) || 0) },
        ' como CFDI a sustituir.',
    ]);
}

function crearFilaCfdiSustituir(f, uuidsAgregados) {
    const tr = document.createElement('tr');
    [
        [f.serie, f.folio].filter(Boolean).join(' '),
        f.cliente_nombre || '',
        f.fecha_emision || '',
        formatoMontoCfdiSustituir.format(Number(f.total) || 0),
    ].forEach(texto => {
        const td = document.createElement('td');
        td.textContent = texto;
        tr.appendChild(td);
    });
    const tdAccion = document.createElement('td');
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-primary btn-sm';
    btn.textContent = 'Agregar';
    if (uuidsAgregados.includes(f.uuid)) marcarBotonCfdiSustituirAgregado(btn);
    btn.addEventListener('click', () => agregarCfdiSustituir(f, btn));
    tdAccion.appendChild(btn);
    tr.appendChild(tdAccion);
    return tr;
}

function abrirModalSeleccionarCfdiSustituir() {
    ocultarAlertaCfdiSustituir();
    document.getElementById('modalSeleccionarCfdiSustituir').classList.add('show');
    document.getElementById('cargandoCfdiSustituir').style.display = 'block';
    document.getElementById('sinFacturasCfdiSustituir').style.display = 'none';
    document.getElementById('listaFacturasSustituir').innerHTML = '';
    fetch(listarParaRelacionUrl)
        .then(r => r.json())
        .then(data => {
            document.getElementById('cargandoCfdiSustituir').style.display = 'none';
            const list = data.facturas || [];
            if (list.length === 0) {
                document.getElementById('sinFacturasCfdiSustituir').style.display = 'block';
                return;
            }
            const tbody = document.getElementById('listaFacturasSustituir');
            const uuidsAgregados = uuidsCfdiSustituirActuales();
            list.forEach(f => tbody.appendChild(crearFilaCfdiSustituir(f, uuidsAgregados)));
        })
        .catch(() => {
            document.getElementById('cargandoCfdiSustituir').style.display = 'none';
            document.getElementById('sinFacturasCfdiSustituir').style.display = 'block';
            document.getElementById('sinFacturasCfdiSustituir').textContent = 'Error al cargar facturas.';
        });
}
function cerrarModalCfdiSustituir() {
    ocultarAlertaCfdiSustituir();
    document.getElementById('modalSeleccionarCfdiSustituir').classList.remove('show');
}
function limpiarCfdiSustituir() {
    document.getElementById('inputUuidReferencia').value = '';
    document.getElementById('inputUuidReferenciaDisplay').value = '';
    document.getElementById('inputTipoRelacion').value = '04';
}
