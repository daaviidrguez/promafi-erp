<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Cotización {{ $cotizacion->folio ?? '' }}</title>
    <style>
        :root {
            --color-white: #fff;
            --color-border: #E2E8F0;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background: #f8fafc;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        }
        .pdf-app-shell {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .pdf-app-header {
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 10px 0 12px;
            border-bottom: 1px solid var(--color-border);
            background: var(--color-white);
        }
        .pdf-app-title {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding-right: 8px;
        }
        .pdf-close-btn {
            border: 0;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: #ef4444;
            color: #fff;
            font-size: 20px;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            flex-shrink: 0;
        }
        .pdf-frame-wrap {
            flex: 1;
            min-height: 0;
            background: #f1f5f9;
        }
        .pdf-frame {
            width: 100%;
            height: 100%;
            border: 0;
            background: #fff;
        }
    </style>
</head>
<body>
    <div class="pdf-app-shell">
        <div class="pdf-app-header">
            <div class="pdf-app-title">Cotización {{ $cotizacion->folio ?? '' }}</div>
            <a href="{{ $returnUrl }}" class="pdf-close-btn" aria-label="Cerrar PDF y regresar">✕</a>
        </div>
        <div class="pdf-frame-wrap">
            <iframe src="{{ $pdfSrc }}" class="pdf-frame" title="PDF Cotización"></iframe>
        </div>
    </div>
</body>
</html>

