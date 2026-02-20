<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Models\Empresa;

class PDFService
{
    public function generarDocumentoPDF($modelo, string $tipo): string
    {
        $modelo->loadMissing(['detalles.producto', 'cliente', 'usuario']);
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
            'esCotizacion' => $tipo === 'cotizacion',
            'esRemision' => $tipo === 'remision',
            'esComplemento' => $tipo === 'complemento',
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

    public function generarFacturaPDF($factura): string
    {
        return $this->generarDocumentoPDF($factura, 'factura');
    }
    
    public function descargarPDF(string $relativePath, string $filename)
    {
        $fullPath = storage_path('app/' . $relativePath);

        if (!file_exists($fullPath)) {
            abort(404, 'Archivo PDF no encontrado.');
        }

        return response()->download($fullPath, $filename);
    }

}