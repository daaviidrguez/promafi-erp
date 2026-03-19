<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Models\Empresa;
use App\Models\InventarioMovimiento;

class PDFService
{
    public function generarDocumentoPDF($modelo, string $tipo): string
    {
        if ($tipo === 'factura') {
            $modelo->loadMissing(['detalles.producto', 'detalles.impuestos', 'cliente', 'cuentaPorCobrar', 'usuario', 'empresa']);
        } elseif ($tipo === 'nota_credito') {
            $modelo->loadMissing(['detalles.producto', 'detalles.impuestos', 'factura', 'cliente', 'usuario', 'empresa']);
        } elseif ($tipo === 'complemento') {
            $modelo->loadMissing(['pagosRecibidos.documentosRelacionados.factura.cuentaPorCobrar', 'pagosRecibidos.documentosRelacionados.factura.detalles.impuestos', 'cliente', 'usuario', 'empresa']);
        } elseif ($tipo === 'cotizacion_compra') {
            $modelo->loadMissing(['detalles.producto', 'proveedor', 'usuario', 'empresa']);
        } elseif ($tipo === 'orden_compra') {
            $modelo->loadMissing(['detalles.producto', 'proveedor', 'usuario', 'empresa']);
        } elseif ($tipo === 'factura_compra') {
            $modelo->loadMissing(['detalles.producto', 'detalles.impuestos', 'proveedor', 'usuario', 'empresa']);
        } elseif ($tipo === 'remision') {
            $modelo->loadMissing(['detalles.producto', 'cliente', 'usuario', 'empresa']);
        } else {
            $modelo->loadMissing(['detalles.producto', 'cliente', 'usuario']);
        }
        $empresa = Empresa::principal();

        $directory = storage_path('app/documentos/' . $tipo . '/' . now()->format('Y/m'));

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = strtoupper($tipo) . '_' . $modelo->folio . '.pdf';
        $filepath = $directory . '/' . $filename;

        $html = view('pdf.documento', [
            'doc' => $modelo,
            'empresa' => $empresa,
            'tipo' => $tipo,
            'esFactura' => $tipo === 'factura',
            'esNotaCredito' => $tipo === 'nota_credito',
            'esCotizacion' => $tipo === 'cotizacion',
            'esRemision' => $tipo === 'remision',
            'esComplemento' => $tipo === 'complemento',
            'esCotizacionCompra' => $tipo === 'cotizacion_compra',
            'esOrdenCompra' => $tipo === 'orden_compra',
            'esFacturaCompra' => $tipo === 'factura_compra',
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        file_put_contents($filepath, $dompdf->output());

        return 'documentos/' . $tipo . '/' . now()->format('Y/m') . '/' . $filename;
    }

    public function generarCotizacionPDF($cotizacion): string
    {
        return $this->generarDocumentoPDF($cotizacion, 'cotizacion');
    }

    public function generarCotizacionCompraPDF($cotizacionCompra): string
    {
        return $this->generarDocumentoPDF($cotizacionCompra, 'cotizacion_compra');
    }

    public function generarFacturaPDF($factura): string
    {
        return $this->generarDocumentoPDF($factura, 'factura');
    }

    public function generarComplementoPDF($complemento): string
    {
        return $this->generarDocumentoPDF($complemento, 'complemento');
    }

    public function generarNotaCreditoPDF($notaCredito): string
    {
        return $this->generarDocumentoPDF($notaCredito, 'nota_credito');
    }

    public function generarOrdenCompraPDF($ordenCompra): string
    {
        return $this->generarDocumentoPDF($ordenCompra, 'orden_compra');
    }

    public function generarFacturaCompraPDF($facturaCompra): string
    {
        return $this->generarDocumentoPDF($facturaCompra, 'factura_compra');
    }

    public function generarRemisionPDF($remision): string
    {
        return $this->generarDocumentoPDF($remision, 'remision');
    }

    public function descargarPDF(string $relativePath, string $filename)
    {
        $fullPath = storage_path('app/' . $relativePath);

        if (!file_exists($fullPath)) {
            abort(404, 'Archivo PDF no encontrado.');
        }

        return response()->download($fullPath, $filename);
    }

    /**
     * Genera PDF del kardex de un producto en un rango de fechas.
     *
     * @param \App\Models\Producto $producto
     * @param \Illuminate\Support\Collection<int, InventarioMovimiento> $movimientos
     * @param \Carbon\Carbon $fechaDesde
     * @param \Carbon\Carbon $fechaHasta
     * @param float $saldoInicial
     * @return string Ruta relativa del archivo guardado
     */
    public function generarKardexPDF($producto, $movimientos, $fechaDesde, $fechaHasta, float $saldoInicial): string
    {
        $empresa = Empresa::principal();
        $directory = storage_path('app/documentos/kardex/' . now()->format('Y/m'));
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        $filename = 'Kardex_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $producto->codigo ?? 'prod') . '_' . $fechaDesde->format('Y-m-d') . '.pdf';
        $filepath = $directory . '/' . $filename;

        $html = view('pdf.kardex', [
            'empresa' => $empresa,
            'producto' => $producto,
            'movimientos' => $movimientos,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'saldoInicial' => $saldoInicial,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();
        file_put_contents($filepath, $dompdf->output());

        return 'documentos/kardex/' . now()->format('Y/m') . '/' . $filename;
    }
}