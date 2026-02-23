@extends('layouts.app')

@section('title', 'Mi Perfil')
@section('page-title', '👤 Mi Perfil')
@section('page-subtitle', 'Administra tu información personal y contraseña')

@section('breadcrumbs')
    <span class="breadcrumb-separator">›</span>
    <span class="breadcrumb-current">Mi Perfil</span>
@endsection

@section('content')

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">

    {{-- Columna izquierda — Avatar + info rápida --}}
    <div>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 32px 20px;">

                {{-- Foto de perfil (arriba del nombre) --}}
                <div class="perfil-avatar-wrap">
                    @if(auth()->user()->avatar)
                        <img src="{{ asset('storage/' . auth()->user()->avatar) }}"
                             alt="Foto de perfil"
                             class="perfil-avatar-img">
                    @else
                        <div class="perfil-avatar-iniciales">
                            {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                        </div>
                    @endif
                </div>

                <div class="perfil-nombre">{{ auth()->user()->name }}</div>
                <div class="text-muted perfil-email">{{ auth()->user()->email }}</div>
                <div class="perfil-rol">
                    @if(auth()->user()->role)
                        <span class="badge badge-primary">👤 {{ auth()->user()->role->display_name }}</span>
                    @else
                        <span class="badge badge-gray">👤 Usuario</span>
                    @endif
                </div>

                <div style="text-align: left; padding-top: 16px; border-top: 1px solid var(--color-gray-100);">
                    <div class="info-row">
                        <div class="info-label">Miembro desde</div>
                        <div class="info-value-sm">{{ auth()->user()->created_at->format('d/m/Y') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Último acceso</div>
                        <div class="info-value-sm">
                            {{ auth()->user()->last_login_at ? auth()->user()->last_login_at->format('d/m/Y H:i') : 'Hoy' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Columna derecha --}}
    <div>

        {{-- Datos Personales --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Datos Personales</div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('perfil.update') }}" enctype="multipart/form-data"
                      id="formDatos">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label class="form-label">Nombre Completo <span class="req">*</span></label>
                        <input type="text" name="name" class="form-control"
                               value="{{ old('name', auth()->user()->name) }}" required>
                        @error('name')
                            <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Correo Electrónico <span class="req">*</span></label>
                        <input type="email" name="email" class="form-control"
                               value="{{ old('email', auth()->user()->email) }}" required>
                        @error('email')
                            <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Foto de Perfil</label>
                        <input type="file" name="avatar" class="form-control" accept="image/*"
                               onchange="previewAvatar(this)">
                        <span class="form-hint">JPG, PNG o GIF — máximo 2MB</span>

                        {{-- Preview --}}
                        <div id="avatarPreview" style="display: none; margin-top: 12px;">
                            <img id="previewImg"
                                 style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover;
                                        border: 2px solid var(--color-primary);">
                            <span class="text-muted" style="font-size: 12px; margin-left: 10px;">
                                Vista previa
                            </span>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary">✓ Guardar Cambios</button>
                    </div>

                </form>
            </div>
        </div>

        {{-- Cambiar Contraseña --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🔒 Cambiar Contraseña</div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('perfil.password') }}" id="formPassword">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label class="form-label">Contraseña Actual <span class="req">*</span></label>
                        <input type="password" name="current_password" id="currentPass" class="form-control"
                               autocomplete="current-password" required>
                        @error('current_password')
                            <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nueva Contraseña <span class="req">*</span></label>
                            <input type="password" name="password" id="newPass" class="form-control"
                                   autocomplete="new-password" required minlength="8"
                                   oninput="checkStrength(this.value)">
                            @error('password')
                                <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                            @enderror

                            {{-- Barra de fortaleza --}}
                            <div id="strengthBar" style="height: 4px; border-radius: 4px; margin-top: 6px;
                                                          background: var(--color-gray-200); overflow: hidden;">
                                <div id="strengthFill"
                                     style="height: 100%; width: 0; transition: all .3s; border-radius: 4px;"></div>
                            </div>
                            <span id="strengthText" class="form-hint"></span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirmar Contraseña <span class="req">*</span></label>
                            <input type="password" name="password_confirmation" id="confirmPass"
                                   class="form-control" autocomplete="new-password" required
                                   oninput="checkMatch()">
                            <span id="matchText" class="form-hint"></span>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary">🔒 Cambiar Contraseña</button>
                    </div>

                </form>
            </div>
        </div>

        {{-- Zona de peligro --}}
        <div class="card" style="border-left: 4px solid var(--color-danger);">
            <div class="card-header">
                <div class="card-title" style="color: var(--color-danger);">⚠️ Zona de Peligro</div>
            </div>
            <div class="card-body">
                <p class="text-muted" style="margin-bottom: 16px; font-size: 13px;">
                    Cerrar sesión en todos los dispositivos donde hayas iniciado sesión con tu cuenta.
                </p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger">🚪 Cerrar Sesión en Todos los Dispositivos</button>
                </form>
            </div>
        </div>

    </div>
</div>

@endsection

@push('styles')
<style>
.perfil-avatar-wrap { margin-bottom: 16px; }
.perfil-avatar-img {
    width: 100px; height: 100px; border-radius: 50%; object-fit: cover;
    border: 3px solid var(--color-primary); box-shadow: var(--shadow-md);
}
.perfil-avatar-iniciales {
    width: 100px; height: 100px; border-radius: 50%; margin: 0 auto;
    background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
    display: flex; align-items: center; justify-content: center;
    font-size: 42px; font-weight: 800; color: #fff;
    border: 3px solid var(--color-primary); box-shadow: var(--shadow-md);
}
.perfil-nombre { font-size: 18px; font-weight: 700; color: var(--color-dark); margin-bottom: 4px; }
.perfil-email { font-size: 13px; margin-bottom: 8px; }
.perfil-rol { margin-bottom: 20px; }
</style>
@endpush

@push('scripts')
<script>
// Preview de avatar antes de subir
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('avatarPreview').style.display = 'flex';
            document.getElementById('avatarPreview').style.alignItems = 'center';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Fortaleza de contraseña
function checkStrength(val) {
    const fill = document.getElementById('strengthFill');
    const text = document.getElementById('strengthText');
    let score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { pct: '25%',  color: 'var(--color-danger)',  label: 'Muy débil' },
        { pct: '50%',  color: 'var(--color-warning)', label: 'Débil' },
        { pct: '75%',  color: 'var(--color-info)',    label: 'Buena' },
        { pct: '100%', color: 'var(--color-success)', label: '✓ Muy fuerte' },
    ];
    const lvl = levels[Math.max(0, score - 1)];
    if (val.length === 0) { fill.style.width = '0'; text.textContent = ''; return; }
    fill.style.width = lvl.pct;
    fill.style.background = lvl.color;
    text.textContent = lvl.label;
    text.style.color = lvl.color;
}

// Verificar coincidencia
function checkMatch() {
    const newPass  = document.getElementById('newPass').value;
    const confirm  = document.getElementById('confirmPass').value;
    const text     = document.getElementById('matchText');
    if (!confirm) { text.textContent = ''; return; }
    if (newPass === confirm) {
        text.textContent = '✓ Las contraseñas coinciden';
        text.style.color = 'var(--color-success)';
    } else {
        text.textContent = '✗ Las contraseñas no coinciden';
        text.style.color = 'var(--color-danger)';
    }
}

// Validar antes de enviar contraseña
document.getElementById('formPassword').addEventListener('submit', function(e) {
    const newPass = document.getElementById('newPass').value;
    const confirm = document.getElementById('confirmPass').value;
    if (newPass !== confirm) {
        e.preventDefault();
        alert('⚠️ Las contraseñas no coinciden.');
    }
});
</script>
@endpush