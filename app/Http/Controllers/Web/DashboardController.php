<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/DashboardController.php

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\Producto;
use App\Models\CuentaPorCobrar;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Mostrar dashboard
     */
    public function index()
    {
        // Estadísticas generales
        $totalClientes = Cliente::count();
        $clientesActivos = Cliente::where('activo', true)->count();
        
        $totalProductos = Producto::count();
        $productosBajoStock = Producto::bajoStock()->count();
        
        // Facturas del mes actual
        $facturasDelMes = Factura::whereMonth('fecha_emision', now()->month)
            ->whereYear('fecha_emision', now()->year)
            ->count();
        
        $montoFacturado = Factura::whereMonth('fecha_emision', now()->month)
            ->whereYear('fecha_emision', now()->year)
            ->where('estado', 'timbrada')
            ->sum('total');
        
        // Cuentas por cobrar
        $porCobrar = CuentaPorCobrar::whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->sum('monto_pendiente');
        
        $cuentasVencidas = CuentaPorCobrar::where('estado', 'vencida')->count();
        
        // Facturas recientes
        $facturasRecientes = Factura::with('cliente')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Cuentas vencidas (top 5)
        $cuentasVencidasList = CuentaPorCobrar::with(['cliente', 'factura'])
            ->where('estado', 'vencida')
            ->orderBy('dias_vencido', 'desc')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'totalClientes',
            'clientesActivos',
            'totalProductos',
            'productosBajoStock',
            'facturasDelMes',
            'montoFacturado',
            'porCobrar',
            'cuentasVencidas',
            'facturasRecientes',
            'cuentasVencidasList'
        ));
    }
}