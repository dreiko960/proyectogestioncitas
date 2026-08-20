# SGCM-CMAS · Frontend — Documento de implementación

Documento integral para llevar el **frontend del prototipo a producción**, conectado al backend descrito en [`docs/BACKEND.md`](BACKEND.md). Organizado en partes secuenciales: estructura, estado global, autenticación, integración de APIs, módulos por rol, TV, pagos Culqi, design system, PWA, seguridad, despliegue y roadmap.

> **Alcance:** solo frontend (SPA React + pantalla TV). El backend se consume como API REST + WebSocket; este documento respeta los componentes, rutas, roles y reglas del prototipo actual para no rediseñar lo que ya funciona.

---

## Parte 0 · Visión general

### 0.1 Estado actual (prototipo)

- SPA **React 18 + Vite 5.4 + React Router 6** (JSX, sin TypeScript), 109 archivos en `src/`.
- Estado global en **Context** (`AppContext.jsx`) con datos simulados de `data/mock.js`; persistencia solo de citas en `localStorage` (`procitas-appointments-v1`) y sincronización entre pestañas vía evento `storage`.
- 42+ rutas en `App.jsx`, 5 paneles definidos en `PanelLayout.jsx` (`NAV` por rol), sin protección real de rutas.
- Pasarela de pago **simulada** (`PaymentGateway.jsx`), PDFs reales con `jspdf` (`clinicPdf.js`), TV con auto-demo.
- Sistema de diseño tokenizado (`tokens.css`) + biblioteca UI propia (`src/components/ui/`) + galería viva en `/componentes`.

### 0.2 Qué cambia en producción

| Hoy (prototipo) | Producción |
|---|---|
| Datos de `mock.js` vía Context | Datos del backend (API REST) vía **React Query** |
| `localStorage` + evento `storage` | API + **WebSocket (Laravel Reverb / laravel-echo)** — cola y TV en vivo entre dispositivos |
| Heurística de rol por correo | Rol real del JWT (sesión del backend) |
| Rutas sin protección | **Guard por rol** en `PanelLayout` + redirect a `/login` |
| Pago simulado | **Culqi.js** (tokenización real en el navegador) |
| Fechas fijas (`TODAY = '2026-08-05'`) | Fechas dinámicas del servidor |
| `jspdf` local | Descarga de PDFs con **URL firmada** del backend (S3) |
| IDs aleatorios `C-XXXX` | IDs/`code` reales de la BD |
| Login en 0.5 s (demo) | Login real con token + refresh automático |

### 0.3 Principios de diseño

1. **El frontend no decide estados ni reglas**: solo muestra lo que la API devuelve y dispara acciones; los estados (`STATUS_LABEL`) se mantienen como *labels* de presentación.
2. **Data fetching con React Query**: cache, invalidation y estados de carga/error estandarizados; el Context queda solo para UI (toasts, modales).
3. **Un solo cliente HTTP** con interceptores (token, refresh, errores unificados del backend).
4. **Tiempo real vía Reverb (WebSockets + laravel-echo)** solo donde aporta (cola, TV, notificaciones); el resto es REST.
5. **El design system actual se conserva**: tokens, componentes `ui/` y estilos por página pasan a producción casi intactos.
6. **Accesibilidad y PWA**: la TV es kiosco fullscreen; el resto del sistema es instalable y usable en móvil.

---

## Parte 1 · Stack y herramientas

| Capa | Tecnología | Justificación |
|---|---|---|
| Framework | **React 18.3** + JSX (mantener; migración opcional a TS en fases) | Reutiliza el 100 % del prototipo |
| Build | **Vite 5.4** (`@vitejs/plugin-react`) | Ya configurado (`server.port 5173`) |
| Enrutado | **React Router 6** (`BrowserRouter` + `Outlet`) | Estructura de `App.jsx` ya lista |
| Data fetching | **TanStack Query v5** | Reemplaza los `useMemo`/Context de datos |
| Cliente HTTP | **axios** (interceptores) | Refresh automático + manejo de errores |
| Estado UI | React Context (solo UI) + **Zustand** (opcional, para TV/notificaciones) | Context actual es suficiente; Zustand si crece |
| Formularios | **React Hook Form** + `zod` (validación compartida con backend) | Los formularios actuales validan a mano |
| Tiempo real | **laravel-echo + pusher-js** (backend: Laravel Reverb) | Cola + TV + notificaciones |
| Pagos | **Culqi.js** (script oficial) + `@culqi/culqi-js` | Tokenización en navegador |
| PDF | **jspdf** (solo vista previa) → el oficial lo genera el backend | El historial/ficha final sale de `GET /documents` |
| Fechas | **date-fns** con `es-PE` | Reemplaza helpers hardcodeados (`dayLabel`, `genWeek`) |
| Iconos | Set SVG propio (`Icons.jsx`) | Ya existe, sin dependencia |
| Tipografía | **Manrope** (Google Fonts) | Ya en `index.html` |
| Calidad | ESLint + Prettier + **Vitest/Testing Library** | Pruebas de componentes y flujos |
| Despliegue | **Netlify** (`netlify.toml` + `public/_redirects`) | Ya configurado; se agregan env vars |

### Dependencias nuevas (sobre las actuales)

```json
{
  "dependencies": {
    "@tanstack/react-query": "^5",
    "axios": "^1",
    "react-hook-form": "^7",
    "zod": "^3",
    "laravel-echo": "^1.16", "pusher-js": "^8",
    "date-fns": "^3"
  },
  "devDependencies": {
    "vitest": "^1", "@testing-library/react": "^14", "@testing-library/user-event": "^14", "jsdom": "^24"
  }
}
```

### Variables de entorno frontend (`.env` en Vite, prefijo `VITE_`)

```env
VITE_API_URL=https://api.cmas.pe/api
VITE_WS_URL=wss://api.cmas.pe
VITE_CULQI_PUBLIC_KEY=pk_test_...
VITE_FRONTEND_URL=https://cmas.pe
```

> NUNCA poner `sk_*` de Culqi ni secretos de backend en el frontend.

---

## Parte 2 · Estructura del proyecto (producción)

```
proCitas/
├── index.html / vite.config.js / netlify.toml / public/_redirects
├── .env / .env.example
└── src/
    ├── main.jsx                    # BrowserRouter + QueryClientProvider + AuthProvider + ToastProvider
    ├── App.jsx                     # rutas públicas + rutas protegidas por rol (Routes → PanelLayout)
    ├── api/
    │   ├── client.js               # axios instance + interceptores (token/refresh/errores)
    │   ├── endpoints.js            # centraliza rutas del backend (contrato Parte 6)
    │   └── realtime.js              # cliente laravel-echo (cola, TV, notificaciones)
    ├── auth/
    │   ├── AuthContext.jsx         # sesión: login/logout/refresh, user + role
    │   ├── RequireAuth.jsx         # guard por rol (wrap de rutas)
    │   └── useAuth.js
    ├── hooks/
    │   ├── useAppointments.js      # queries + mutations por módulo
    │   ├── usePayments.js
    │   ├── useQueue.js             # subscribe al socket + invalidar queries
    │   ├── useWaitlist.js
    │   ├── useCountdown.js         # (se conserva) ofertas de cupo
    │   └── useToast.js             # (se conserva)
    ├── components/
    │   ├── Icons.jsx               # (se conserva)
    │   ├── PageHeader.jsx · AppointmentCard.jsx · PaymentGateway.jsx → se conecta a Culqi
    │   ├── layout/  (PanelLayout · AuthLayout · Logo)  # + guard por rol
    │   ├── ui/      # biblioteca completa (se conserva)
    │   └── shared/  # nuevos: LoadingState · ErrorState · EmptyState reutilizables
    ├── pages/
    │   ├── public/     # Landing, Login, Register, Recuperar, Disponibilidad, Componentes
    │   ├── patient/ · doctor/ · nurse/ · reception/ · admin/   # mismos archivos, datos desde API
    │   ├── queue/      # WaitingQueue (socket)
    │   └── display/    # TvDisplay (kiosco, socket)
    ├── styles/         # tokens.css · global.css · css por página (se conservan)
    ├── utils/
    │   ├── helpers.js  # solo presentación: fmtPrice, fmtDate, STATUS_LABEL, fmtPayType…
    │   ├── formats.js  # date-fns es-PE (reemplaza dayLabel/genWeek/hardcodes)
    │   └── validation.js  # schemas zod compartidos
    └── test/           # setup de Vitest + factories de mocks del API
```

**Regla**: los componentes `ui/` y `Icons.jsx` no importan datos ni API (solo props) → se conservan sin cambios.

---

## Parte 3 · Estado global y data fetching

### 3.1 Reemplazo de `AppContext` por capas

| Prototipo | Producción |
|---|---|
| `AppContext.jsx` (datos + acciones + helpers de cola) | Se divide: **React Query** (datos) + **AuthContext** (sesión) + Context UI (toasts/modales) |
| `appointments, payments, waitlist…` del context | `useQuery` por módulo con `staleTime` corto para cola |
| `bookAppointment`, `sendToTriage`… | `useMutation` → POST/PATCH a la API → `invalidateQueries` |
| `localStorage` + evento `storage` | **eliminado**: el servidor es la fuente; el socket actualiza cola/TV |
| `resetDemo` | se elimina (no existe en producción) |

### 3.2 Patrón por módulo

```jsx
// hooks/useQueue.js
export function useQueueDay(date) {
  const queryClient = useQueryClient()
  const { data } = useQuery({
    queryKey: ['queue', date],
    queryFn: () => api.get(`/queue/day?date=${date}`).then((r) => r.data.data),
    refetchInterval: 15_000,          // respaldo si el WebSocket falla
  })
  useEffect(() => {
    // Laravel Reverb: canal privado por consultorio (pusher protocol)
    const channel = echo
      .private(`queue.consultorio.${consultorioId}`)
      .listen('.QueueUpdated', () => queryClient.invalidateQueries(['queue']))
    return () => echo.leaveChannel(channel.name)
  }, [queryClient])
  return data
}
```

### 3.3 Estado de carga/error estándar

- `LoadingState` (skeleton/spinner) y `ErrorState` (mensaje + botón reintentar) compartidos; el backend devuelve `{ statusCode, message[], error }` → se traduce a toast + vista de error.
- `EmptyState` ya existe y se reutiliza tal cual.

---

## Parte 4 · Autenticación en el frontend

### 4.1 Flujo

1. `Login.jsx` envía `{ email, password }` a `POST /auth/login` (real, sin los 700 ms/0.5 s simulados).
2. `AuthContext` guarda `accessToken` (memoria) y `refreshToken` (httpOnly cookie o `localStorage` según decisión de seguridad; recomendado: cookie `HttpOnly` con `SameSite=Lax`).
3. `axios` interceptor agrega `Authorization: Bearer <access>` y, en `401`, intenta `POST /auth/refresh` (cola de reintentos); si falla → logout + redirect a `/login`.
4. `auth.user` incluye `role`, `name`, `initials`, `patientId`/`doctorId` según rol → `PanelLayout` renderiza el `NAV[role]` actual sin heurística.
5. Botones de "acceso rápido demo" se eliminan de producción (o quedan solo en staging con credenciales de prueba).

### 4.2 Guards

- `RequireAuth` envuelve `<PanelLayout>`: sin sesión → `<Navigate to="/login" state={{ from }} />`; con sesión pero rol no autorizado para la ruta → `<Navigate to="/" />` + auditoría del acceso denegado (el backend también lo valida; el guard es solo UX).
- `RecoverPassword`/`NewPassword` usan el token del query param (enlace del email).

### 4.3 Sesión persistente

- Al recargar la página: si hay refresh cookie → `POST /auth/refresh` silencioso antes de renderizar (splash breve). El `localStorage` de sesión del prototipo se elimina.

---

## Parte 5 · Enrutado (rutas actuales → producción)

`App.jsx` conserva la estructura; cambia la agrupación:

```jsx
<Routes>
  {/* Públicas (sin sesión) */}
  <Route path="/" element={<Landing />} />
  <Route path="/login" element={<Login />} />
  <Route path="/registro" element={<Register />} />
  <Route path="/recuperar" element={<RecoverPassword />} />
  <Route path="/recuperar/confirmacion" element={<RecoverPassword step="sent" />} />
  <Route path="/recuperar/nueva-password" element={<NewPassword />} />
  <Route path="/disponibilidad" element={<SearchAvailability />} />
  <Route path="/componentes" element={<Components />} />

  {/* TV kiosko (token de solo lectura, sin sesión de empleado) */}
  <Route path="/tv" element={<TvDisplay />} />

  {/* Panel por rol */}
  <Route element={<RequireAuth />}>            {/* ← guard nuevo */}
    <Route element={<PanelLayout />}>
      {/* mismo árbol de rutas por rol: /paciente*, /medico*, /enfermeria*, /recepcion*, /admin* */}
    </Route>
  </Route>

  <Route path="*" element={<Navigate to="/" replace />} />
</Routes>
```

Además:

- Cada panel define sus rutas con **`@Roles('paciente')`** como atributo de metadatos (se valida contra `auth.user.role`; si no coincide → redirect al home del rol).
- `ROLE_HOME = { paciente: '/paciente', medico: '/medico', enfermera: '/enfermeria', recepcionista: '/recepcion', administrador: '/admin' }` tras login/refresh.
- La pantalla `/tv` usa su propio token (`POST /tv/token`) almacenado en `sessionStorage` con reintento de suscripción.

---

## Parte 6 · Integración con el backend (contrato desde el frontend)

### 6.1 Cliente HTTP (`api/client.js`)

```js
const api = axios.create({ baseURL: import.meta.env.VITE_API_URL })

api.interceptors.request.use((cfg) => {
  const token = getAccessToken()
  if (token) cfg.headers.Authorization = `Bearer ${token}`
  return cfg
})

api.interceptors.response.use(
  (r) => r,
  async (err) => {
    const original = err.config
    if (err.response?.status === 401 && !original._retry) {
      original._retry = true
      try { await refreshSession(); return api(original) }
      catch { logout(); window.location = '/login' }
    }
    throw normalizeError(err)   // → { message[], statusCode }
  },
)
```

### 6.2 Mapeo de errores del backend → UX

| Backend | Frontend |
|---|---|
| `409` reserva con `alternatives[]` | Modal "Este horario ya no está disponible" + 3 alternativas (lógica ya existente en `BookAppointment.jsx`) |
| `401/403` | Toast + redirect según caso; auditoría de acceso denegado ya visible en admin |
| `422` (validación DTO) | Mapeo de errores a campos del formulario (React Hook Form + zod) |
| `429` (rate limit) | Pantalla "Demasiados intentos, espera X segundos" |
| `404/500` | `ErrorState` + `request-id` del header para soporte |

### 6.3 Endpoints consumidos por página (resumen — contrato completo en BACKEND.md Parte 5)

| Página (prototipo) | Endpoints |
|---|---|
| `SearchAvailability` | `GET /availability` |
| `BookAppointment` | `GET /doctors/:id/slots` · `POST /appointments` · `POST /payments/charge` |
| `MyAppointments` | `GET /appointments/me` · `PATCH /appointments/:id/cancel|reschedule` |
| `PatientCheckin` | `POST /appointments/:id/checkin` |
| `PatientHistory` | `GET /appointments/patient/:pid` · `GET /documents/.../pdf` |
| `Waitlist*` | `POST /waitlist` · `GET /waitlist/me` · `POST /waitlist/:id/confirm|reject` |
| `PatientPayments` | `GET /payments/me` · `POST /payments/verify` (recepción) |
| `Agenda (médico/recepción)` | `GET /appointments/day` |
| `Availability` | `GET /doctors/:id/schedules` · `POST .../exceptions` |
| `Diagnosis` / `TriageForm` | `GET /appointments/:id` · `POST /triage/...` · `PATCH /triage/:id/complete` |
| `WaitingQueue` | `GET /queue/day` · transiciones `POST /queue/:id/*` |
| `TvDisplay` | `POST /tv/token` · socket `queue.updated` |
| `Admin*` | `GET /users` · `GET/PATCH /specialties` · `GET /consultorios` · `GET /reports/*` · `GET /audit` · `GET/PATCH /settings` |

---

## Parte 7 · Módulos por rol (qué se conserva y qué se conecta)

| Módulo | Archivos | Cambio en producción |
|---|---|---|
| Landing / Público | `Landing.jsx`, `SearchAvailability.jsx` | Especialidades y disponibilidad desde API; fechas dinámicas |
| Autenticación | `Login.jsx`, `Register.jsx`, `RecoverPassword.jsx`, `NewPassword.jsx` | Login real (Parte 4); registro valida DNI único (RENIEC del backend) y consentimiento Ley 29733 (checkbox + texto del prototipo) |
| Paciente | 11 páginas | Todos los datos vía hooks; eliminar `ME`/`TODAY` hardcodeados; `useCountdown` se mantiene para ofertas (expiración real la confirma el servidor con `offer_expires_at`) |
| Médico | 5 páginas | Agenda timeline con datos reales; relación clínica (403 del backend se muestra como pantalla de denegado) |
| Enfermería | 3 páginas | Cola vía `GET /triage/queue` + socket |
| Recepción | 6 páginas | Check-in asigna turno (respuesta del backend); pagos con verificación real |
| Admin | 7 páginas | Reportes/auditoría reales; exportación CSV descarga archivo real |
| Cola + TV | `WaitingQueue.jsx`, `TvDisplay.jsx` | Suscripción Reverb/laravel-echo (Parte 8); el "Modo automático demo" se elimina (o queda en staging) |

---

## Parte 8 · Pantalla TV (kiosco, tiempo real)

### 8.1 Conectividad

- `TvDisplay.jsx` se conserva en visual; la fuente de datos cambia:
  1. `POST /tv/token` (clave de consultorio) → token Sanctum con ability `tv:read` en `sessionStorage`.
  2. `GET /queue/day?date=hoy` → render inicial.
  3. **laravel-echo** (protocolo Pusher, servidor Laravel Reverb) → canal privado `queue.consultorio.{id}`; eventos `QueueUpdated` (recarga payload) y `TurnCalled` (animación del turno en grande).
  4. Reconexión automática de Echo con backoff + `GET /queue/day` al reconectar.

### 8.2 Payload esperado (igual a la estructura actual de `TvDisplay`)

```json
{
  "now": { "triage": { "turno": "A-004", "name": "…", "consultorio": "Consultorio 2" } | null,
           "consulta": { "turno": "A-003", "name": "…", "consultorio": "Consultorio 1" } | null },
  "next": [ { "turno": "A-005", "name": "…", "consultorio": "…", "appointmentAt": "09:30" } ],
  "rest": [ … ], "attendedToday": 12, "date": "2026-08-05"
}
```

### 8.3 Consideraciones

- **Sin sesión de empleado**: token de solo lectura; si expira → re-solicitar con la clave.
- Multi-pantalla por piso: query `?consultorioId=` en `/tv` (admin lo configura desde Consultorios).
- Fullscreen y oscuro: CSS actual se conserva; se añade `kiosk` flag (`?kiosk=1`) que oculta botones de control.

---

## Parte 9 · Pagos con Culqi (frontend)

### 9.1 Flujo (`PaymentGateway.jsx` conectado)

```
1. Paciente elige Abono 50% / Pago total / Pago en caja (UI actual)
2. Si elige en línea → se carga Culqi.js (pk_ de .env)
3. Culqi abre su modal → tokeniza la tarjeta → devuelve { token }
4. Frontend NO ve ni guarda datos de tarjeta
5. POST /payments/charge { appointmentId, type, culqiToken }
6. Respuesta: payment pagado + cita pagada → pantalla de éxito (OP-2026-XXXX = order_id real)
```

### 9.2 Manejo de estados

- `Culqi.on('token')` → enviar; `Culqi.on('error')` → toast con el mensaje de Culqi (`error.message`).
- Deshabilitar botón durante el procesamiento (evita doble cobro).
- Si el charge devuelve `409`/timeout → **verificar estado antes de reintentar**: `GET /payments/:appointmentId` (evita cobros duplicados; el backend es idempotente por `culqi_order_id`).
- `order.expired` (webhook) → el frontend lo ve al recargar "Mis citas": la cita vuelve a `agendada` con aviso de pagar en caja.

---

## Parte 10 · PDFs y descargas

| Documento | Flujo en producción |
|---|---|
| Historial clínico (paciente) | Botón → `GET /documents/historial` → backend genera PDF con membrete (`clinic.js`) → sube a S3 → devuelve **URL firmada** → el frontend abre/descarga |
| Ficha del paciente (médico) | Igual con `GET /documents/ficha/:pid` (regla de relación clínica en el backend) |
| Comprobante de pago | `GET /payments/receipts/:id` → URL firmada |
| Reportes CSV/PDF (admin) | `GET /reports/export?type=csv` → descarga directa (`content-disposition`) |

- Se elimina `jspdf` del bundle principal (se cargaba con `import()`); queda opcional solo para vista previa offline.
- Los helpers `fmtPrice`, `fmtDate`, `STATUS_LABEL`, `fmtPayType`, `paidTotalOf` se conservan para presentación (el cálculo del total lo ratifica el backend).

---

## Parte 11 · Design system (se conserva)

| Pieza | Estado | Nota |
|---|---|---|
| `tokens.css` (paleta teal, acento ámbar, neutrales, estados) | Conservar | Variables `--st-*` por estado de cita intactas |
| `global.css` (utilidades, animaciones, responsive ≤768/≤480) | Conservar | Agregar clases de estados de carga/error nuevos |
| Biblioteca `ui/` (Button, Badge, Card, Field, Modal, Tabs, Misc, EmptyState, Toast) | Conservar | Solo props; sin tocar |
| `PageHeader`, `AppointmentCard`, `Icons.jsx` | Conservar | Sin cambios |
| `PaymentGateway.jsx` | **Modificar** | Conectar a Culqi (Parte 9) |
| Galería `/componentes` | Conservar | Puede agregar nuevos estados (carga/error/429) |
| Tipografía Manrope | Conservar | `index.html` |
| Dark/contraste TV | Conservar | Oscuro de alto contraste |

---

## Parte 12 · PWA, responsive y accesibilidad

1. **PWA** (fase final): manifest (`public/manifest.webmanifest`), service worker (Vite `vite-plugin-pwa`) con cache de la app shell y **estrategia network-first** para datos; instalable en móvil de pacientes y tablets de recepción/enfermería.
2. **Responsive**: el sistema actual ya cubre ≤768 px (drawer + bottom-nav con los primeros 5 ítems del `NAV`) y ≤480 px (modales bottom-sheet); se conserva y se audita con las páginas conectadas a API.
3. **Accesibilidad**: etiquetas `aria-label` (ya presentes en drawer/burger), contraste de la TV (alto contraste ya aplicado), estados `disabled`/`aria-busy` en botones de pago, `lang="es"` ya en `index.html`.
4. **Rendimiento**: código dividido por rol con `React.lazy` (hoy todo se importa en `App.jsx`); `jspdf` fuera del bundle; fonts con `display=swap`.

---

## Parte 13 · Seguridad frontend

| Control | Implementación |
|---|---|
| Token | Access en memoria; refresh en cookie `HttpOnly` + `SameSite=Lax` (configurar CORS del backend a `FRONTEND_URL`) |
| Datos de tarjeta | Nunca tocan el frontend (Culqi modal) |
| XSS | React escapa por defecto; validar con zod lo que se renderiza desde la API; CSP en `netlify.toml` headers |
| Errores | No mostrar stack/detalles internos; usar `message[]` del backend |
| Rutas | Guard por rol (Parte 4); nunca ocultar solo por UI — el backend siempre valida |
| Credenciales | `VITE_*` solo públicas; auditoría de secretos en CI (guardrail) |
| TV | Token de solo lectura, sin acceso a paneles |

---

## Parte 14 · Despliegue (Netlify)

```toml
# netlify.toml (se extiende)
[build]
  command = "npm run build"
  publish = "dist"

[build.environment]
  NODE_VERSION = "20"

[[redirects]]
  from = "/*"
  to = "/index.html"
  status = 200
```

- **Env vars en Netlify**: `VITE_API_URL`, `VITE_WS_URL`, `VITE_CULQI_PUBLIC_KEY` (dashboard de Netlify, no en el repo).
- **Headers de seguridad** en `netlify.toml`: `X-Frame-Options: DENY` (excepto `/tv` si se embebe), `Referrer-Policy`, `Content-Security-Policy`.
- **Preview/staging**: ramas `staging` → deploy preview apuntando al backend de staging.
- **PWA**: `public/manifest.webmanifest` + `_headers` para el service worker.

---

## Parte 15 · Roadmap de implementación (frontend)

| Fase | Contenido | Depende de |
|---|---|---|
| **0 · Base** | axios client + interceptores, QueryClientProvider, AuthContext vacío, rutas protegidas | — |
| **1 · Auth UI** | Login/Registro/Recuperar conectados, refresh automático, guards por rol | 0, backend Auth |
| **2 · Catálogos** | Especialidades, consultorios, doctores, disponibilidad pública desde API | 1 |
| **3 · Citas** | Reservar (con 409 + alternativas), Mis citas, check-in, cancelar/reprogramar | 2, backend citas |
| **4 · Triaje + Diagnóstico** | Cola de enfermería, formulario, agenda médica, diagnóstico | 3 |
| **5 · Cola + TV real** | `useQueue` con socket, WaitingQueue, TvDisplay kiosco, token TV | 4, backend queue |
| **6 · Pagos Culqi** | PaymentGateway conectado, Mis pagos, verificación de recepción, saldos 50% | 5, backend payments |
| **7 · Lista de espera** | Inscripción, ofertas con `useCountdown` (expiración real), confirm/reject | 5 |
| **8 · Historial + PDF** | Historial desde API, descargas con URL firmada | 4 |
| **9 · Admin** | Usuarios, especialidades, consultorios, reportes, auditoría, configuración | 3 |
| **10 · Notificaciones** | Campana del topbar con `notification.new` (socket) | 5 |
| **11 · PWA + rendimiento** | Lazy loading por rol, manifest, service worker, audit accesibilidad | todo |
| **12 · Hardening** | Pruebas Vitest de flujos críticos, errores 429/403, pulido responsive | todo |

> Cada fase termina con build verificado (`npm.cmd run build`) y documentación reflejada en `README.md` / `docs/MODULOS.md` (regla de `AGENTS.md`).

---

## Parte 16 · Lo que falta en el prototipo (gaps detectados para producción)

1. **No hay cliente HTTP ni manejo de errores unificado** (todo es Context): crear `api/` (Parte 2/6).
2. **Sin guard de rutas**: cualquier rol entra a cualquier panel — resolver con `RequireAuth` (Parte 4.2).
3. **Fechas y datos hardcodeados** en decenas de componentes (`TODAY`, `2026-08-05`, `dayLabel`, `genWeek`): migrar a `date-fns` + servidor.
4. **`AppContext` mezcla datos y UI**: separar (Parte 3) — es el mayor cambio estructural.
5. **Sincronización entre pestañas solo por `localStorage`**: reemplazar por API + socket; la TV no funciona entre dispositivos.
6. **Pasarela simulada**: conectar Culqi.js (Parte 9); el auto-formato/detección de marca de `PaymentGateway.jsx` se conserva como UX.
7. **Acciones simuladas que son toasts** (exportación de reportes, envío de correos, comprobantes): conectar a endpoints reales.
8. **`resetDemo` y modo automático de TV**: eliminar en producción (quedan en staging).
9. **Sin pruebas automatizadas ni lazy loading**: agregar Vitest + `React.lazy` (Parte 12).
10. **Identidad demo `ME`/`NURSE` fijos**: reemplazar por `auth.user` del backend.
11. **PDF generado en el navegador**: pasar a URLs firmadas del backend (mantener `jspdf` solo como respaldo).
12. **No hay manejo de sesión tras recarga**: implementar refresh silencioso (Parte 4.3).

---

## Parte 17 · Tabla de mapeo prototipo → frontend producción

| Prototipo | Producción |
|---|---|
| `main.jsx` (AppProvider + ToastProvider) | + `QueryClientProvider` + `AuthProvider` |
| `App.jsx` (rutas sin guard) | + `RequireAuth` wrapper y metadatos de rol |
| `AppContext.jsx` | `api/` + hooks `use*` + `AuthContext` |
| `data/mock.js` (TODAY, ME, colecciones) | Servidor; se elimina (queda solo en staging) |
| `Login.jsx` heurística + acceso rápido | Login real JWT; acceso rápido solo staging |
| `PanelLayout.jsx` NAV por rol | Mismo `NAV`, rol desde sesión |
| `PaymentGateway.jsx` simulado | Culqi.js + `POST /payments/charge` |
| `WaitingQueue.jsx` (estado en memoria) | `useQueue` + evento `QueueUpdated` (Reverb) |
| `TvDisplay.jsx` (modo automático) | Kiosco con token TV + Reverb (sin auto-demo) |
| `useCountdown.jsx` (15 min fijos) | `settings.waitlistWindowMin` del servidor |
| `clinicPdf.js` / `jspdf` | `GET /documents/*` (URL firmada S3) |
| `helpers.js` `dayLabel/genWeek/hourSlots` | `date-fns` es-PE + datos del backend |
| `Toast.jsx` (toasts simulados) | Toasts reales de errores/éxito de la API |
| `/componentes` galería | Se conserva + nuevos estados de carga/error |