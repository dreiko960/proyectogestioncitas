# SGCM-CMAS · Estándares de desarrollo

Convenciones de código, git, documentación y "definición de terminado" para todo el equipo. Complementa los planes técnicos (`docs/BACKEND.md`, `docs/FRONTEND.md`) y la regla de documentación de `AGENTS.md`.

---

## 1. Flujo de trabajo con git

### 1.1 Ramas

| Rama | Uso | Protegida |
|---|---|---|
| `main` | Producción; solo merges desde `staging`/hotfix con aprobación manual | Sí (CI + revisión) |
| `staging` | Integración y QA (deploy a staging) | Sí (CI) |
| `develop` | Integración de desarrollo (opcional si el equipo es pequeño) | No |
| `feature/<area>-<desc>` | Tareas: `feature/pagos-culqi`, `feature/tv-realtime` | — |
| `fix/<area>-<desc>` | Correcciones: `fix/turno-duplicado` | — |
| `hotfix/<desc>` | Corrección urgente de prod (PR directo a `main` + `staging`) | — |

**Regla:** ninguna rama `feature` toca `main` directamente; siempre pasa por CI y revisión.

### 1.2 Commits (Conventional Commits)

Formato: `<tipo>(<ámbito>): <descripción>` — descripción imperativa, en minúscula, ≤ 72 caracteres.

| Tipo | Uso | Ejemplo |
|---|---|---|
| `feat` | Nueva funcionalidad | `feat(auth): add refresh token rotation` |
| `fix` | Corrección de bug | `fix(queue): turno duplicado al hacer check-in concurrente` |
| `docs` | Solo documentación | `docs(backend): añade contrato de endpoints de pagos` |
| `refactor` | Cambio sin cambiar comportamiento | `refactor(payments): extrae CulqiProvider` |
| `test` | Pruebas | `test(citas): doble reserva devuelve 409 con alternativas` |
| `chore` | Tareas de mantenimiento | `chore(deps): actualiza typeorm a 0.3.24` |
| `perf` | Rendimiento | `perf(availability): cache Redis de franjas libres` |

**Reglas de commit:** un commit = una unidad lógica; no mezclar `feat` con `fix`; no subir secretos; mensajes en español (consistencia del repo).

### 1.3 Pull Requests

- Título con el mismo formato de Conventional Commits.
- Descripción: qué, por qué, cómo se probó (checkbox de la sección 4).
- Tamaño: PRs pequeños (< ~400 líneas) para revisión efectiva; si es grande, dividir.
- Revisión: al menos 1 aprobación; el autor no se auto-aprueba.
- Antes de merge: CI verde + sin conflictos + tests del área afectada pasando.

---

## 2. Estructura y nomenclatura de código

### 2.1 Backend (Laravel)

| Convención | Ejemplo |
|---|---|
| Un dominio por carpeta de servicios y controllers: `auth`, `appointments`, `payments`, `triage`, `queue`, `waitlist`, `reports`, `audit`, `settings` | `app/Services/AppointmentService.php` |
| Rutas API en `routes/api.php` con prefijo `/api` y middleware de rol | `Route::post('/appointments', ...)->middleware('role:paciente')` |
| Endpoints en kebab-case, plural de recursos | `GET /api/appointments/me`, `POST /api/payments/charge` |
| Validación con `FormRequest`: `CreateXxxRequest`, `UpdateXxxRequest`, `QueryXxxRequest` | `CreateAppointmentRequest` |
| Respuestas con `ApiResource` + `JsonResponse`; errores: `ConflictHttpException` (409), `NotFoundHttpException` (404), `ForbiddenHttpException` (403) | nunca `abort` sin mapear |
| Transacciones: siempre `DB::transaction(...)` para operaciones multi-tabla | reserva, check-in con turno, confirmar oferta |
| Auditoría: toda acción sensible llama a `AuditService` | login, pagos, triaje, cancelaciones, accesos denegados |
| Fechas/horas: `TIMESTAMPTZ` y formato ISO-8601 en la API (casts `datetime`) | `2026-08-05T09:00:00.000Z` |
| Eventos broadcast: transición de cola → `QueueUpdated`/`TurnCalled` (Reverb) | `QueueService` dispara eventos |

### 2.2 Frontend (React/Vite)

| Convención | Ejemplo |
|---|---|
| Un archivo por página con su CSS: `NombrePágina.jsx` + `NombrePágina.css` | `BookAppointment.jsx/.css` |
| Hooks personalizados con prefijo `use` por dominio | `useQueue.js`, `useAppointments.js` |
| Queries/mutations en el hook del módulo (React Query); la página solo consume | `useQueueDay(date)` |
| Componentes `ui/` y `Icons.jsx` **solo props**, sin API ni datos globales | `Button`, `Modal`, `Badge` |
| Navegación siempre con `Link`/`useNavigate` (nunca `<a>` interno) | panel de roles |
| Clases BEM-lite siguiendo `tokens.css` (variables `--primary`, `--st-*`) | `nav-item-active`, `mnav-item` |
| Estados de cita solo como labels de presentación (`STATUS_LABEL`); el estado real lo decide el backend | `helpers.js` |
| `es-PE` para fechas/precios (`date-fns`, `fmtPrice` S/ ) | — |

### 2.3 Nomenclatura transversal

| Concepto | Regla |
|---|---|
| IDs de negocio legibles | Citas `C-XXXX`, pagos `P-XXXX`/`R-2026-XXXX`, lista de espera `WL-XXX` |
| Estados de cita | Snake_case del prototipo (`en_espera_triaje`, `triaje_completado`) — no cambiarlos |
| Enums de BD | `user_role`, `appointment_status`, `payment_status`, `paid_type`, `waitlist_status`, `audit_sev` |
| Variables de entorno | Backend snake_case (`DB_HOST`, `JWT_ACCESS_SECRET`); frontend con prefijo `VITE_` |
| Tablas | Plural snake_case (`appointments`, `waitlist_entries`, `refresh_tokens`) |

---

## 3. Documentación obligatoria por tarea

Regla de `AGENTS.md`: **toda tarea de código se acompaña de su documentación en la misma sesión**, antes de darla por terminada:

| Cambio | Documentar en |
|---|---|
| Endpoint/regla de negocio nueva | `docs/BACKEND.md` (contrato, Parte 5/6) y `docs/REQUISITOS.md` si cambia un criterio |
| Página/componente/flujo nuevo | `docs/FRONTEND.md` (Parte 7) y `docs/MODULOS.md` (módulo afectado) |
| Cambio estructural (BD, módulos) | `README.md` (arquitectura/estructura) + `docs/BACKEND.md` Parte 2/3 |
| Cambio de configuración/despliegue | `docs/DEVOPS.md` (ambientes, pipeline) |
| Control de seguridad nuevo | `docs/SEGURIDAD.md` (checklist) |
| Caso de prueba relevante | `docs/PRUEBAS.md` (sección 3/4) |
| Cualquier cambio funcional | `README.md` en las secciones afectadas (rutas, modelo, estados, funcionalidades por rol) |

**Formato de documentación:** respetar el estilo existente (tablas, secciones numeradas, encabezados) de cada documento; no duplicar contenido entre docs — enlazar (ej. `ver BACKEND.md Parte 7`).

---

## 4. Definición de terminado (Definition of Done)

Una tarea **no está terminada** si no cumple todo esto:

- [ ] Código implementado siguiendo las convenciones de la sección 2.
- [ ] Build verificado: backend `php artisan` + migraciones OK · frontend `npm.cmd run build` (regla de AGENTS.md).
- [ ] Lint sin errores y sin warnings nuevos (`pint` backend, ESLint frontend).
- [ ] Pruebas: casos obligatorios de `docs/PRUEBAS.md` §3 del área afectada escritos y pasando; cobertura sin bajar.
- [ ] CI verde en la rama (PR).
- [ ] Caso manual comprobado (flujo relevante de `PRUEBAS.md` §4 o checklist §7).
- [ ] Documentación actualizada según la sección 3 (misma sesión).
- [ ] Sin secretos ni datos sensibles en el código (revisión + escaneo CI).
- [ ] Sin código muerto ni comentarios de desarrollo (no se añaden comentarios salvo que se pidan).
- [ ] Merge aprobado a `staging` (y a `main` con el checklist de `DEVOPS.md` §7).

---

## 5. Entorno y comandos

| Acción | Comando (Windows) |
|---|---|
| Backend: instalar dependencias | `composer install` (en `backend/`) |
| Backend: servidor de desarrollo | `php artisan serve` (puerto 8000) |
| Backend: tests | `php artisan test` / `php artisan test --filter=<Area>` |
| Backend: migraciones | `php artisan migrate` / `php artisan make:migration <Nombre>` |
| Backend: seed | `php artisan db:seed` (dev/test) |
| Backend: colas en dev | `php artisan queue:work redis` (o `php artisan horizon`) |
| Backend: WebSockets | `php artisan reverb:start` |
| Backend: calidad | `vendor/bin/pint --test` / `vendor/bin/pint` |
| Frontend: dev | `npm run dev` (puerto 5173) |
| Frontend: build | `npm.cmd run build` (**no** `npm.ps1`, bloqueado por ExecutionPolicy) |
| Frontend: tests | `npm run test` (Vitest) |
| BD local | `docker compose up -d postgres redis` |

---

## 6. Revisiones de código (checklist del reviewer)

- [ ] ¿Respeta el contrato de BACKEND.md Parte 5? ¿FormRequests validados y mapeados a los errores correctos?
- [ ] ¿Las transacciones usan `DB::transaction`? ¿Hay condiciones de carrera (turnos, doble reserva, ofertas)?
- [ ] ¿Se emiten los eventos de broadcast correctos (cola/TV/notificaciones)?
- [ ] ¿La auditoría cubre la acción sensible? ¿Severidad correcta?
- [ ] ¿Frontend: React Query (no llamadas sueltas), errores mapeados a toast/vista, sin datos hardcodeados?
- [ ] ¿Sin secretos, sin comentarios innecesarios, sin código muerto?
- [ ] ¿Documentación actualizada en la misma PR?