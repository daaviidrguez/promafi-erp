# Checklist de deploy — Optimización de rendimiento (2026-11-23)

**Objetivo:** que el ERP se sienta más rápido en Hostinger (Business Web Hosting / I/O) **sin cambiar el comportamiento funcional** visible al usuario (mismos datos, mismas pantallas, misma lógica de negocio).

**Contexto Hostinger:** el cuello de botella no era una intermitencia del servicio; había picos de I/O (hasta ~20480 KB/s) y endpoints Laravel lentos (cotizaciones, búsquedas, tablero, notificaciones, PDFs). Aumento temporal de recursos vigente hasta **2026-11-24 19:07 UTC**.

**Fecha del cambio en código:** 2026-11-23  
**Rama:** `main`

---

## 1. Resumen ejecutivo de lo que se hizo

| Área | Antes | Después |
|------|--------|---------|
| Folios COT/OC/EA/CC | `pluck` de todos los folios → parseo en PHP | `MAX(SUBSTRING_INDEX(...))` en MySQL |
| `/cotizaciones` | `UPDATE` de vencidas en **cada** GET | Throttle 15 min + comando Artisan + Schedule |
| Tablero / Dashboard / CxC / Notifs | N+1: 1 query NC por cada CxC | Batch `SUM(total) GROUP BY factura_id` |
| Sesión (plantilla) | `SESSION_DRIVER=file` | `SESSION_DRIVER=database` en `.env.example` |
| Búsquedas | `LOWER()` + `LIKE %q%` | Prefijos en códigos; sin `LOWER()` (collation CI) |
| Índices BD | Faltaban en nombre / catálogo / NC / CxC | Migración `2026_11_23_190000_...` |
| Notificaciones admin | Query pesada en **cada** page load | Ver **ronda 2** abajo |
| Permisos / Empresa | EXISTS por check; query empresa repetida | Keys en memoria; memo request; cache Gate 10 min |
| Acuse cancelación PDF | Timeouts 30s × varios intentos Facturama | Timeout 10s + PDF cacheado en disco |
| Autocomplete cotización | Debounce 280ms, sin cancelar fetch | 400ms + `AbortController` |

---

## 1.b Ronda 2 — lentitud al cambiar *cualquier* pantalla (2026-11-23 tarde)

**Síntoma reportado tras ronda 1:** planeo al navegar menú → menú (coste fijo por request).

| Área | Antes (post ronda 1) | Después (ronda 2) |
|------|----------------------|-------------------|
| Notificaciones | `GET /notificaciones/admin` en **cada** `DOMContentLoaded` | `GET .../resumen` solo si no hay estado en `sessionStorage` (TTL 90s); detalle completo al **abrir** la campana |
| Caché notifs | Por usuario, 45s | Compartida `notifs_admin_data_shared`, 120s |
| Permisos sidebar | `role.permissions` podía consultar por checks | `loadMissing(['role.permissions'])` en middleware de rutas |
| Fuentes | `@import` Google Fonts (Outfit/Inter) vía red | System UI local (sin request externo); rebuild Vite |

### Archivos ronda 2
- `app/Http/Controllers/Web/NotificacionesController.php` — `resumen()` + `admin()` detalle
- `routes/web.php` — ruta `notificaciones.admin.resumen`
- `resources/views/layouts/partials/header.blade.php` — fetch diferido + sessionStorage
- `app/Http/Middleware/EnsureUserHasRoutePermission.php` — preload permisos
- `resources/css/app.css`, `public/css/app.css`, `public/build/*` — sin Google Fonts

### Deploy extra ronda 2
- [ ] Subir también `public/build/` (manifest + CSS nuevo) o correr `npm run build` en servidor
- [ ] `php artisan route:cache` (ruta nueva de resumen)
- [ ] `php artisan view:cache`
- [ ] Probar: navegar 5 pantallas → no debe disparar notifs en cada una (Network: solo 1 resumen / 90s)
- [ ] Abrir campana → carga detalle; toast al primer login de sesión igual que antes

---

## 1.c Ronda 3 — listados CFDI sin blobs (LCP Facturas ~28s)

**Síntoma:** LCP ~27.85s en `/facturas` (TTFB/servidor). Causa: `SELECT *` incluía `xml_content`/sellos/acuses.

| Cambio | Detalle |
|--------|---------|
| `CfdiListadoColumnas` + `scopeParaListado` | Factura, Complemento, NC, FacturaCompra |
| Index facturas/complementos/NC/compras | Sin columnas TEXT fiscales |
| Cotizaciones / CxC / devoluciones | Eager `factura` solo columnas mínimas |
| Dashboard / búsqueda global | Facturas y compras ligeras |
| Orden | `fecha_emision` / `fecha` (indexadas) en lugar de `created_at` donde aplica |
| Migración | `2026_11_23_210000_add_created_at_indexes_for_cfdi_listados.php` |

### Deploy extra ronda 3
- [ ] `php artisan migrate --force`
- [ ] Probar `/facturas` → LCP/TTFB mucho menor; misma tabla visible
- [ ] Probar complementos, NC, compras, cotizaciones, CxC

---

## 1.d Ronda 4 — campana = aviso diario (no en vivo)

| Antes | Después |
|-------|---------|
| Fetch resumen al navegar (TTL 90s) | `localStorage` por **día**; **0 fetch** al cambiar pantallas |
| Caché datos 120s | Snapshot compartido hasta **medianoche** |
| Leído = sesión | Leído = **hasta mañana** (sesión + cache del día) |
| Toast / badge recurrentes | Toast 1× al día; badge desaparece al abrir/leer |

### Deploy extra ronda 4
- [ ] `php artisan view:cache`
- [ ] Hard refresh (Ctrl+F5) para JS del header
- [ ] Network: navegar 5 pantallas → sin `/notificaciones/*` tras el primer hit del día
- [ ] Abrir campana → badge se apaga hasta el día siguiente

---

## 1.e Ronda 5 — limpieza index facturas + show cotización

| Cambio | Motivo |
|--------|--------|
| Index facturas sin UUID bajo el folio | No aporta (truncado); UUID completo en show |
| `CfdiListadoColumnas::factura` sin `uuid` | Listado no lo usa |
| Cotización show: factura eager ligera | Evita traer XML de la factura relacionada |
| Factura edit: menos reload de relaciones | Evita `load` duplicado de cliente |

---

## 1.f Ronda 6 — producto-rápido + PDF lazy al timbrar

**Sin cambiar lógica fiscal ni TimbradoConcurrencyGuard.**

| Cambio | Detalle |
|--------|---------|
| `ProductoSimilitudCatalogo` | Misma regla (>80% + casi idénticas); solo ≤200 candidatos por LIKE/tokens (no full-scan) |
| `ProductoPsiConsecutivo` | `MAX` SQL en lugar de pluck de todos los `PSI-*` |
| Timbrar factura | PAC + XML + inventario igual; **sin DomPDF en el POST**; PDF en `verPDF`/`descargarPDF` |

### Deploy extra ronda 6
- [ ] Probar crear producto rápido (confirmación de similitud sigue funcionando)
- [ ] Timbrar: redirect rápido; abrir PDF genera archivo la 1ª vez
- [ ] Verificar que doble clic / guard de timbrado sigue activo

---

## 1.g Ronda 7 — index facturas + cotizaciones más ágiles

| Cambio | Detalle |
|--------|---------|
| Sin UPDATE vencidas en GET | Solo `schedule` / `cotizaciones:marcar-vencidas` (cron) |
| Paginación numerada (`paginate(20)`) | UX con salto de página; COUNT acotado por rango 90 días / filtros (revisar «Todas») |
| Rango default 90 días | Link «Todas» para historial completo |
| Stats cotizaciones | Cache 60s (+ invalidación al guardar/aceptar/enviar/convertir/borrar) |
| Select mínimo cotizaciones | Sin observaciones / dirección / PDF |
| Búsqueda liviana | Prefijo folio; sin `orWhereHas` en cotizaciones |
| UI index | Menos botones (XML/SAT/PDF → detalle); badge cancelación con etiqueta legible (sin N+1 SAT) |
| Índices | `(fecha_emision,id)` facturas; `(fecha,id)` cotizaciones |

### Deploy extra ronda 7
- [ ] `php artisan migrate --force` (índices compuestos)
- [ ] Cron `schedule:run` activo (vencidas ya **no** se marcan al abrir el listado)
- [ ] Probar `/facturas` y `/cotizaciones`: números de página, «Todas», búsqueda por folio

---

## 2. Archivos tocados (inventario exacto)

### Nuevos
- `app/Support/FolioNumerico.php` — helper MAX secuencia de folio
- `app/Support/CobranzaSaldoBatch.php` — batch NC + hidratar saldos
- `app/Console/Commands/MarcarCotizacionesVencidasCommand.php` — `cotizaciones:marcar-vencidas`
- `database/migrations/2026_11_23_190000_add_performance_indexes_for_search_and_cobranza.php`
- `docs/DEPLOY-RENDIMIENTO-2026-11-23.md` — este checklist

### Modificados
- `.env.example`
- `routes/console.php` — Schedule cada 15 min
- `app/Providers/AppServiceProvider.php`
- Controllers: `Cotizacion`, `Tablero`, `Dashboard`, `CuentaPorCobrar`, `Notificaciones`, `Factura`, `Role`, `Api/CatalogoOnline`
- Models: `Cotizacion`, `OrdenCompra`, `EntradaAnticipada`, `CotizacionCompra`, `CuentaPorCobrar`, `Cliente`, `Producto`, `Sugerencia`, `Role`, `Empresa`
- `app/Services/FacturamaService.php`
- `resources/views/cotizaciones/create.blade.php`

---

## 3. Checklist de deploy en Hostinger (orden obligatorio)

### A. Antes de subir (local / CI)
- [ ] Código en `main` pusheado a `origin`
- [ ] Backup de BD de producción (panel Hostinger → Databases → Export / o `mysqldump`)
- [ ] Anotar valores actuales de `.env` prod: `SESSION_DRIVER`, `CACHE_STORE`, `LOG_LEVEL`, `APP_DEBUG`, `QUEUE_CONNECTION`

### B. Subir código
- [ ] Deploy del commit de este cambio (git pull / FTP / panel File Manager / pipeline)
- [ ] Confirmar que existen en el servidor:
  - [ ] `app/Support/FolioNumerico.php`
  - [ ] `app/Support/CobranzaSaldoBatch.php`
  - [ ] `app/Console/Commands/MarcarCotizacionesVencidasCommand.php`
  - [ ] `database/migrations/2026_11_23_190000_add_performance_indexes_for_search_and_cobranza.php`

### C. Variables de entorno (`.env` de producción)
Aplicar / verificar (sin dejar `file` en sesión):

```env
SESSION_DRIVER=database
CACHE_STORE=database
LOG_LEVEL=error
APP_DEBUG=false
QUEUE_CONNECTION=sync
```

Opcional recomendado:

```env
LOG_STACK=daily
# LOG_RENDIMIENTO_UMBRAL=5
```

Checklist:
- [ ] `SESSION_DRIVER=database` (si estaba en `file`, este cambio solo ya reduce I/O)
- [ ] Existe tabla `sessions` (si no: `php artisan session:table` + migrate, o migración Laravel sessions ya corrida)
- [ ] Existe tabla `cache` / `cache_locks` para `CACHE_STORE=database`
- [ ] `APP_DEBUG=false` y `LOG_LEVEL=error`

### D. Comandos Artisan en el servidor (SSH o terminal Hostinger)

Desde la raíz del proyecto (`public_html` / carpeta Laravel, **no** solo `public/`):

```bash
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan cotizaciones:marcar-vencidas
```

Checklist:
- [ ] `migrate --force` OK (índices creados; sin error de índice duplicado)
- [ ] `config:cache` / `route:cache` / `view:cache` OK
- [ ] Comando de vencidas responde con conteo (puede ser `0`)

Si `migrate` falla por índice ya existente:
- [ ] Revisar `SHOW INDEX FROM productos;` / `clientes` / `notas_credito` / `cuentas_por_cobrar`
- [ ] No forzar drop a ciegas; alinear migración o crear solo los que falten

### E. Cron (muy recomendado)

En Hostinger → Advanced → Cron Jobs:

```text
* * * * * cd /ruta/absoluta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

Eso ejecuta `cotizaciones:marcar-vencidas` cada 15 minutos (definido en `routes/console.php`).

Checklist:
- [ ] Cron `schedule:run` creado (**obligatorio** para marcar vencidas; el index ya no hace UPDATE)
- [ ] Ruta PHP correcta (`php` o `/usr/bin/php` del panel)
- [ ] Si no hay cron: las cotizaciones pueden quedar en enviada/aceptada tras la vigencia (poco relevante operativamente); el comando manual sigue disponible

### F. Permisos / storage
- [ ] `storage/` y `bootstrap/cache/` escribibles
- [ ] Se puede crear `storage/app/documentos/acuses_cancelacion/` (el código hace `mkdir` al primer acuse)

### G. CDN (Hostinger)
- [ ] CDN activo está bien para assets estáticos
- [ ] **No** cachear páginas HTML autenticadas del ERP (Hostinger ya lo advirtió)

---

## 4. Checklist de verificación funcional (smoke test)

Tras deploy, probar en producción (idealmente con el aumento de I/O aún activo):

| # | Acción | Resultado esperado |
|---|--------|--------------------|
| 1 | Login | Sesión OK (driver database) |
| 2 | `GET /cotizaciones` | Lista en segundos, no minutos; stats visibles |
| 3 | `GET /cotizaciones/crear` | Folio preview inmediato |
| 4 | Teclear producto (código y nombre) | Sugerencias rápidas; mismos matches útiles |
| 5 | Teclear cliente | Autocomplete rápido |
| 6 | `GET /tablero` | Gráficas/KPIs coherentes con antes |
| 7 | `GET /dashboard` | Totales cobranza / vencidas coherentes |
| 8 | Campana notificaciones (navegar 2–3 páginas) | Badge/toast; 2ª carga más ligera (caché 45s) |
| 9 | CxC index totales | Pendiente / vencido coherentes con NC |
| 10 | Descargar PDF cotización existente | Sigue sirviendo archivo si `pdf_path` existe |
| 11 | Acuse cancelación (si hay factura cancelada) | 1ª vez puede tardar; 2ª descarga casi inmediata |
| 12 | API catálogo `q=` sin `buscar_descripcion` | Busca código/nombre (no descripción); con `buscar_descripcion=1` incluye descripción |

**Números de negocio a contrastar (muestra):**
- [ ] Total por cobrar tablero ≈ dashboard ≈ CxC (misma fórmula saldo − NC timbradas)
- [ ] Folio siguiente al crear cotización no choca con existente (incl. soft-deleted)

---

## 5. Rollback rápido

Si algo falla tras migrate:

1. Revertir código al commit anterior (`git revert` / redeploy tag anterior).
2. Índices nuevos son aditivos (no cambian datos); se pueden dejar o dropear:

```sql
ALTER TABLE productos DROP INDEX productos_activo_catalogo_idx;
ALTER TABLE productos DROP INDEX productos_nombre_idx;
ALTER TABLE clientes DROP INDEX clientes_activo_nombre_idx;
ALTER TABLE notas_credito DROP INDEX nc_factura_estado_idx;
ALTER TABLE cuentas_por_cobrar DROP INDEX cxc_estado_vencimiento_idx;
```

3. Restaurar `.env` (`SESSION_DRIVER`, etc.) si se cambió y hay problema de sesiones.
4. `php artisan config:cache` de nuevo.

---

## 6. Notas para un agente / futuro mantenimiento

### Comportamiento que **no** debe romperse
- `saldo_pendiente_real` = `max(0, monto_pendiente − SUM(NC timbradas de la factura))`
- Estado display CxC: cancelada → pagada (saldo≤0) → vencida → parcial → pendiente
- Folio cotización: `max(contador_empresa, max_folio_serie + 1)` incluyendo soft-deleted
- Toast de notificaciones: **una vez por sesión**; leído en sesión; datos cacheables 45s
- API catálogo: búsqueda en `descripcion` solo con `buscar_descripcion=1` (opt-in)

### Dónde mirar si vuelve la lentitud
1. Access logs Hostinger: mismos endpoints (`/cotizaciones`, `/buscar-productos`, `/tablero`, `/notificaciones/admin`, PDFs)
2. `storage/logs/rendimiento-*.log` (middleware `LogSlowRequests`, umbral default 5s)
3. Confirmar que prod **no** volvió a `SESSION_DRIVER=file`
4. Slow query log MySQL si Hostinger lo habilita
5. Tamaño de `storage/logs/laravel.log` y temps Excel

### Mejoras pendientes (no hechas en este cambio)
- Queue async para generación de PDFs pesados con muchas imágenes
- FULLTEXT / motor de búsqueda para `LIKE %texto%` en nombres largos
- Endpoint ligero `unread_count` vs payload completo de notificaciones
- Limitar histórico “más vendidos” del tablero a año en curso (sí cambiaría el gráfico; requiere OK de negocio)
- Limpieza periódica `storage/framework/cache/laravel-excel` y temps CFDI

### Comandos útiles post-deploy
```bash
php artisan cotizaciones:marcar-vencidas
php artisan schedule:list
php artisan about
```

---

## 7. Firma del cambio

- **Motivo:** diagnóstico Hostinger (I/O + endpoints lentos) + auditoría de código Laravel.
- **Criterio de éxito:** mismos flujos; tiempos de listados/búsquedas/tablero/notificaciones en el orden de segundos o menos, no minutos.
- **Documento vivo:** actualizar este archivo si se amplía la optimización o se cambia el cron/índices.
