<?php

// UBICACIÓN: routes/web.php
// REEMPLAZA el contenido actual con este

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\GlobalSearchController;
use App\Http\Controllers\Web\PerfilController;
use App\Http\Controllers\Web\CotizacionController;
use App\Http\Controllers\Web\ClienteController;
use App\Http\Controllers\Web\ClienteContactoController;
use App\Http\Controllers\Web\ProductoController;
use App\Http\Controllers\Web\CategoriaProductoController;
use App\Http\Controllers\Web\FacturaController;
use App\Http\Controllers\Web\CuentaPorCobrarController;
use App\Http\Controllers\Web\ComplementoPagoController;
use App\Http\Controllers\Web\EmpresaController;
use App\Http\Controllers\Web\CotizacionCompraController;
use App\Http\Controllers\Web\OrdenCompraController;
use App\Http\Controllers\Web\ProveedorController;
use App\Http\Controllers\Web\CuentaPorPagarController;
use App\Http\Controllers\Web\RemisionController;
use App\Http\Controllers\Web\SugerenciaController;

/*
|--------------------------------------------------------------------------
| Web Routes - ERP Promafi COMPLETO
|--------------------------------------------------------------------------
*/

// ============================================================================
// RUTAS PÚBLICAS
// ============================================================================

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

// ============================================================================
// RUTAS PROTEGIDAS (requieren autenticación)
// ============================================================================

Route::middleware('auth')->group(function () {
    
    // ───── LOGOUT ─────
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // ───── DASHBOARD ─────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // ───── DASHBOARD ─────
    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/password', [PerfilController::class, 'updatePassword'])->name('perfil.password');

    // ───── EMPRESA / CONFIGURACIÓN ───── ✅
    Route::get('/configuracion', [EmpresaController::class, 'edit'])->name('empresa.edit');
    Route::put('/configuracion', [EmpresaController::class, 'update'])->name('empresa.update');
    Route::post('/configuracion/probar-pac', [EmpresaController::class, 'probarPAC'])->name('empresa.probar-pac');
    Route::post('/configuracion/verificar-certificados', [EmpresaController::class, 'verificarCertificados'])->name('empresa.verificar-certificados');
    
    // ========================================
    // COTIZACIONES
    // ========================================
    
    // Rutas específicas PRIMERO (antes de las rutas con parámetros)
    Route::get('/cotizaciones/crear', [CotizacionController::class, 'create'])->name('cotizaciones.create');
    Route::get('/cotizaciones/estadisticas', [CotizacionController::class, 'estadisticas'])->name('cotizaciones.estadisticas');
    Route::get('/cotizaciones/buscar-clientes', [CotizacionController::class, 'buscarClientes'])->name('cotizaciones.buscar-clientes');
    Route::get('/cotizaciones/buscar-productos', [CotizacionController::class, 'buscarProductos'])->name('cotizaciones.buscar-productos');
    
    // Listado y guardar
    Route::get('/cotizaciones', [CotizacionController::class, 'index'])->name('cotizaciones.index');
    Route::post('/cotizaciones', [CotizacionController::class, 'store'])->name('cotizaciones.store');
    
    // Rutas con parámetro {cotizacion}
    Route::get('/cotizaciones/{cotizacion}', [CotizacionController::class, 'show'])->name('cotizaciones.show');
    Route::delete('/cotizaciones/{cotizacion}', [CotizacionController::class, 'destroy'])->name('cotizaciones.destroy');
    
    // Acciones específicas
    Route::post('/cotizaciones/{cotizacion}/aceptar', [CotizacionController::class, 'aceptar'])->name('cotizaciones.aceptar');
    Route::post('/cotizaciones/{cotizacion}/enviar', [CotizacionController::class, 'enviar'])->name('cotizaciones.enviar');
    Route::post('/cotizaciones/{cotizacion}/convertir-factura', [CotizacionController::class, 'convertirFactura'])->name('cotizaciones.convertir-factura');
    
    // PDFs
    Route::get('/cotizaciones/{cotizacion}/generar-pdf', [CotizacionController::class, 'generarPDF'])->name('cotizaciones.generar-pdf');
    Route::get('/cotizaciones/{cotizacion}/descargar-pdf', [CotizacionController::class, 'descargarPDF'])->name('cotizaciones.descargar-pdf');
    Route::get('/cotizaciones/{cotizacion}/ver-pdf', [CotizacionController::class, 'verPDF'])->name('cotizaciones.ver-pdf');

    // ========================================
    // COTIZACIONES DE COMPRA
    // ========================================
    Route::get('/cotizaciones-compra/crear', [CotizacionCompraController::class, 'create'])->name('cotizaciones-compra.create');
    Route::get('/cotizaciones-compra/buscar-proveedores', [CotizacionCompraController::class, 'buscarProveedores'])->name('cotizaciones-compra.buscar-proveedores');
    Route::get('/cotizaciones-compra/buscar-productos', [CotizacionCompraController::class, 'buscarProductos'])->name('cotizaciones-compra.buscar-productos');
    Route::get('/cotizaciones-compra', [CotizacionCompraController::class, 'index'])->name('cotizaciones-compra.index');
    Route::post('/cotizaciones-compra', [CotizacionCompraController::class, 'store'])->name('cotizaciones-compra.store');
    Route::get('/cotizaciones-compra/{cotizacionCompra}', [CotizacionCompraController::class, 'show'])->name('cotizaciones-compra.show');
    Route::post('/cotizaciones-compra/{cotizacionCompra}/aprobar', [CotizacionCompraController::class, 'aprobar'])->name('cotizaciones-compra.aprobar');
    Route::post('/cotizaciones-compra/{cotizacionCompra}/generar-orden', [CotizacionCompraController::class, 'generarOrdenCompra'])->name('cotizaciones-compra.generar-orden');

    // ========================================
    // ÓRDENES DE COMPRA
    // ========================================
    Route::get('/ordenes-compra/crear', [OrdenCompraController::class, 'create'])->name('ordenes-compra.create');
    Route::get('/ordenes-compra', [OrdenCompraController::class, 'index'])->name('ordenes-compra.index');
    Route::post('/ordenes-compra', [OrdenCompraController::class, 'store'])->name('ordenes-compra.store');
    Route::get('/ordenes-compra/{ordenCompra}', [OrdenCompraController::class, 'show'])->name('ordenes-compra.show');
    Route::post('/ordenes-compra/{ordenCompra}/aceptar', [OrdenCompraController::class, 'aceptar'])->name('ordenes-compra.aceptar');
    Route::post('/ordenes-compra/{ordenCompra}/recibir', [OrdenCompraController::class, 'recibir'])->name('ordenes-compra.recibir');

    // ========================================
    // PROVEEDORES
    // ========================================
    Route::resource('proveedores', ProveedorController::class)->parameters(['proveedores' => 'proveedor']);

    // ========================================
    // CUENTAS POR PAGAR
    // ========================================
    Route::get('/cuentas-por-pagar', [CuentaPorPagarController::class, 'index'])->name('cuentas-por-pagar.index');
    Route::get('/cuentas-por-pagar/{cuentaPorPagar}', [CuentaPorPagarController::class, 'show'])->name('cuentas-por-pagar.show');
    Route::post('/cuentas-por-pagar/{cuentaPorPagar}/pagar', [CuentaPorPagarController::class, 'registrarPago'])->name('cuentas-por-pagar.registrar-pago');

    // ========================================
    // REMISIONES
    // ========================================
    Route::get('/remisiones/crear', [RemisionController::class, 'create'])->name('remisiones.create');
    Route::get('/remisiones/buscar-clientes', [RemisionController::class, 'buscarClientes'])->name('remisiones.buscar-clientes');
    Route::get('/remisiones/buscar-productos', [RemisionController::class, 'buscarProductos'])->name('remisiones.buscar-productos');
    Route::get('/remisiones', [RemisionController::class, 'index'])->name('remisiones.index');
    Route::post('/remisiones', [RemisionController::class, 'store'])->name('remisiones.store');
    Route::get('/remisiones/{remision}', [RemisionController::class, 'show'])->name('remisiones.show');
    Route::get('/remisiones/{remision}/editar', [RemisionController::class, 'edit'])->name('remisiones.edit');
    Route::put('/remisiones/{remision}', [RemisionController::class, 'update'])->name('remisiones.update');
    Route::delete('/remisiones/{remision}', [RemisionController::class, 'destroy'])->name('remisiones.destroy');
    Route::post('/remisiones/{remision}/enviar', [RemisionController::class, 'enviar'])->name('remisiones.enviar');
    Route::post('/remisiones/{remision}/entregar', [RemisionController::class, 'entregar'])->name('remisiones.entregar');
    Route::post('/remisiones/{remision}/cancelar', [RemisionController::class, 'cancelar'])->name('remisiones.cancelar');

    // ───── CLIENTES ───── ✅
    Route::resource('clientes', ClienteController::class);
    Route::resource('clientes.contactos', ClienteContactoController::class)->parameters(['contactos' => 'contacto']);
    
    // ───── PRODUCTOS ───── ✅
    Route::resource('productos', ProductoController::class);

    // ───── CATEGORIAS ───── ✅
    Route::resource('categorias', CategoriaProductoController::class);

    // ───── SUGERENCIAS (partidas para cotizar manual) ─────
    Route::get('/sugerencias/buscar', [SugerenciaController::class, 'buscar'])->name('sugerencias.buscar');
    Route::resource('sugerencias', SugerenciaController::class)->parameters(['sugerencias' => 'sugerencia']);

    
    // ───── FACTURAS ───── ✅
    Route::resource('facturas', FacturaController::class)->except(['edit', 'update']);
    Route::post('/facturas/{factura}/timbrar', [FacturaController::class, 'timbrar'])->name('facturas.timbrar');
    Route::post('/facturas/{factura}/generar-pdf', [FacturaController::class, 'generarPDF'])->name('facturas.generar-pdf');
    Route::delete('/facturas/{factura}/cancelar', [FacturaController::class, 'cancelar'])->name('facturas.cancelar');
    Route::get('/facturas/{factura}/descargar-xml', [FacturaController::class, 'descargarXML'])->name('facturas.descargar-xml');
    Route::get('/facturas/{factura}/descargar-pdf', [FacturaController::class, 'descargarPDF'])->name('facturas.descargar-pdf');
    
    // ───── CUENTAS POR COBRAR ───── ✅
    Route::get('/cuentas-cobrar', [CuentaPorCobrarController::class, 'index'])->name('cuentas-cobrar.index');
    Route::get('/cuentas-cobrar/{cuentaPorCobrar}', [CuentaPorCobrarController::class, 'show'])->name('cuentas-cobrar.show');
    Route::post('/cuentas-cobrar/{cuentaPorCobrar}/pagar', [CuentaPorCobrarController::class, 'registrarPago'])->name('cuentas-cobrar.registrar-pago');
    
    // ───── COMPLEMENTOS DE PAGO ───── ✅
    // IMPORTANTE: Rutas específicas ANTES de rutas con parámetros
    Route::get('/complementos/crear', [ComplementoPagoController::class, 'create'])->name('complementos.create');
    Route::get('/complementos/facturas-pendientes', [ComplementoPagoController::class, 'facturasPendientes'])->name('complementos.facturas-pendientes');
    Route::get('/complementos', [ComplementoPagoController::class, 'index'])->name('complementos.index');
    Route::post('/complementos', [ComplementoPagoController::class, 'store'])->name('complementos.store');
    Route::get('/complementos/{complemento}', [ComplementoPagoController::class, 'show'])->name('complementos.show');
    Route::post('/complementos/{complemento}/timbrar', [ComplementoPagoController::class, 'timbrar'])->name('complementos.timbrar');
    Route::get('/complementos/{complemento}/descargar-xml', [ComplementoPagoController::class, 'descargarXML'])->name('complementos.descargar-xml');
    
    // ───── BUSCADOR GLOBAL ─────
    Route::get('/buscar', [GlobalSearchController::class, 'search'])->name('global.search');


});