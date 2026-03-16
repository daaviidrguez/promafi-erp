<?php

// UBICACIÓN: routes/web.php
// REEMPLAZA el contenido actual con este

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\TableroController;
use App\Http\Controllers\Web\TableroAnualController;
use App\Http\Controllers\Web\GlobalSearchController;
use App\Http\Controllers\Web\PerfilController;
use App\Http\Controllers\Web\CotizacionController;
use App\Http\Controllers\Web\ClienteController;
use App\Http\Controllers\Web\ClienteContactoController;
use App\Http\Controllers\Web\ProductoController;
use App\Http\Controllers\Web\CategoriaProductoController;
use App\Http\Controllers\Web\FacturaController;
use App\Http\Controllers\Web\CuentaPorCobrarController;
use App\Http\Controllers\Web\EstadoCuentaController;
use App\Http\Controllers\Web\ComplementoPagoController;
use App\Http\Controllers\Web\DevolucionController;
use App\Http\Controllers\Web\NotaCreditoController;
use App\Http\Controllers\Web\EmpresaController;
use App\Http\Controllers\Web\CotizacionCompraController;
use App\Http\Controllers\Web\OrdenCompraController;
use App\Http\Controllers\Web\CompraController;
use App\Http\Controllers\Web\ProveedorController;
use App\Http\Controllers\Web\CuentaPorPagarController;
use App\Http\Controllers\Web\RemisionController;
use App\Http\Controllers\Web\SugerenciaController;
use App\Http\Controllers\Web\UsuarioController;
use App\Http\Controllers\Web\RoleController;
use App\Http\Controllers\Web\InventarioController;
use App\Http\Controllers\Web\CatalogosSatController;
use App\Http\Controllers\Web\RegimenFiscalController;
use App\Http\Controllers\Web\UsoCfdiController;
use App\Http\Controllers\Web\FormaPagoController;
use App\Http\Controllers\Web\MetodoPagoController;
use App\Http\Controllers\Web\MonedaController;
use App\Http\Controllers\Web\UnidadMedidaSatController;
use App\Http\Controllers\Web\ClaveProdServicioController;
use App\Http\Controllers\Web\IsrResicoController;
use App\Http\Controllers\Web\ListaPrecioController;
use App\Http\Controllers\Web\ReporteController;
use App\Http\Controllers\Web\ImportadorCfdiController;

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

Route::middleware(['auth', 'route.permission'])->group(function () {

    // ───── LOGOUT (no requiere permiso) ─────
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // ───── DASHBOARD ─────
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/tablero', [TableroController::class, 'index'])->name('tablero.index');
    Route::get('/tablero-anual', [TableroAnualController::class, 'index'])->name('tablero-anual.index');

    // ───── REPORTES ─────
    Route::redirect('/reportes', '/reportes/fiscal', 301)->name('reportes.index');
    Route::get('/reportes/fiscal', [ReporteController::class, 'fiscal'])->name('reportes.fiscal');
    Route::get('/reportes/ventas', [ReporteController::class, 'ventas'])->name('reportes.ventas');
    Route::get('/reportes/compras', [ReporteController::class, 'compras'])->name('reportes.compras');
    Route::get('/reportes/utilidad', [ReporteController::class, 'utilidad'])->name('reportes.utilidad');

    // ───── PERFIL ─────
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
    Route::get('/cotizaciones/listas-precios-cliente', [CotizacionController::class, 'listasPreciosCliente'])->name('cotizaciones.listas-precios-cliente');
    Route::get('/cotizaciones/productos-lista-precio', [CotizacionController::class, 'productosListaPrecio'])->name('cotizaciones.productos-lista-precio');
    
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
    Route::post('/cotizaciones/{cotizacion}/crear-productos-manuales', [CotizacionController::class, 'crearProductosDesdeManuales'])->name('cotizaciones.crear-productos-manuales');
    
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
    Route::get('/ordenes-compra/{ordenCompra}/edit', [OrdenCompraController::class, 'edit'])->name('ordenes-compra.edit');
    Route::put('/ordenes-compra/{ordenCompra}', [OrdenCompraController::class, 'update'])->name('ordenes-compra.update');
    Route::delete('/ordenes-compra/{ordenCompra}', [OrdenCompraController::class, 'destroy'])->name('ordenes-compra.destroy');
    Route::get('/ordenes-compra/{ordenCompra}/ver-pdf', [OrdenCompraController::class, 'verPDF'])->name('ordenes-compra.ver-pdf');
    Route::get('/ordenes-compra/{ordenCompra}/descargar-pdf', [OrdenCompraController::class, 'descargarPDF'])->name('ordenes-compra.descargar-pdf');
    Route::post('/ordenes-compra/{ordenCompra}/aceptar', [OrdenCompraController::class, 'aceptar'])->name('ordenes-compra.aceptar');
    Route::post('/ordenes-compra/{ordenCompra}/recibir', [OrdenCompraController::class, 'recibir'])->name('ordenes-compra.recibir');

    // ========================================
    // COMPRAS (facturas de compra directas / CFDI)
    // ========================================
    Route::get('/compras/crear', [CompraController::class, 'create'])->name('compras.create');
    Route::match(['get', 'post'], '/compras/subir-cfdi', [CompraController::class, 'uploadCfdi'])->name('compras.upload-cfdi');
    Route::get('/compras/crear-desde-cfdi', [CompraController::class, 'crearDesdeCfdi'])->name('compras.crear-desde-cfdi');
    Route::post('/compras/guardar-desde-cfdi', [CompraController::class, 'storeDesdeCfdi'])->name('compras.store-desde-cfdi');
    Route::get('/compras/buscar-proveedores', [CompraController::class, 'buscarProveedores'])->name('compras.buscar-proveedores');
    Route::get('/compras/buscar-productos', [CompraController::class, 'buscarProductos'])->name('compras.buscar-productos');
    Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
    Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
    Route::get('/compras/{compra}', [CompraController::class, 'show'])->name('compras.show');
    Route::get('/compras/{compra}/ver-pdf', [CompraController::class, 'verPDF'])->name('compras.ver-pdf');
    Route::get('/compras/{compra}/descargar-pdf', [CompraController::class, 'descargarPDF'])->name('compras.descargar-pdf');
    Route::post('/compras/{compra}/recibir', [CompraController::class, 'recibir'])->name('compras.recibir');

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
    Route::get('/cuentas-por-pagar/{cuentaPorPagar}/comprobante', [CuentaPorPagarController::class, 'verComprobante'])->name('cuentas-por-pagar.ver-comprobante');
    Route::get('/cuentas-por-pagar/{cuentaPorPagar}/comprobante/descargar', [CuentaPorPagarController::class, 'descargarComprobante'])->name('cuentas-por-pagar.descargar-comprobante');

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
    Route::get('/remisiones/{remision}/ver-pdf', [RemisionController::class, 'verPDF'])->name('remisiones.ver-pdf');
    Route::get('/remisiones/{remision}/descargar-pdf', [RemisionController::class, 'descargarPDF'])->name('remisiones.descargar-pdf');

    // ───── CLIENTES ───── ✅
    Route::resource('clientes', ClienteController::class);
    Route::resource('clientes.contactos', ClienteContactoController::class)->parameters(['contactos' => 'contacto']);
    
    // ───── PRODUCTOS ───── ✅
    Route::get('/productos/buscar-clave-sat', [ProductoController::class, 'buscarClaveSat'])->name('productos.buscar-clave-sat');
    Route::resource('productos', ProductoController::class);

    // ───── CATEGORIAS ───── ✅
    Route::resource('categorias', CategoriaProductoController::class);

    // ───── INVENTARIO ─────
    Route::get('/inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('/inventario/movimientos', [InventarioController::class, 'movimientos'])->name('inventario.movimientos');
    Route::get('/inventario/movimientos/crear', [InventarioController::class, 'createMovimiento'])->name('inventario.create-movimiento');
    Route::post('/inventario/movimientos', [InventarioController::class, 'storeMovimiento'])->name('inventario.store-movimiento');
    Route::get('/inventario/movimientos/{movimiento}', [InventarioController::class, 'showMovimiento'])->name('inventario.movimiento.show');
    Route::get('/inventario/producto/{producto}', [InventarioController::class, 'showProducto'])->name('inventario.show-producto');

    // ───── SUGERENCIAS (partidas para cotizar manual) ─────
    Route::get('/sugerencias/buscar', [SugerenciaController::class, 'buscar'])->name('sugerencias.buscar');
    Route::resource('sugerencias', SugerenciaController::class)->parameters(['sugerencias' => 'sugerencia']);

    
    // ───── FACTURAS ───── ✅
    Route::get('/facturas-para-relacion/listar', [FacturaController::class, 'listarParaRelacion'])->name('facturas.listar-para-relacion');
    Route::resource('facturas', FacturaController::class);
    Route::post('/facturas/{factura}/timbrar', [FacturaController::class, 'timbrar'])->name('facturas.timbrar');
    Route::post('/facturas/{factura}/generar-pdf', [FacturaController::class, 'generarPDF'])->name('facturas.generar-pdf');
    Route::delete('/facturas/{factura}/cancelar', [FacturaController::class, 'cancelar'])->name('facturas.cancelar');
    Route::get('/facturas/{factura}/descargar-xml', [FacturaController::class, 'descargarXML'])->name('facturas.descargar-xml');
    Route::get('/facturas/{factura}/descargar-xml-cancelacion', [FacturaController::class, 'descargarXmlCancelacion'])->name('facturas.descargar-xml-cancelacion');
    Route::get('/facturas/{factura}/obtener-acuse-cancelacion', [FacturaController::class, 'obtenerAcuseCancelacion'])->name('facturas.obtener-acuse-cancelacion');
    Route::get('/facturas/{factura}/ver-pdf', [FacturaController::class, 'verPDF'])->name('facturas.ver-pdf');
    Route::get('/facturas/{factura}/descargar-pdf', [FacturaController::class, 'descargarPDF'])->name('facturas.descargar-pdf');

    // ───── LISTAS DE PRECIOS ─────
    Route::get('/listas-precios/crear', [ListaPrecioController::class, 'create'])->name('listas-precios.create');
    Route::get('/listas-precios', [ListaPrecioController::class, 'index'])->name('listas-precios.index');
    Route::post('/listas-precios', [ListaPrecioController::class, 'store'])->name('listas-precios.store');
    Route::get('/listas-precios/{listaPrecio}', [ListaPrecioController::class, 'show'])->name('listas-precios.show');
    Route::get('/listas-precios/{listaPrecio}/ver-pdf', [ListaPrecioController::class, 'verPDF'])->name('listas-precios.ver-pdf');
    Route::get('/listas-precios/{listaPrecio}/ver-pdf-cliente', [ListaPrecioController::class, 'verPDFCliente'])->name('listas-precios.ver-pdf-cliente');
    Route::get('/listas-precios/{listaPrecio}/descargar-pdf', [ListaPrecioController::class, 'descargarPDF'])->name('listas-precios.descargar-pdf');
    Route::get('/listas-precios/{listaPrecio}/editar', [ListaPrecioController::class, 'edit'])->name('listas-precios.edit');
    Route::get('/listas-precios/{listaPrecio}/editar-masivamente', [ListaPrecioController::class, 'editarMasivamente'])->name('listas-precios.editar-masivamente');
    Route::get('/listas-precios/{listaPrecio}/descargar-plantilla', [ListaPrecioController::class, 'descargarPlantilla'])->name('listas-precios.descargar-plantilla');
    Route::post('/listas-precios/{listaPrecio}/importar-masivo', [ListaPrecioController::class, 'importarMasivo'])->name('listas-precios.importar-masivo');
    Route::put('/listas-precios/{listaPrecio}', [ListaPrecioController::class, 'update'])->name('listas-precios.update');
    Route::patch('/listas-precios/{listaPrecio}/toggle-activo', [ListaPrecioController::class, 'toggleActivo'])->name('listas-precios.toggle-activo');
    Route::delete('/listas-precios/{listaPrecio}', [ListaPrecioController::class, 'destroy'])->name('listas-precios.destroy');

    // ───── CATÁLOGOS SAT (Facturación) ─────
    Route::get('/catalogos-sat', [CatalogosSatController::class, 'index'])->name('catalogos-sat.index');
    Route::resource('catalogos-sat/regimenes-fiscales', RegimenFiscalController::class)->parameters(['regimenes_fiscales' => 'regimenes_fiscale'])->names('catalogos-sat.regimenes-fiscales');
    Route::resource('catalogos-sat/usos-cfdi', UsoCfdiController::class)->parameters(['usos_cfdi' => 'usos_cfdi'])->names('catalogos-sat.usos-cfdi');
    Route::resource('catalogos-sat/formas-pago', FormaPagoController::class)->parameters(['formas_pago' => 'formas_pago'])->names('catalogos-sat.formas-pago');
    Route::resource('catalogos-sat/metodos-pago', MetodoPagoController::class)->parameters(['metodos_pago' => 'metodos_pago'])->names('catalogos-sat.metodos-pago');
    Route::resource('catalogos-sat/monedas', MonedaController::class)->parameters(['monedas' => 'moneda'])->names('catalogos-sat.monedas');
    Route::resource('catalogos-sat/unidades-medida', UnidadMedidaSatController::class)->parameters(['unidades_medida' => 'unidades_medida'])->names('catalogos-sat.unidades-medida');
    Route::get('/catalogos-sat/claves-producto-servicio/plantilla', [ClaveProdServicioController::class, 'descargarPlantilla'])->name('catalogos-sat.claves-producto-servicio.plantilla');
    Route::post('/catalogos-sat/claves-producto-servicio/importar', [ClaveProdServicioController::class, 'importar'])->name('catalogos-sat.claves-producto-servicio.importar');
    Route::resource('catalogos-sat/claves-producto-servicio', ClaveProdServicioController::class)->parameters(['claves_producto_servicio' => 'claves_producto_servicio'])->names('catalogos-sat.claves-producto-servicio')->except(['show']);
    Route::get('/catalogos-sat/isr-resico', [IsrResicoController::class, 'index'])->name('catalogos-sat.isr-resico.index');
    Route::put('/catalogos-sat/isr-resico', [IsrResicoController::class, 'update'])->name('catalogos-sat.isr-resico.update');
    
    // ───── CUENTAS POR COBRAR ───── ✅
    Route::get('/cuentas-cobrar', [CuentaPorCobrarController::class, 'index'])->name('cuentas-cobrar.index');
    Route::get('/cuentas-cobrar/{cuentaPorCobrar}', [CuentaPorCobrarController::class, 'show'])->name('cuentas-cobrar.show');

    // ───── ESTADO DE CUENTA ─────
    Route::get('/estado-cuenta', [EstadoCuentaController::class, 'index'])->name('estado-cuenta.index');
    Route::get('/estado-cuenta/ver', [EstadoCuentaController::class, 'ver'])->name('estado-cuenta.ver');
    Route::get('/estado-cuenta/pdf', [EstadoCuentaController::class, 'pdf'])->name('estado-cuenta.pdf');
    
    // ───── COMPLEMENTOS DE PAGO ───── ✅
    // IMPORTANTE: Rutas específicas ANTES de rutas con parámetros
    Route::get('/complementos/crear', [ComplementoPagoController::class, 'create'])->name('complementos.create');
    Route::get('/complementos/facturas-pendientes', [ComplementoPagoController::class, 'facturasPendientes'])->name('complementos.facturas-pendientes');
    Route::get('/complementos', [ComplementoPagoController::class, 'index'])->name('complementos.index');
    Route::post('/complementos', [ComplementoPagoController::class, 'store'])->name('complementos.store');
    Route::get('/complementos/{complemento}', [ComplementoPagoController::class, 'show'])->name('complementos.show');
    Route::get('/complementos/{complemento}/editar', [ComplementoPagoController::class, 'edit'])->name('complementos.edit');
    Route::put('/complementos/{complemento}', [ComplementoPagoController::class, 'update'])->name('complementos.update');
    Route::delete('/complementos/{complemento}', [ComplementoPagoController::class, 'destroy'])->name('complementos.destroy');
    Route::post('/complementos/{complemento}/timbrar', [ComplementoPagoController::class, 'timbrar'])->name('complementos.timbrar');
    Route::get('/complementos/{complemento}/ver-pdf', [ComplementoPagoController::class, 'verPDF'])->name('complementos.ver-pdf');
    Route::get('/complementos/{complemento}/descargar-pdf', [ComplementoPagoController::class, 'descargarPDF'])->name('complementos.descargar-pdf');
    Route::get('/complementos/{complemento}/descargar-xml', [ComplementoPagoController::class, 'descargarXML'])->name('complementos.descargar-xml');

    // ───── DEVOLUCIONES ─────
    Route::get('/devoluciones', [DevolucionController::class, 'index'])->name('devoluciones.index');
    Route::get('/devoluciones/crear', [DevolucionController::class, 'create'])->name('devoluciones.create');
    Route::post('/devoluciones', [DevolucionController::class, 'store'])->name('devoluciones.store');
    Route::get('/devoluciones/{devolucion}', [DevolucionController::class, 'show'])->name('devoluciones.show');
    Route::post('/devoluciones/{devolucion}/autorizar', [DevolucionController::class, 'autorizar'])->name('devoluciones.autorizar');

    // ───── NOTAS DE CRÉDITO ─────
    Route::get('/notas-credito', [NotaCreditoController::class, 'index'])->name('notas-credito.index');
    Route::get('/notas-credito/crear', [NotaCreditoController::class, 'create'])->name('notas-credito.create');
    Route::post('/notas-credito', [NotaCreditoController::class, 'store'])->name('notas-credito.store');
    Route::get('/notas-credito/{notaCredito}', [NotaCreditoController::class, 'show'])->name('notas-credito.show');
    Route::get('/notas-credito/{notaCredito}/editar', [NotaCreditoController::class, 'edit'])->name('notas-credito.edit');
    Route::put('/notas-credito/{notaCredito}', [NotaCreditoController::class, 'update'])->name('notas-credito.update');
    Route::delete('/notas-credito/{notaCredito}', [NotaCreditoController::class, 'destroy'])->name('notas-credito.destroy');
    Route::post('/notas-credito/{notaCredito}/timbrar', [NotaCreditoController::class, 'timbrar'])->name('notas-credito.timbrar');
    Route::get('/notas-credito/{notaCredito}/ver-pdf', [NotaCreditoController::class, 'verPDF'])->name('notas-credito.ver-pdf');
    Route::get('/notas-credito/{notaCredito}/descargar-pdf', [NotaCreditoController::class, 'descargarPDF'])->name('notas-credito.descargar-pdf');
    Route::get('/notas-credito/{notaCredito}/descargar-xml', [NotaCreditoController::class, 'descargarXML'])->name('notas-credito.descargar-xml');
    
    // ───── BUSCADOR GLOBAL ─────
    Route::get('/buscar', [GlobalSearchController::class, 'search'])->name('global.search');

    // ───── SISTEMA: Usuarios, Roles, Importador CFDI ─────
    Route::get('/importador-cfdi', [ImportadorCfdiController::class, 'index'])->name('importador-cfdi.index');
    Route::post('/importador-cfdi', [ImportadorCfdiController::class, 'store'])->name('importador-cfdi.store');
    Route::resource('usuarios', UsuarioController::class)->parameters(['usuarios' => 'usuario']);
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::get('/roles/{role}/editar', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');

});
