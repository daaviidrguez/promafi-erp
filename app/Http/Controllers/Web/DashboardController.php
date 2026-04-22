<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Producto;
use App\Models\CuentaPorCobrar;
use App\Models\Cotizacion;
use App\Models\ComplementoPago;
use App\Models\Remision;
use App\Models\NotaCredito;
use App\Models\Proveedor;
use App\Models\OrdenCompra;
use App\Models\CotizacionCompra;
use App\Models\CuentaPorPagar;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Mostrar dashboard con resúmenes por departamento.
     */
    public function index()
    {
        $mes = now()->month;
        $año = now()->year;

        // ─── CLIENTES (Administración) ───
        $totalClientes = Cliente::count();
        $clientesActivos = Cliente::where('activo', true)->count();

        // ─── FACTURACIÓN ───
        $facturasDelMes = Factura::whereMonth('fecha_emision', $mes)
            ->whereYear('fecha_emision', $año)
            ->count();
        $montoFacturado = (float) Factura::whereMonth('fecha_emision', $mes)
            ->whereYear('fecha_emision', $año)
            ->where('estado', 'timbrada')
            ->sum('total');
        $facturasBorrador = Factura::where('estado', 'borrador')->count();

        $cotizacionesTotal = Cotizacion::count();
        $cotizacionesMes = Cotizacion::whereMonth('fecha', $mes)->whereYear('fecha', $año)->count();
        $cotizacionesPendientes = Cotizacion::whereIn('estado', ['enviada', 'aceptada'])->count();

        $complementosMes = ComplementoPago::whereMonth('fecha_emision', $mes)->whereYear('fecha_emision', $año)->count();
        $remisionesMes = Remision::whereMonth('fecha', $mes)->whereYear('fecha', $año)->count();
        $notasCreditoMes = NotaCredito::whereMonth('fecha_emision', $mes)->whereYear('fecha_emision', $año)->count();

        // ─── COBRANZA / FINANZAS (coherente con saldo_pendiente_real) ───
        $cuentasCobranza = CuentaPorCobrar::with(['cliente', 'factura'])
            ->excluirFacturaBorrador()
            ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->get();
        $porCobrar = (float) $cuentasCobranza->sum(fn ($c) => $c->saldo_pendiente_real);
        $cuentasVencidasList = $cuentasCobranza
            ->filter(fn ($c) => $c->estado_display === 'vencida')
            ->sortByDesc('dias_vencido')
            ->take(5)
            ->values();
        $cuentasVencidas = $cuentasCobranza->filter(fn ($c) => $c->estado_display === 'vencida')->count();

        $facturasRecientes = Factura::with('cliente')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $remisionesPendientesFacturar = Remision::where('estado', 'entregada')
            ->where(function ($q) {
                $q->whereNull('factura_id')
                    ->orWhereHas('factura', fn ($f) => $f->where('estado', 'cancelada'));
            })
            ->count();
        $remisionesPendientesFacturarList = Remision::with('cliente')
            ->where('estado', 'entregada')
            ->where(function ($q) {
                $q->whereNull('factura_id')
                    ->orWhereHas('factura', fn ($f) => $f->where('estado', 'cancelada'));
            })
            ->orderBy('fecha_entrega', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // ─── PRODUCTOS / INVENTARIO ───
        $totalProductos = Producto::count();
        $productosBajoStock = Producto::bajoStock()->count();
        $productosActivos = Producto::where('activo', true)->count();

        // ─── COMPRAS ───
        $totalProveedores = Proveedor::count();
        $proveedoresActivos = Proveedor::activos()->count();

        $ordenesBorrador = OrdenCompra::where('estado', 'borrador')->count();
        $ordenesAceptadas = OrdenCompra::where('estado', 'aceptada')->count();
        $ordenesRecibidas = OrdenCompra::where('estado', 'recibida')->count();
        $ordenesConvertidasCompra = OrdenCompra::where('estado', 'convertida_compra')->count();
        $ordenesMes = OrdenCompra::whereMonth('fecha', $mes)->whereYear('fecha', $año)->count();

        $cotizacionesCompraTotal = CotizacionCompra::count();
        $cotizacionesCompraMes = CotizacionCompra::whereMonth('created_at', $mes)->whereYear('created_at', $año)->count();

        $porPagar = (float) CuentaPorPagar::whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->sum('monto_pendiente');
        $cuentasPorPagarPendientes = CuentaPorPagar::whereIn('estado', ['pendiente', 'parcial', 'vencida'])->count();

        return view('dashboard', compact(
            'totalClientes',
            'clientesActivos',
            'totalProductos',
            'productosBajoStock',
            'productosActivos',
            'facturasDelMes',
            'montoFacturado',
            'facturasBorrador',
            'cotizacionesTotal',
            'cotizacionesMes',
            'cotizacionesPendientes',
            'complementosMes',
            'remisionesMes',
            'notasCreditoMes',
            'porCobrar',
            'cuentasVencidas',
            'cuentasVencidasList',
            'facturasRecientes',
            'remisionesPendientesFacturar',
            'remisionesPendientesFacturarList',
            'totalProveedores',
            'proveedoresActivos',
            'ordenesBorrador',
            'ordenesAceptadas',
            'ordenesRecibidas',
            'ordenesConvertidasCompra',
            'ordenesMes',
            'cotizacionesCompraTotal',
            'cotizacionesCompraMes',
            'porPagar',
            'cuentasPorPagarPendientes'
        ));
    }
}
