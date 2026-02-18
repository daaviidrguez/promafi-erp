{{-- UBICACIÓN: resources/views/login.blade.php --}}

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ERP Promafi</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <img src="{{ asset('images/logo-promafi.png') }}" alt="Promafi Logo">
                <h1>ERP Comercial</h1>
                <p>Sistema de Gestión Empresarial</p>
            </div>

            {{-- Mostrar errores --}}
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>⚠️ Error:</strong> {{ $errors->first() }}
                </div>
            @endif

            {{-- Formulario de Login --}}
            <form method="POST" action="{{ route('login.submit') }}" id="loginForm">
                @csrf
                
                <div class="form-group">
                    <label class="form-label" for="email">Correo Electrónico</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="admin@promafi.mx"
                        value="{{ old('email') }}"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Contraseña</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="••••••••"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary">
                    <span>🔐</span>
                    <span>Iniciar Sesión</span>
                </button>
            </form>

            <div style="margin-top: 24px; text-align: center; font-size: 13px; color: var(--color-gray-600);">
                <p>© {{ date('Y') }} Promafi. Todos los derechos reservados.</p>
            </div>
        </div>
    </div>

    <script>
        // Opcional: Agregar animación al submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '<span>⏳</span> <span>Validando...</span>';
            btn.disabled = true;
        });
    </script>
</body>
</html>