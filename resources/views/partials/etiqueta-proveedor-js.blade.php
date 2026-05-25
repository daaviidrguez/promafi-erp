<script>
function etiquetaProveedor(p) {
    if (!p) return '';
    var codigo = (p.codigo || '').trim();
    var nombre = (p.nombre || p.etiqueta || '').trim();
    if (p.etiqueta && !p.nombre) return p.etiqueta;
    return codigo ? (nombre + ' - ' + codigo) : nombre;
}
</script>
