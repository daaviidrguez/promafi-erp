<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ImportadorCfdiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ImportadorCfdiController extends Controller
{
    public function __construct(
        protected ImportadorCfdiService $importador
    ) {}

    /**
     * Muestra el formulario de importación (subir XML).
     */
    public function index()
    {
        return view('importador-cfdi.index');
    }

    /**
     * Procesa uno o más archivos XML y registra facturas o complementos de pago.
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'archivos' => 'required|array',
            'archivos.*' => [
                'required',
                'file',
                'max:2048',
                function (string $attribute, $value, \Closure $fail): void {
                    $ext = strtolower($value->getClientOriginalExtension());
                    $mime = $value->getMimeType();
                    $xmlMimes = ['text/xml', 'application/xml', 'application/x-xml', 'text/plain'];
                    $esXml = $ext === 'xml' || in_array($mime, $xmlMimes, true);
                    if (!$esXml) {
                        $fail('Cada archivo debe ser XML (.xml).');
                    }
                },
            ],
        ], [
            'archivos.required' => 'Seleccione al menos un archivo XML.',
        ])->validate();

        $resultados = [];
        $archivos = $request->file('archivos');

        foreach ($archivos as $archivo) {
            $contenido = file_get_contents($archivo->getRealPath());
            if ($contenido === false) {
                $resultados[] = [
                    'archivo' => $archivo->getClientOriginalName(),
                    'success' => false,
                    'tipo' => null,
                    'errors' => ['No se pudo leer el archivo.'],
                    'warnings' => [],
                ];
                continue;
            }

            $resultado = $this->importador->importar($contenido);
            $modelo = $resultado['modelo'];
            $resultados[] = [
                'archivo' => $archivo->getClientOriginalName(),
                'success' => $resultado['success'],
                'tipo' => $resultado['tipo'],
                'modelo_id' => $modelo ? $modelo->id : null,
                'errors' => $resultado['errors'],
                'warnings' => $resultado['warnings'],
            ];
        }

        $importados = collect($resultados)->where('success', true)->count();
        $fallidos = collect($resultados)->where('success', false)->count();

        return redirect()
            ->route('importador-cfdi.index')
            ->with('importador_resultados', $resultados)
            ->with('importador_importados', $importados)
            ->with('importador_fallidos', $fallidos);
    }
}
