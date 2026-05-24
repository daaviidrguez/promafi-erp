<style>
.complemento-facturas-wrap .complemento-facturas-table {
    table-layout: fixed;
    width: 100%;
    min-width: 520px;
}
.complemento-facturas-wrap .complemento-facturas-table thead th,
.complemento-facturas-wrap .complemento-facturas-table tbody td {
    vertical-align: middle;
}
.complemento-facturas-wrap .complemento-facturas-table .col-factura {
    width: 44%;
    padding-left: 16px;
    padding-right: 12px;
}
.complemento-facturas-wrap .complemento-facturas-table .col-fecha {
    width: 76px;
    padding-left: 8px;
    padding-right: 8px;
    white-space: nowrap;
    font-size: 12.5px;
    color: var(--color-gray-600);
}
.complemento-facturas-wrap .complemento-facturas-table .col-pendiente {
    width: 104px;
    padding-left: 6px;
    padding-right: 4px;
}
.complemento-facturas-wrap .complemento-facturas-table .col-pagar {
    width: 118px;
    padding-left: 4px;
    padding-right: 6px;
}
.complemento-facturas-wrap .complemento-facturas-table .col-pago-total {
    width: 52px;
    padding-left: 4px;
    padding-right: 10px;
}
.complemento-facturas-wrap .complemento-facturas-table thead .col-fecha,
.complemento-facturas-wrap .complemento-facturas-table thead .col-pendiente,
.complemento-facturas-wrap .complemento-facturas-table thead .col-pagar,
.complemento-facturas-wrap .complemento-facturas-table thead .col-pago-total {
    padding-top: 10px;
    padding-bottom: 10px;
}
.complemento-facturas-wrap .complemento-facturas-table thead .col-pago-total {
    text-align: center;
}
.complemento-facturas-wrap .complemento-facturas-table .monto-pago {
    width: 100%;
    max-width: 112px;
    margin-left: auto;
    text-align: right;
    padding: 6px 8px;
    font-size: 13px;
}
.complemento-facturas-wrap .complemento-facturas-table .pago-total-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 32px;
}
.complemento-facturas-wrap .complemento-facturas-table .pago-total-check {
    width: 15px;
    height: 15px;
    margin: 0;
    cursor: pointer;
    accent-color: var(--color-primary);
}
@media (max-width: 640px) {
    .complemento-facturas-wrap .complemento-facturas-table .col-factura { width: 38%; }
    .complemento-facturas-wrap .complemento-facturas-table .col-fecha { width: 68px; }
    .complemento-facturas-wrap .complemento-facturas-table .col-pendiente { width: 92px; }
    .complemento-facturas-wrap .complemento-facturas-table .col-pagar { width: 108px; }
}
</style>
