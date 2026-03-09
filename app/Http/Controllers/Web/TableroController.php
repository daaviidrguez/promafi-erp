<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\CuentaPorCobrar;
use App\Models\ComplementoPago;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\Proveedor;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TableroController extends Controller
{
    /**
     * Tablero con 4 secciones: Ventas, Clientes, Productos, Compras y gastos.
     */
    public function index()
    {
        $hoy = now();
        $mesActual = $hoy->month;
        $añoActual = $hoy->year;

        // ─── 1. VENTAS (ventas + cobranza) ───
        $ventasPorMes = Factura::where('estado', 'timbrada')
            ->whereYear('fecha_emision', $añoActual)
            ->selectRaw('MONTH(fecha_emision) as mes, SUM(total) as total')
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->keyBy('mes');
        $labelsVentas = [];
        $dataVentas = [];
        for ($m = 1; $m <= 12; $m++) {
            $labelsVentas[] = Carbon::create()->month($m)->locale('es')->translatedFormat('M');
            $dataVentas[] = (float) ($ventasPorMes->get($m)?->total ?? 0);
        }

        $cuentasCobranza = CuentaPorCobrar::excluirFacturaBorrador()
            ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->get();
        $cobranzaPendiente = (float) $cuentasCobranza->sum(fn ($c) => $c->saldo_pendiente_real);
        $cobranzaCobradoMes = (float) ComplementoPago::whereMonth('fecha_emision', $mesActual)
            ->whereYear('fecha_emision', $añoActual)
            ->sum('monto_total');
        $cobranzaLabels = ['Pendiente por cobrar', 'Cobrado (mes actual)'];
        $cobranzaData = [$cobranzaPendiente, $cobranzaCobradoMes];

        // ─── 2. CLIENTES (más importantes + antigüedad de saldos) ───
        $clientesImportantes = Cliente::where('clientes.activo', true)
            ->select(['clientes.id', 'clientes.nombre'])
            ->leftJoin('facturas', function ($j) {
                $j->on('facturas.cliente_id', '=', 'clientes.id')->where('facturas.estado', '=', 'timbrada');
            })
            ->groupBy('clientes.id', 'clientes.nombre')
            ->selectRaw('COALESCE(SUM(facturas.total), 0) as total_ventas')
            ->orderByDesc('total_ventas')
            ->limit(10)
            ->get();
        $clientesImportantes->each(fn ($c) => $c->total_ventas = (float) ($c->total_ventas ?? 0));

        $antiguedadSaldos = $cuentasCobranza->filter(fn ($c) => $c->saldo_pendiente_real > 0)
            ->groupBy(function ($c) {
                $d = $c->dias_vencido ?? 0;
                if ($d <= 0) return 'Al corriente';
                if ($d <= 30) return '1-30 días';
                if ($d <= 60) return '31-60 días';
                if ($d <= 90) return '61-90 días';
                return 'Más de 90 días';
            });
        $ordenRango = ['Al corriente', '1-30 días', '31-60 días', '61-90 días', 'Más de 90 días'];
        $antiguedadLabels = $ordenRango;
        $antiguedadData = array_map(fn ($r) => (float) ($antiguedadSaldos->get($r)?->sum(fn ($c) => $c->saldo_pendiente_real) ?? 0), $ordenRango);

        // ─── 3. PRODUCTOS (más vendidos, mayor costo, utilidad bruta) ───
        $masVendidos = FacturaDetalle::whereHas('factura', fn ($q) => $q->where('estado', 'timbrada'))
            ->select('producto_id', DB::raw('SUM(cantidad) as cantidad'), DB::raw('SUM(importe) as importe'))
            ->groupBy('producto_id')
            ->orderByDesc('cantidad')
            ->limit(10)
            ->with('producto:id,nombre,codigo')
            ->get();
        $mayorCosto = Producto::where('activo', true)->orderByDesc('costo')->limit(10)->get(['id', 'nombre', 'codigo', 'costo', 'precio_venta']);
        $utilidadBruta = Producto::where('activo', true)
            ->whereRaw('(precio_venta - costo) > 0')
            ->selectRaw('id, nombre, codigo, costo, precio_venta, (precio_venta - costo) as utilidad_bruta')
            ->orderByDesc(DB::raw('(precio_venta - costo)'))
            ->limit(10)
            ->get();

        // ─── 4. COMPRAS Y GASTOS (compras y servicios más importantes) ───
        $comprasPorProveedor = OrdenCompra::whereIn('estado', ['aceptada', 'recibida'])
            ->select('proveedor_id', 'proveedor_nombre', DB::raw('SUM(total) as total'))
            ->groupBy('proveedor_id', 'proveedor_nombre')
            ->orderByDesc('total')
            ->limit(10)
            ->get();
        $comprasPorMes = OrdenCompra::whereIn('estado', ['aceptada', 'recibida'])
            ->whereYear('fecha', $añoActual)
            ->selectRaw('MONTH(fecha) as mes, SUM(total) as total')
            ->groupBy('mes')
            ->orderBy('mes')
            ->get()
            ->keyBy('mes');
        $labelsCompras = [];
        $dataCompras = [];
        for ($m = 1; $m <= 12; $m++) {
            $labelsCompras[] = Carbon::create()->month($m)->locale('es')->translatedFormat('M');
            $dataCompras[] = (float) ($comprasPorMes->get($m)?->total ?? 0);
        }

        return view('tablero.index', compact(
            'labelsVentas',
            'dataVentas',
            'cobranzaLabels',
            'cobranzaData',
            'clientesImportantes',
            'antiguedadLabels',
            'antiguedadData',
            'masVendidos',
            'mayorCosto',
            'utilidadBruta',
            'comprasPorProveedor',
            'labelsCompras',
            'dataCompras'
        ));
    }
}
