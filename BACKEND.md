# SGCM-CMAS · Backend — Documento de implementación

Documento integral para el desarrollo del **backend en producción** del Sistema de Gestión de Citas Médicas (CMAS, Ayacucho). Cubre desde la creación de la base de datos hasta la integración de APIs externas, organizado en partes secuenciales. Todo lo aquí descrito proviene del análisis del prototipo (`src/data/mock.js`, `src/context/AppContext.jsx`, `src/utils/helpers.js`, `README.md`, `docs/MODULOS.md`) y de la arquitectura objetivo (C4).

> **Stack elegido: Laravel (PHP).** Este documento reemplaza la planificación anterior (NestJS) por **Laravel 12 + PHP 8.3** con Eloquent, Sanctum, Reverb (WebSockets) y Horizon (colas).

> **Alcance:** solo backend. El frontend (SPA React + TV) se consume como cliente de estas APIs. Los IDs, estados y reglas de negocio **respetan los del prototipo** para no romper la integración.

---

## Parte 0 · Visión general

### 0.1 Arquitectura objetivo (resumen)

```
SPA React / TV (kiosco)
        │ HTTPS (REST) · wss (Pusher protocol / Reverb)
        ▼
API REST (Laravel) ──► PostgreSQL (fuente de verdad)
     │      │  │
     │      │  ├──► Redis (cache, sesiones, colas Horizon)
     │      │  └──► S3 / R2 (PDFs emitidos, documentos)
     │      └─────► Reverb (WebSockets, canales por consultorio)
     │
     ├──► Culqi (tokenización, cobros, webhooks, reembolsos)
     ├──► RENIEC (validación de DNI)
     └──► Email / SMS (recordatorios de cita)
```

### 0.2 Principios de diseño

1. **Consistencia fuerte para reservar cupo** (requisito de `implementar.md`): toda reserva se hace en transacción con bloqueo de fila (`lockForUpdate`); nunca dos citas sobre el mismo `doctor + fecha + hora`.
2. **El servidor es dueño de la verdad**: estados de cita, turnos, cola y pagos solo cambian vía API; el frontend no decide estados.
3. **Tiempo real para cola y TV**: Laravel Reverb (WebSockets) con canales por consultorio; la TV se actualiza con eventos, no con polling.
4. **Auditoría completa y persistente**: toda acción sensible (login, pagos, triaje, acceso a historial, cancelaciones) se registra con timestamp real, IP y usuario.
5. **Datos sensibles protegidos**: cifrado en reposo (Ley 29733), tokens de corta duración, roles en el servidor (nunca confiar en el frontend).
6. **Pagos con Culqi**: tokenización en el navegador; el backend solo cobra con el token (nunca ve la tarjeta) y verifica los webhooks por firma.

### 0.3 Qué se hereda del prototipo (no rediseñar)

| Elemento | Fuente |
|---|---|
| Estados de cita (`agendada…documentada`, `cancelada`, `reprogramada`) | `helpers.js` `STATUS_LABEL` |
| Tipos de pago (`adelanto` 50% / `total` 100%) | `PAY_TYPE_LABEL` |
| Pipeline de cola (`en_espera_triaje → en_triaje → triaje_completado → en_atencion → atendida`) | `AppContext.jsx` `QUEUE_PIPELINE` |
| Turnos secuenciales `A-00X` por día | `turnoOf` / `nextTurno` |
| Reglas de negocio configurables (12 h cancelación, ventana 15 min ofertas, días no laborables) | `Settings.jsx` |
| Heurística de rol | se reemplaza por `role` real en BD |
| Pago 50% habilita check-in directo; saldo se cobra en recepción | `BookAppointment.jsx` / `Payment.jsx` |

---

## Parte 1 · Stack, herramientas y dependencias

### 1.1 Stack elegido

| Capa | Tecnología | Justificación |
|---|---|---|
| Lenguaje | **PHP 8.3+** | Decision del equipo |
| Framework | **Laravel 12** | Rutas, Eloquent, migraciones, queues, scheduler, auth y WebSockets de primera clase |
| ORM / migraciones | **Eloquent** + Schema Migrations | Integración nativa, migraciones versionadas |
| Base de datos | **PostgreSQL 16** | Consistencia fuerte (transacciones, `SELECT FOR UPDATE`), ENUMs, JSONB |
| Cache / sesiones | **Redis 7** | Cache de horarios libres, sesiones, colas |
| Colas de trabajo | **Laravel Queues + Horizon** (Redis) | Recordatorios, expiración de ofertas, conciliación |
| Tiempo real | **Laravel Reverb** (WebSockets, protocolo Pusher) | Cola + TV en vivo entre procesos |
| Autenticación | **Laravel Sanctum** (tokens con abilities) + tabla `refresh_tokens` propia | Access + refresh rotativo |
| Pagos | **Culqi** (`guzzle`/HTTP client + `Culqi.js` en frontend) | Pasarela peruana elegida por el cliente |
| Documentación API | **Scribe** (`knuckleswtf/scribe`) | Contrato vivo para el frontend |
| Validación | **FormRequest** + `Validator` | Validación declarativa en cada endpoint |
| PDFs | `dompdf` / `barryvdh/laravel-dompdf` → S3 | Historial clínico, comprobantes |
| Pruebas | **PHPUnit** (feature tests + unit tests) | Calidad mínima |
| Calidad | **Laravel Pint** + PHP_CodeSniffer | Convenciones |
| Despliegue | Docker (php:8.3-fpm + nginx) + GitHub Actions | Ambientes dev/test/prod |

### 1.2 Dependencias principales (`backend/composer.json`)

```json
{
  "require": {
    "php": "^8.3",
    "laravel/framework": "^12.0",
    "laravel/sanctum": "^4.0",
    "laravel/horizon": "^5.0",
    "laravel/reverb": "^1.0",
    "predis/predis": "^2.2",
    "guzzlehttp/guzzle": "^7.8",
    "barryvdh/laravel-dompdf": "^3.0",
    "knuckleswtf/scribe": "^5.0"
  },
  "require-dev": {
    "pestphp/pest": "^3.0",
    "laravel/pint": "^1.13"
  }
}
```

> Opcional según contrato Culqi: paquete `culqi/culqi-php` (SDK oficial) o el cliente HTTP con `guzzle`.

> **Versiones reales instaladas (agosto 2026):** Horizon `^5.0` (v5.48.3) — el `^6.0` del borrador no existe como versión estable para Laravel 12; Scribe `^5.0` (v5.11.0) — el `^4.0` no soporta Laravel 12. Se documenta la desviación conforme a `ESTANDARES.md`. Requiere `ext-pcntl`/`ext-posix` para Horizon en Linux (Docker); en Windows local se instala con `--ignore-platform-req`. Dev: Pest `^3.0` (v3.8.7), Pint `^1.24`.

### 1.3 Variables de entorno (`.env.example`)

```env
APP_NAME=SGCM-CMAS
APP_ENV=local
APP_KEY=base64:...
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173

# Base de datos
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sgcm_cmas
DB_USERNAME=cmas
DB_PASSWORD=change-me

# Redis / colas / cache
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Tokens de acceso
ACCESS_TOKEN_TTL_MINUTES=15
REFRESH_TOKEN_TTL_DAYS=30

# Culqi (NUNCA en el repo; el frontend usa pk_)
CULQI_API_KEY=sk_test_...
CULQI_API_BASE=https://api.culqi.com/v2
CULQI_WEBHOOK_SECRET=whsec_...

# RENIEC (consulta de DNI)
RENIEC_API_URL=https://api.reniec.gob.pe/...
RENIEC_API_TOKEN=...

# Email / SMS
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
SMS_API_KEY=...

# Storage (PDFs)
S3_ENDPOINT=https://...          # compatible S3 (R2, MinIO, AWS)
S3_BUCKET=sgcm-cmas-docs
S3_REGION=auto
S3_ACCESS_KEY=...
S3_SECRET_KEY=...

# Reverb (WebSockets)
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_APP_PORT=443

# Cifrado de datos sensibles
DATA_ENC_KEY=...                 # AES-256, 32 bytes hex
```

> **Dev local sin Redis:** en Windows/Laragon el `.env` usa `CACHE_STORE=file`, `SESSION_DRIVER=file` y `QUEUE_CONNECTION=sync` (desviación local, ver `ESTANDARES.md`). En Docker los servicios declaran `CACHE_STORE=redis`/`QUEUE_CONNECTION=redis`/`SESSION_DRIVER=redis` explícitamente en su `environment` (docker-compose.yml), por lo que el `.env` local no afecta al contenedor. En producción se usa `.env.production` con Redis.

---

## Parte 2 · Base de datos (PostgreSQL)

### 2.1 Modelo entidad-relación

```
users ────┬── patients (1:1, rol paciente)
          ├── doctors  (1:1, rol medico)
          ├── refresh_tokens (1:N)
          ├── password_reset_tokens (1:N)   # añadida en la impl. (Parte 4.3)
          └── audit_log (1:N, por user_id)

specialties ──┬── doctors (N:1)
              └── consultorio_specialties (N:M con consultorios)

consultorios ─── consultorio_specialties ─── specialties

appointments ── patients (N:1)
             ── doctors (N:1)
             ── specialties (N:1)
             ── payments (1:N)
             ── triages (1:1)
             ── diagnoses (1:1)
             ── waitlist_entries (1:1, la oferta confirmada crea la cita)

doctor_schedules ── doctors (N:1)      # plantilla semanal de horas
doctor_date_exceptions ── doctors (N:1) # días bloqueados / ausencias
waitlist_entries ── patients, doctors, specialties
settings (tabla clave-valor)
```

### 2.2 Script DDL completo

```sql
-- ============================================================
-- SGCM-CMAS · Esquema de base de datos (PostgreSQL 16)
-- Migración inicial: database/migrations/0001_01_01_000000_create_schema.php
-- ============================================================

CREATE TYPE user_role AS ENUM
  ('paciente','medico','enfermera','recepcionista','administrador');

CREATE TYPE appointment_status AS ENUM
  ('agendada','confirmada','pagada','check_in','en_espera_triaje','en_triaje',
   'triaje_completado','en_atencion','atendida','documentada',
   'cancelada','reprogramada');

CREATE TYPE payment_status AS ENUM
  ('pendiente_verificacion','pagado','reembolsado','fallido');

CREATE TYPE payment_method AS ENUM
  ('efectivo','yape','plin','transferencia','tarjeta_pasarela');

CREATE TYPE paid_type AS ENUM ('adelanto','total');   -- 50% / 100%

CREATE TYPE waitlist_status AS ENUM
  ('en_espera','oferta','confirmada','expirada','retirada');

CREATE TYPE audit_sev AS ENUM ('info','warning','danger');

-- ------------------------------------------------------------------
-- USERS · cuentas del sistema (5 roles del prototipo)
-- ------------------------------------------------------------------
CREATE TABLE users (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role          user_role NOT NULL,
  active        BOOLEAN NOT NULL DEFAULT TRUE,
  last_login_at TIMESTAMPTZ,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------------
-- PATIENTS · perfil clínico (1:1 con users rol=paciente)
-- DNI y dirección se cifran en la capa de aplicación (AES-256-GCM)
-- ------------------------------------------------------------------
CREATE TABLE patients (
  id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id    UUID NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  dni        VARCHAR(8) NOT NULL UNIQUE,
  phone      VARCHAR(15),
  dob        DATE NOT NULL,
  address    TEXT,
  consent_29733 BOOLEAN NOT NULL DEFAULT FALSE,   -- Ley N.º 29733
  consent_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------------
-- DOCTORS · perfil profesional (1:1 con users rol=medico)
-- ------------------------------------------------------------------
CREATE TABLE doctors (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id        UUID NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  initials       VARCHAR(5) NOT NULL,
  specialty_id   UUID NOT NULL REFERENCES specialties(id),
  consultorio_id UUID REFERENCES consultorios(id),
  phone          VARCHAR(15),
  bio            TEXT,
  rating         NUMERIC(3,2) NOT NULL DEFAULT 0,
  rating_count   INTEGER NOT NULL DEFAULT 0,
  studies        VARCHAR(120),
  exp            INTEGER NOT NULL DEFAULT 0,
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------------
-- SPECIALTIES · catálogo (precios del prototipo)
-- ------------------------------------------------------------------
CREATE TABLE specialties (
  id       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  code     VARCHAR(30) NOT NULL UNIQUE,   -- 'medicina','pediatria',...
  name     VARCHAR(80) NOT NULL,
  icon     VARCHAR(30) NOT NULL DEFAULT 'stethoscope',
  price    NUMERIC(10,2) NOT NULL CHECK (price > 0),
  "desc"   TEXT,                          -- palabra reservada en PG; se cita siempre
  active   BOOLEAN NOT NULL DEFAULT TRUE
);

-- ------------------------------------------------------------------
-- CONSULTORIOS + especialidades asociadas (M:N)
-- ------------------------------------------------------------------
CREATE TABLE consultorios (
  id      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  nombre  VARCHAR(80) NOT NULL,
  piso    VARCHAR(20) NOT NULL,
  area    VARCHAR(80),
  activo  BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE consultorio_specialties (
  consultorio_id UUID NOT NULL REFERENCES consultorios(id) ON DELETE CASCADE,
  specialty_id   UUID NOT NULL REFERENCES specialties(id)   ON DELETE CASCADE,
  PRIMARY KEY (consultorio_id, specialty_id)
);

-- ------------------------------------------------------------------
-- DOCTOR_SCHEDULES · plantilla de disponibilidad semanal
-- (en el prototipo eran slots concretos; aquí: franjas por día de semana)
-- ------------------------------------------------------------------
CREATE TABLE doctor_schedules (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  doctor_id    UUID NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
  day_of_week  SMALLINT NOT NULL CHECK (day_of_week BETWEEN 0 AND 6), -- 0=Dom
  start_time   TIME NOT NULL,
  end_time     TIME NOT NULL,
  UNIQUE (doctor_id, day_of_week, start_time, end_time)
);

-- Días bloqueados (vacaciones, ausencias puntuales)
CREATE TABLE doctor_date_exceptions (
  id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  doctor_id  UUID NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
  date       DATE NOT NULL,
  reason     VARCHAR(120),
  UNIQUE (doctor_id, date)
);

-- ------------------------------------------------------------------
-- APPOINTMENTS · citas (núcleo del sistema)
-- ------------------------------------------------------------------
CREATE TABLE appointments (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  code           VARCHAR(12) NOT NULL UNIQUE,   -- 'C-1042' legible
  patient_id     UUID NOT NULL REFERENCES patients(id),
  doctor_id      UUID NOT NULL REFERENCES doctors(id),
  specialty_id   UUID NOT NULL REFERENCES specialties(id),
  date           DATE NOT NULL,
  time           TIME NOT NULL,
  duration_min   SMALLINT NOT NULL DEFAULT 30,
  status         appointment_status NOT NULL DEFAULT 'agendada',
  reason         TEXT,
  check_in_time  TIME,                          -- llegada confirmada
  turno          VARCHAR(6),                    -- 'A-001' (por día)
  paid_type      paid_type,                     -- adelanto | total
  cancelled_at   TIMESTAMPTZ,
  cancel_reason  TEXT,
  rescheduled_to DATE,                          -- reprogramación
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at     TIMESTAMPTZ NOT NULL DEFAULT now(),

  -- consistencia fuerte: un doctor no puede tener 2 citas en la misma
  -- franja el mismo día (excluye canceladas/reprogramadas)
  CONSTRAINT no_double_booking UNIQUE (doctor_id, date, time, status) DEFERRABLE INITIALLY IMMEDIATE
);

-- Índices operativos
CREATE INDEX idx_appt_patient ON appointments (patient_id, date DESC);
CREATE INDEX idx_appt_doctor_day ON appointments (doctor_id, date);
CREATE INDEX idx_appt_day_status ON appointments (date, status);
CREATE UNIQUE INDEX idx_appt_turno_day ON appointments (date, turno) WHERE turno IS NOT NULL;

-- ------------------------------------------------------------------
-- TRIAGES · triaje de enfermería (1:1 con cita)
-- ------------------------------------------------------------------
CREATE TABLE triages (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  appointment_id UUID NOT NULL UNIQUE REFERENCES appointments(id) ON DELETE CASCADE,
  nurse_id       UUID NOT NULL REFERENCES users(id),
  pa             VARCHAR(12),
  temp           NUMERIC(4,1),
  fc             SMALLINT,
  peso           NUMERIC(5,1),
  talla          NUMERIC(4,2),
  motivo         TEXT NOT NULL,
  alergias       TEXT,
  observaciones  TEXT,
  at             TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------------
-- DIAGNOSES · diagnóstico médico (1:1 con cita)
-- ------------------------------------------------------------------
CREATE TABLE diagnoses (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  appointment_id UUID NOT NULL UNIQUE REFERENCES appointments(id) ON DELETE CASCADE,
  doctor_id      UUID NOT NULL REFERENCES users(id),
  dx             TEXT NOT NULL,
  notes          TEXT,
  at             TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ------------------------------------------------------------------
-- PAYMENTS · pagos (caja + Culqi). El prototipo suma pagos pagados
-- para calcular el total (paidTotalOf); se mantiene la misma lógica.
-- ------------------------------------------------------------------
CREATE TABLE payments (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  code           VARCHAR(12) NOT NULL UNIQUE,     -- 'P-0813' / 'R-2026-0813'
  appointment_id UUID NOT NULL REFERENCES appointments(id),
  patient_id     UUID NOT NULL REFERENCES patients(id),
  amount         NUMERIC(10,2) NOT NULL CHECK (amount >= 0),
  method         payment_method NOT NULL,
  status         payment_status NOT NULL DEFAULT 'pendiente_verificacion',
  paid_type      paid_type NOT NULL,
  receipt_code   VARCHAR(16),                      -- comprobante 'R-2026-XXXX'
  verified_by    UUID REFERENCES users(id),        -- recepcionista / NULL=Sistema
  gateway        BOOLEAN NOT NULL DEFAULT FALSE,   -- pagado por Culqi
  culqi_order_id VARCHAR(60),                      -- order_xxx
  culqi_charge_id VARCHAR(60),                     -- charge_xxx
  culqi_data     JSONB,                            -- payload del webhook
  refunded_at    TIMESTAMPTZ,
  created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (appointment_id, culqi_order_id)          -- idempotencia de webhooks
);

CREATE INDEX idx_pay_appt ON payments (appointment_id);
CREATE INDEX idx_pay_status ON payments (status);

-- ------------------------------------------------------------------
-- WAITLIST_ENTRIES · lista de espera de cupos (módulo del paciente)
-- ------------------------------------------------------------------
CREATE TABLE waitlist_entries (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  code            VARCHAR(10) NOT NULL UNIQUE,     -- 'WL-008'
  patient_id      UUID NOT NULL REFERENCES patients(id),
  specialty_id    UUID NOT NULL REFERENCES specialties(id),
  doctor_id       UUID NOT NULL REFERENCES doctors(id),
  preferred       VARCHAR(160),
  position        INTEGER NOT NULL,
  status          waitlist_status NOT NULL DEFAULT 'en_espera',
  offer_date      DATE,                            -- cupo ofrecido
  offer_time      TIME,
  offer_expires_at TIMESTAMPTZ,                    -- ventana (15 min)
  confirm_window_min INTEGER NOT NULL DEFAULT 15,  -- settings.waitlistWindowMin
  created_appointment_id UUID REFERENCES appointments(id),
  enrolled_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_wl_patient ON waitlist_entries (patient_id);
CREATE INDEX idx_wl_spec_status ON waitlist_entries (specialty_id, status);
CREATE INDEX idx_wl_offer_expiry ON waitlist_entries (status, offer_expires_at)
  WHERE status = 'oferta';

-- ------------------------------------------------------------------
-- SETTINGS · reglas de negocio (Admin → Configuración)
-- ------------------------------------------------------------------
CREATE TABLE settings (
  key        VARCHAR(60) PRIMARY KEY,
  value      JSONB NOT NULL
);

INSERT INTO settings (key, value) VALUES
  ('minCancelHours',     '{"v": 12}'),
  ('minReserveHours',    '{"v": 2}'),
  ('tokenExpiryMin',     '{"v": 30}'),
  ('waitlistWindowMin',  '{"v": 15}'),
  ('lateFeeDays',        '{"v": 2}'),
  ('nonWorkingDays',     '{"v": ["2026-08-01","2026-08-02","2026-07-28","2026-07-29"]}');

-- ------------------------------------------------------------------
-- AUDIT_LOG · auditoría persistente (reemplaza el mock 'Hace unos segundos')
-- ------------------------------------------------------------------
CREATE TABLE audit_log (
  id         BIGSERIAL PRIMARY KEY,
  at         TIMESTAMPTZ NOT NULL DEFAULT now(),
  user_id    UUID REFERENCES users(id),
  email      VARCHAR(160),
  action     VARCHAR(80) NOT NULL,
  detail     TEXT,
  sev        audit_sev NOT NULL DEFAULT 'info',
  ip         INET,
  user_agent TEXT,
  route      VARCHAR(120),
  method     VARCHAR(10)
);

CREATE INDEX idx_audit_at ON audit_log (at DESC);
CREATE INDEX idx_audit_user ON audit_log (user_id, at DESC);

-- ------------------------------------------------------------------
-- REFRESH_TOKENS · sesiones rotativas
-- ------------------------------------------------------------------
CREATE TABLE refresh_tokens (
  id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id    UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash TEXT NOT NULL UNIQUE,        -- SHA-256 del token
  expires_at TIMESTAMPTZ NOT NULL,
  revoked_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  ip         INET,
  user_agent TEXT
);

CREATE INDEX idx_rt_user ON refresh_tokens (user_id);
```

### 2.3 Consistencia de la reserva (anti doble reserva)

La reserva se ejecuta **siempre** en transacción. Eloquent ofrece `lockForUpdate()` (equivalente a `SELECT ... FOR UPDATE`):

**Opción A — bloqueo de fila (recomendada):** secuencia de 30 min por médico/día materializada en `doctor_schedules` + validación con `lockForUpdate()` sobre la fila de la franja.

**Opción B — constraint + reintento:** `no_double_booking` (UNIQUE sobre `doctor_id, date, time, status`) convierte la carrera en error `23505`; el servicio reintenta y devuelve **409 + alternativas sugeridas** (mismo comportamiento del prototipo: modal "Este horario ya no está disponible" con 3 alternativas).

```php
// App\Services\AppointmentService::reserve()
DB::transaction(function () use ($doctorId, $date, $time) {
    // 1. Bloquear la franja para impedir otra reserva concurrente
    $slot = DoctorSchedule::query()
        ->where('doctor_id', $doctorId)
        ->where('day_of_week', (int) $date->format('w'))
        ->where('start_time', $time)
        ->lockForUpdate()
        ->first();

    // 2. Verificar que no exista cita activa (no cancelada/reprogramada)
    $conflict = Appointment::query()
        ->where('doctor_id', $doctorId)
        ->where('date', $date)
        ->where('time', $time)
        ->whereNotIn('status', ['cancelada', 'reprogramada'])
        ->exists();

    // 3. Si existe → lanzar ConflictException con 3 alternativas libres cercanas
    // 4. Si no existe → crear la cita (status 'agendada')
});
```

### 2.4 Turnos `A-00X`

Secuencia **por día** (el turno es el orden de llegada, `AppContext.turnoOf/nextTurno`). Se asigna en el check-in:

```sql
-- Transacción del check-in: asignar siguiente turno del día
UPDATE appointments SET status='en_espera_triaje', check_in_time=now(), turno=(
  SELECT 'A-' || LPAD((COALESCE(MAX(CAST(SUBSTRING(turno,3) AS INT)), 0) + 1)::text, 3, '0')
  FROM appointments WHERE date = $1 AND turno IS NOT NULL
) WHERE id = $2;
```

> La TV y la cola solo leen `appointments` con `date = hoy` y `status IN ('en_espera_triaje','en_triaje','triaje_completado','en_atencion')` ordenadas por `turno` (misma lógica que `queuedToday`).

### 2.5 Seed inicial

`database/seeders/DatabaseSeeder.php` replica `src/data/mock.js` del prototipo: 9 usuarios, 5 pacientes, 8 médicos, 7 especialidades, 5 consultorios, citas de ejemplo (incluidas las del día `2026-08-05` con turnos `A-001…A-003`), pagos, lista de espera y auditoría de muestra. Usado solo en dev/test.

---

## Parte 3 · Estructura del proyecto (Laravel)

```
backend/
├── .env / .env.example
├── artisan
├── composer.json
├── docker-compose.yml               # postgres + redis + app + reverb + horizon
├── Dockerfile
├── routes/
│   ├── api.php                      # TODAS las rutas de la API (prefijo /api)
│   └── web.php                      # rutas web auxiliares (health, docs)
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/         # un controller por recurso
│   │   │   ├── AuthController.php · UserController.php · AppointmentController.php
│   │   │   ├── PaymentController.php · TriageController.php · QueueController.php
│   │   │   ├── WaitlistController.php · ReportController.php · AuditController.php
│   │   │   ├── SettingsController.php · AvailabilityController.php · DocumentController.php
│   │   │   └── WebhookController.php (Culqi)
│   │   ├── Requests/                # FormRequests (Create/Update/Query)
│   │   ├── Resources/               # API Resources (transformación de respuestas)
│   │   └── Middleware/              # EnsureRole · ValidateCulqiWebhook · etc.
│   ├── Models/                      # Eloquent: User, Patient, Doctor, Specialty,
│   │                                #   Consultorio, Appointment, Triage, Diagnosis,
│   │                                #   Payment, WaitlistEntry, AuditLog, RefreshToken, Setting
│   ├── Services/                    # lógica de negocio reutilizable
│   │   ├── AppointmentService.php   # reserva con transacción, turnos, cancelación
│   │   ├── PaymentService.php       # Culqi: charge, webhooks, reembolsos
│   │   ├── QueueService.php         # pipeline de cola + eventos Reverb
│   │   ├── WaitlistService.php      # posiciones, ofertas, expiración
│   │   ├── AvailabilityService.php  # franjas libres + cache Redis
│   │   ├── TriageService.php · DiagnosisService.php
│   │   ├── ReportService.php · AuditService.php
│   │   ├── DocumentService.php      # PDFs (dompdf) → S3 + URLs firmadas
│   │   └── DniService.php           # RENIEC (provider intercambiable)
│   ├── Events/                      # AppointmentCreated, TurnCalled, QueueUpdated,
│   │                                #   NotificationCreated, PaymentPaid
│   ├── Jobs/                        # SendAppointmentReminder, ExpireWaitlistOffers,
│   │                                #   ReconcileCulqiOrders, ProcessLateCancellationRefund
│   ├── Listeners/ · Notifications/  # listeners de eventos y notificaciones (mail/SMS)
│   ├── Enums/                       # AppointmentStatus, PaymentStatus, PaidType,
│   │                                #   WaitlistStatus, UserRole, AuditSev
│   └── Console/
│       ├── Kernel.php               # schedule: recordatorios 24h/2h, expiración,
│       │                            #   conciliación, limpieza de tokens
│       └── Commands/                # ReconcilePayments, ExpireOffers, ...
├── config/                          # sanctum.php, horizon.php, reverb.php, ...
├── database/
│   ├── migrations/                  # migraciones versionadas (0001_init…)
│   └── seeders/                     # DatabaseSeeder (mock del prototipo)
├── tests/
│   ├── Unit/                        # reglas de negocio (PHPUnit/Pest)
│   └── Feature/                     # e2e API con RefreshDatabase
└── bootstrap/app.php
```

Convenciones por módulo (patrón Laravel):

| Archivo | Responsabilidad |
|---|---|
| `routes/api.php` | Define el contrato REST con middleware de rol (`role:paciente`) y rate limit |
| `Controllers/Api/*` | Orquesta request → servicio → resource; respuestas JSON con `apiResource`/`JsonResponse` |
| `Requests/*` | Validación declarativa (`FormRequest`) |
| `Resources/*` | Transformación de modelos a JSON (paginación, campos por rol) |
| `Services/*` | Reglas de negocio, transacciones y disparo de eventos |
| `Models/*` | Eloquent + casts (`enum`, `json`), scopes (`scopeQueuedToday`) |
| `Events/Jobs/Listeners` | Desacopla: transición de cola → evento → broadcast Reverb |

---

## Parte 4 · Autenticación y autorización

### 4.1 Flujo de login

1. `POST /api/auth/login` con `{ email, password }`.
2. Se busca en `users`; si `active = false` → 403. Se compara `Hash::check`.
3. Se emiten **access token** (Sanctum, 15 min, con abilities `role:paciente` etc.) y **refresh token** (30 días, guardado *hasheado* en `refresh_tokens`, rotativo).
4. Se actualiza `last_login_at` y se registra auditoría (rol **real desde BD**; la heurística del prototipo desaparece).
5. Rate limiting: `RateLimiter` 5 intentos/min por email+IP → bloqueo temporal (prototipo: "Intento de login fallido").

### 4.2 Refresh de tokens

- `POST /api/auth/refresh`: recibe el refresh token, verifica hash y expiración en `refresh_tokens`, **revoca el usado** y emite un par nuevo (rotación). Reuso del mismo refresh → revoca toda la familia (seguridad).
- En `routes/api.php`, el grupo autenticado usa `auth:sanctum` + middleware `role:`.

### 4.3 Recuperación de contraseña

- `POST /api/auth/forgot-password` → genera token de un solo uso con expiración `settings.tokenExpiryMin` (30 min) → email con enlace `FRONTEND_URL/recuperar/nueva-password?token=…`.
  > **Implementación:** se añadió la tabla `password_reset_tokens` (`email` PK, `token` hasheado SHA-256, `expires_at`) en la migración `0001_01_01_000003`; el DDL de la Parte 2 no la incluía (desviación documentada).
- `POST /api/auth/reset-password` valida token, política de clave (≥6, mayúscula, número) y revoca refresh tokens del usuario.

### 4.4 Registro público (solo pacientes)

- Valida correo/DNI/celular **únicos** (regla de `Register.jsx`), verifica DNI contra **RENIEC** (si la integración está activa), exige consentimiento Ley 29733 (`consent_29733 = true`).
- El resto de roles los crea el admin (`POST /api/users` con `role`).

### 4.5 Autorización por rol y por recurso

- Middleware `EnsureRole` (equivalente a `@Roles`): `role:paciente`, `role:medico`, `role:enfermera|recepcionista`, etc.
- **Relación clínica** (regla del prototipo, `PatientDetail.jsx`): un médico solo ve el historial de un paciente con el que tiene citas (`Appointment::where('doctor_id', $me)->where('patient_id', $pid)->exists()`). Si no → 403 + registro en auditoría (`Acceso denegado`, sev `danger`).
- **TV**: token de solo lectura por consultorio (`POST /api/tv/token` con clave de pantalla configurada por admin) — nunca usa sesión de empleado. Se emite como token Sanctum con ability `tv:read`.

---

## Parte 5 · Contrato de API (endpoints por módulo)

> Prefijo global: `/api`. Formato de respuesta unificado: `{ data: … }` en éxito; `{ message, errors }` en error (FormRequest). Paginación `?page=&per_page=`. Documentación generada por **Scribe** en `/docs`.

### 5.1 Auth
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/api/auth/login` | público | Inicio de sesión |
| POST | `/api/auth/refresh` | público | Renovar access con refresh rotativo |
| POST | `/api/auth/logout` | autenticado | Revoca refresh token |
| POST | `/api/auth/register` | público | Registro de paciente (Ley 29733) |
| POST | `/api/auth/forgot-password` | público | Solicitar enlace |
| POST | `/api/auth/reset-password` | público | Nueva contraseña |

### 5.2 Usuarios (admin)
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/users` | Lista con filtro por rol/activo (tabla de `Users.jsx`) |
| POST | `/api/users` | Alta con correo único (admin crea cualquier rol) |
| PATCH | `/api/users/{id}` | Editar nombre/rol |
| PATCH | `/api/users/{id}/activate` | Activar/desactivar (`{active}`) |

### 5.3 Catálogos
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/specialties` | público | Especialidades activas con precio |
| POST/PATCH | `/api/specialties` | admin | Precios, activación (advertencia si hay médicos) |
| GET | `/api/consultorios` | público | Pisos/áreas |
| POST/PATCH | `/api/consultorios` | admin | Asignación de especialidades (M:N) |
| GET | `/api/doctors` | público | Profesionales + rating + consultorio |
| GET | `/api/doctors/{id}` | público | Detalle (bio, estudios, exp) |
| GET | `/api/doctors/{id}/slots?from=&to=` | autenticado | Franjas libres (cache Redis 30 s) |
| POST | `/api/doctors/{id}/schedules` | medico/admin | Plantilla semanal |
| POST | `/api/doctors/{id}/exceptions` | medico/admin | Día bloqueado |

### 5.4 Disponibilidad pública
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/availability?specialtyId=&from=&to=` | Replica `SearchAvailability.jsx`: cruza schedules con citas activas; filtra días no laborables; `specialtyId=cardiologia` puede devolver lista vacía (caso demo) |

### 5.5 Citas (núcleo)
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/api/appointments` | paciente/recepción | **Reserva con transacción**; body `{doctorId, specialtyId, date, time, duration, reason, payOnline?: {type: 'adelanto'|'total', culqiToken?}}` → 201 con cita; 409 con `alternatives[]` si hay conflicto |
| GET | `/api/appointments/me` | paciente | Próximas/pasadas/canceladas (`MyAppointments.jsx`) |
| GET | `/api/appointments/day?date=` | recepción/medico/enfermera | Agenda del día (filtros por especialidad/médico) |
| GET | `/api/appointments/{id}` | autenticado (autorizado) | Detalle con triage, pago y diagnóstico |
| POST | `/api/appointments/{id}/checkin` | paciente | Check-in móvil → `check_in` (solo `agendada`/`pagada`) |
| PATCH | `/api/appointments/{id}/cancel` | paciente/recepción | Cancela (regla 12 h → warning `cancelacion tardia`); libera franja; reembolso si pagó por Culqi |
| PATCH | `/api/appointments/{id}/reschedule` | paciente/recepción | `{date, time}` → `reprogramada` (valida franja libre) |
| GET | `/api/appointments/patient/{pid}` | medico | Historial del paciente (regla de relación clínica) |

### 5.6 Pagos (caja + Culqi)
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| POST | `/api/payments/charge` | paciente | Cobro por Culqi: recibe `{appointmentId, type: 'adelanto'|'total', culqiToken}` → crea `order` en Culqi → `charge` → payment `pagado` (gateway=true, op_ref) |
| POST | `/api/payments/cash` | recepción | Cobro en caja (efectivo/Yape/Plin/transferencia) → comprobante `R-2026-XXXX` |
| POST | `/api/payments/verify` | recepción | Confirma `pendiente_verificacion` (declarados por el paciente) |
| POST | `/api/payments/complete-balance` | recepción | Cobra el saldo de abonos 50% (deja la cita al 100%) |
| POST | `/api/payments/{id}/refund` | admin | Reembolso Culqi (`charge_id`) |
| POST | `/api/webhooks/culqi` | público* | *Firma HMAC v2 verificada; actualiza `payments` por `culqi_order_id` (idempotente) |
| GET | `/api/payments/receipts/{id}` | paciente | Comprobante (PDF en S3) |

### 5.7 Triaje (enfermería)
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/triage/queue` | Cola: `en_espera_triaje` ordenados por tiempo de espera + en progreso (`TriageQueue.jsx`) |
| GET | `/api/triage/history?date=` | Triajes del turno |
| POST | `/api/triage/{appointment}` | Inicia triaje → `en_triaje` (solo desde `en_espera_triaje`) |
| PATCH | `/api/triage/{appointment}/complete` | Guarda signos vitales → `triaje_completado` (inserta en `triages`) |

### 5.8 Cola del día (recepción/enfermería) + TV
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/api/queue/day?date=` | Citas en pipeline ordenadas por `turno` + stats en vivo (esperando/en triaje/en consulta/atendidos) |
| POST | `/api/queue/{id}/send-triage` | Check-in presencial: asigna `turno` → `en_espera_triaje` (desde `pagada` o `check_in` del móvil) |
| POST | `/api/queue/{id}/call-triage` | → `en_triaje` (llamado a triaje) |
| POST | `/api/queue/{id}/finish-triage` | → `triaje_completado` (variante desde el tablero) |
| POST | `/api/queue/{id}/call-consult` | → `en_atencion` (llamado a consulta) |
| POST | `/api/queue/{id}/attended` | → `atendida` (sale de la cola) |
| GET | `/api/queue/stats-today` | Contadores para el header de la TV |
| POST | `/api/tv/token` | Emite token de solo lectura para la pantalla (clave de consultorio) |

> Cada transición emite un evento Laravel que **broadcast** `queue.updated` al canal del consultorio (Parte 8).

### 5.9 Lista de espera (paciente)
| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/waitlist` | Inscripción `{specialtyId, doctorId, preferred}` → posición `N` |
| GET | `/api/waitlist/me` | Mis inscripciones (estados y ofertas) |
| POST | `/api/waitlist/{id}/confirm` | Confirma oferta → **crea la cita automáticamente** + payment `pendiente_verificacion` (lógica de `confirmOffer`) |
| POST | `/api/waitlist/{id}/reject` | Rechaza → vuelve a `en_espera`, cupo pasa al siguiente |
| POST | `/api/waitlist/{id}/offer` | (worker) Asigna cupo: `{date, time}` + `offer_expires_at = now + settings.waitlistWindowMin` |
| POST | `/api/waitlist/{id}/expire` | (worker) → `expirada`, oferta al siguiente |

### 5.10 Reportes / Auditoría / Configuración
| Método | Ruta | Rol | Descripción |
|---|---|---|---|
| GET | `/api/reports/summary?month=` | admin | Citas, tasa de cancelación, inasistencia, ingresos (`Dashboard.jsx`) |
| GET | `/api/reports/occupancy?from=&to=` | admin | Ocupación por especialidad + tendencia semanal |
| GET | `/api/reports/export?type=csv` | admin | Exportación (reemplaza el toast simulado) |
| GET | `/api/audit?sev=&from=&to=` | admin | Registro paginado con `at` real |
| GET/PATCH | `/api/settings` | admin | Reglas de negocio (tabla `settings`) |
| GET | `/api/notifications/me` | autenticado | Recordatorios/avisos (lista en el topbar) |
| POST | `/api/documents/{appointment}/pdf` | paciente/medico | Genera y sube PDF (historial/ficha) a S3, devuelve URL firmada |

---

## Parte 6 · Reglas de negocio críticas

### 6.1 Estados y transiciones válidas

```
agendada ──(pago caja)────────────► pagada
agendada ──(Culqi 50%/100%)──────► pagada (paid_type adelanto|total)
confirmada ──(pago caja)──────────► pagada   (cita registrada por recepción)
pagada ──(check-in móvil)───────────► check_in
pagada ──(check-in presencial)──────► en_espera_triaje (turno A-00X)
check_in ──(check-in presencial)───► en_espera_triaje (turno A-00X)
en_espera_triaje ──(llamar)──────► en_triaje ──(completar)──► triaje_completado
triaje_completado ──(llamar)─────► en_atencion ──(atender)──► atendida | documentada
agendada|pagada ──(cancelar)─────► cancelada      (≤12 h → aviso cancelación tardía)
cualquiera ──(reprogramar)───────► reprogramada
```

Cada transición se valida en el servicio (nunca aceptar saltos inválidos). Se recomienda además `appointment_status_history` para auditar cambios de estado.

### 6.2 Pago 50% / 100%

- **Adelanto (50%)**: monto `round(price/2)` vía Culqi → cita `pagada` con `paid_type='adelanto'` → habilita check-in directo; el saldo se cobra en recepción (`/api/payments/complete-balance`): se registra un segundo pago `pagado` y la cita pasa a `paid_type='total'` (misma semántica de `Payment.jsx`).
- **Total (100%)**: pago completo → `paid_type='total'`.
- **Caja**: cita queda `agendada` hasta el cobro en recepción.
- Métodos del paciente (Yape/Plin/Transferencia declarados en `PatientPayments.jsx`) → `pendiente_verificacion` hasta confirmación de recepción (<15 min).

### 6.3 Cancelación tardía

`(hora de cita - hora de cancelación) < settings.minCancelHours (12 h)` → el sistema **advierte** (toast/modal) y registra en auditoría `Cita cancelada` sev `warning`. Si hubo pago Culqi → reembolso automático (job) o manual según política.

### 6.4 Lista de espera

- Posición = orden cronológico de inscripción dentro de `(specialty, doctor)`.
- Al liberarse un cupo, el worker ofrece al primero en `en_espera` (`offer_expires_at` = +`settings.waitlistWindowMin` = 15 min).
- `confirm` crea la cita (misma transacción de reserva) y un pago `pendiente_verificacion` (replica `confirmOffer`).
- `expire` → pasa el cupo al siguiente (el prototipo lo hace manualmente con `useCountdown`).

### 6.5 Registro de desviaciones y decisiones de implementación (Partes 5–6)

| # | Decisión | Detalle |
|---|---|---|
| 1 | Timezone | `APP_TIMEZONE=America/Lima` (Postgres sesión `America/Lima`). Sin esto, Eloquent relee fechas con desfase de 5 h. |
| 2 | TV de solo lectura | `POST /api/tv/token` emite token cifrado (`Crypt::encryptString`, `aud=tv`, exp +1 día) con la clave `TV_READ_KEY` (default `cmas-tv`). `GET /api/queue/day` y `GET /api/queue/stats-today` quedan **fuera** de `auth:sanctum` con middleware `tv-or-auth` (`TvOrSanctum`): aceptan token TV vía `?tvToken=` o staff autenticado (recepcionista/enfermera/medico/administrador). |
| 3 | Historial de estados | Tabla `appointment_status_history` implementada (migración `2026_08_20_190000`): cada transición registra `{appointment_id, from, to, at, by_user_id}`. |
| 4 | Avisos/notificaciones | Tabla `user_notices` (migración `2026_08_20_200000`); leído = columna `read_at` (timestamptz nullable), **no** booleano `read`. `GET /api/notifications/me` responde `{items, unread, pagination}`; `PATCH /api/notifications/{id}/read` devuelve `{read_at}`. |
| 5 | Culqi en desarrollo | `.env` con placeholder `CULQI_API_KEY=sk_test_...`: `CulqiGateway::configured()` devuelve `false` si la clave está vacía o contiene `...` → modo mock local (order/charge/refund con prefijos `mock_`), sin llamadas HTTP. Cifras en centavos: `charge` pasa `round($amount*100)` a order y charge. |
| 6 | Comprobante PDF | `GET /api/payments/receipts/{id}` y `POST /api/documents/{appointment}/pdf` (ficha clínica, vista `pdfs/clinical`) guardan en disco local (`clinical/`), no S3; en producción cambiar el disco a S3. La respuesta es JSON `{url, path, size}` (no bytes). |
| 7 | Complete-balance | Monto = `price − paidTotalOf(appointment)` (el resto real pagado), no un 50% fijo; lanza `InvalidArgumentException` si `paid_type !== adelanto`. `PaymentService::cash()` acepta `?amountOverride` para esto. |
| 8 | `markAppointmentPaid()` | Además de marcar el pago, flipea la cita `Agendada|Confirmada → Pagada`; `PaymentController::charge()` hace lo propio para Culqi (en el controlador, para no romper el flujo de `reserve()` que transiciona al final). |
| 9 | Enums | `AppointmentStatus` **no** tiene `Reservada` ni `PagoPendiente` (usar `Agendada`); `PaymentStatus::Pagado='pagado'` (no `Completado`); `PaidType` solo `Adelanto|Total`; `PaymentMethod` sin `'tarjeta'` en caja (solo efectivo/yape/plin/transferencia). |
| 10 | Modelos | `Payment::$timestamps=false` con `UPDATED_AT=null` (la tabla `payments` no tiene `updated_at`); `WaitlistEntry` cast `enrolled_at => datetime`; `AuditLog` con `sev` cast a enum, `at` datetime y `$timestamps=false`. |
| 11 | Waitlist | `confirm` crea la cita `agendada` + payment `pendiente_verificacion` (Yape/Plin declarados). `reject` vuelve a `en_espera` (no `retirada`); `expire` → `expirada` y renumera posiciones. `data.offer.time` = `'HH:MM:SS'` (columna TIME). |
| 12 | Auditoría | `GET /api/audit` filtra por `?sev=`, `?from=`, `?to=`; responde `{items, pagination}`. El login registra un log `sev=info` (el filtro `sev` es necesario para aislar). |
| 13 | Rutas | 86 rutas API en `routes/api.php`; webhook público `POST /webhooks/culqi` con middleware `culqi.webhook`; body de pagos en camelCase (`appointmentId`, `paymentId`, `type`, `culqiToken`). |
| 14 | Usuarios admin | `POST /api/users` responde `{user: {…}, password}` (la contraseña generada solo se devuelve una vez); `PATCH /api/specialties/{id}` responde `{specialty: {…}, warning}`; `GET /api/availability` responde `{slots: [{date, time, price, doctor, specialty}]}` (slot = 30 min, `end_time` excluyente). |

---

## Parte 7 · Pagos con Culqi (detalle de integración)

### 7.1 Flujo de cobro en línea

```
1. Frontend: Culqi.js genera TOKEN de tarjeta (nunca pasa por nuestro servidor)
2. POST /api/payments/charge  { appointmentId, type, culqiToken }
3. Backend: crea ORDER en Culqi (POST /v2/orders, monto según type) con SK
   → order_id (op_ref 'OP-2026-XXXX' del prototipo = order_id)
4. Backend: cobra el charge (POST /v2/charges con order_id + token)
5. Respuesta: payment 'pagado' (gateway=true, culqi_order_id, culqi_charge_id),
   cita → 'pagada' + paid_type
6. Culqi además notifica webhook order.paid → verificar firma → idempotencia
   (UNIQUE appointment_id+culqi_order_id)
```

Implementación: `App\Services\PaymentService` con cliente HTTP (`guzzle`) contra `https://api.culqi.com/v2` (o SDK oficial `culqi/culqi-php`). El `WebhookController` expone `POST /api/webhooks/culqi` con middleware `ValidateCulqiWebhook`.

### 7.2 Webhooks (endpoint público con firma)

- Header `Authorization: Bearer <CULQI_WEBHOOK_SECRET>` (firma v2) — rechazar sin verificar.
- Eventos a manejar: `order.paid`, `order.expired`, `charge.created`, `charge.failed`.
- `order.expired` → marca la cita `agendada` (no pagada) y libera para pago en caja.
- `charge.failed` → auditoría sev `danger` + notificación al paciente.
- **Idempotencia**: `updateOrCreate` por `culqi_order_id` (constraint UNIQUE en BD).

### 7.3 Reembolsos y conciliación

- `POST /v2/refunds` con `charge_id` (reembolso parcial = solo en producción según contrato Culqi).
- Job diario (`schedule` + Horizon): `GET /v2/orders?created_at[gte]=…` → cruza con `payments` para detectar cargos sin webhook (conciliación).
- Las keys `sk_test_*` / `sk_live_*` viven en `.env`; el frontend solo ve `pk_*` en su configuración.

### 7.4 Tarjetas y métodos

- Tarjetas Visa/Mastercard/Amex (auto-detección de marca ya existe en `PaymentGateway.jsx`).
- **Yape**: Culqi permite cobros Yape vía QR o API (validar contrato del plan); los pagos Yape declarados por el paciente siguen el flujo `pendiente_verificacion` → recepción.

---

## Parte 8 · Tiempo real (Laravel Reverb)

### 8.1 Diseño

| Pieza | Decisión |
|---|---|
| Servidor WebSockets | **Laravel Reverb** (protocolo Pusher, puerto 8080) |
| Canales | `queue.consultorio.{id}` (tablero y TV) y `global` (todas las pantallas) |
| Eventos broadcast | `QueueUpdated` (payload = `GET /api/queue/day`), `TurnCalled` `{turno, name, consultorio, destination}`, `TvRefresh` (fuerza recarga de la TV), `NotificationCreated` |
| Cliente | Frontend con **laravel-echo + pusher-js** (Parte 8 de FRONTEND.md) |
| Autenticación de canales | `Broadcast::channel('queue.consultorio.{id}', …)` → solo tokens `tv:read` o roles recepción/enfermera |
| Escalado | Reverb horizontal con Redis (múltiples servidores comparten eventos) |

### 8.2 Quién emite qué

| Acción (API) | Evento |
|---|---|
| `send-triage`, `call-triage`, `finish-triage`, `call-consult`, `attended` | `QueueUpdated` + `TurnCalled` |
| Pago verificado / cita creada | `NotificationCreated` al usuario |
| `tv/refresh` manual (admin) | `TvRefresh` |

### 8.3 Pantalla TV

- Autenticación: `POST /api/tv/token` con clave de consultorio → token Sanctum con ability `tv:read` (sin privilegios de panel).
- El payload de la TV replica `TvDisplay.jsx`: `now` (en triage / en consulta), `next` (hasta 5), `rest` (atenuado), `attendedToday`, reloj (cliente).
- Si la TV se desconecta, al reconectar hace `GET /api/queue/day` + suscripción (estado eventualmente consistente).

---

## Parte 9 · Tareas programadas (Laravel Queues + Horizon)

| Trabajo | Schedule (Kernel) | Lógica |
|---|---|---|
| `SendAppointmentReminder` | 24 h y 2 h antes de cada cita | Busca citas `agendada/pagada/check_in` → email + SMS (Notifications) |
| `ExpireWaitlistOffers` | cada minuto | `updateWhere status='oferta' AND offer_expires_at < now` → `expirada`; ofrece al siguiente |
| `ReconcileCulqiOrders` | diario 23:30 | Cruza órdenes Culqi con `payments` (Parte 7.3) |
| `ProcessLateCancellationRefund` | cada 5 min | Citas `cancelada` con pago Culqi y sin `refunded_at` → reembolso |
| `CleanupRefreshTokens` | diario | Elimina `refresh_tokens` expirados/revocados |
| `ExportReport` | bajo demanda | Genera CSV/PDF de reportes → S3 + URL firmada |

> `confirmWindowMin` (15 min) de `settings` alimenta `ExpireWaitlistOffers` y el countdown del frontend (`useCountdown`). Horizon se ejecuta como proceso separado (`php artisan horizon`).

---

## Parte 10 · Integraciones externas

### 10.1 RENIEC (validación de DNI)

- Consulta por DNI en registro y en check-in (opcional): `App\Services\DniService` (proxy con cache Redis 30 días y tasa limitada).
- Proveedores peruanos habituales: API oficial RENIEC (servicios web para empresas) o agregadores con token (ApiInti, apis.net.pe, JSON.pe). El servicio implementa una **interfaz `DniProvider`** para poder cambiar de proveedor sin tocar el dominio.

### 10.2 Email / SMS

- Email: `Mail` de Laravel (SMTP: Mailgun/SendGrid/Postmark). Plantillas Blade: confirmación de cita, recordatorio 24 h/2 h, cupo de lista de espera, comprobante, recuperación de contraseña.
- SMS: proveedor peruano (p. ej. Twilio o agregador local) vía `Notification` canal propio. El prototipo solo muestra toasts → aquí se implementa el envío real encolado.

### 10.3 Storage de documentos (S3 / R2 / MinIO)

- PDFs generados por `DocumentService` con `dompdf` (historial clínico con membrete — replicar `clinicPdf.js`/`clinic.js` —, ficha del médico, comprobantes `R-2026-XXXX`).
- Buckets privados + **URLs firmadas** de descarga (7 días, `Storage::temporaryUrl`). Metadatos: `appointment_id`, `type`, `emitted_at`, `emitted_by`.
- No se guardan datos de tarjeta en ningún documento.

---

## Parte 11 · Seguridad

| Control | Implementación |
|---|---|
| Contraseñas | `Hash::make` (bcrypt, cost 12); política del prototipo (≥6, mayúscula, número) |
| Tokens | Sanctum access 15 min + refresh rotativo hasheado en BD (`refresh_tokens`); revocación en logout |
| Datos sensibles | AES-256-GCM en reposo para `patients.dni`, `patients.address` y datos clínicos si el plan lo requiere (clave `DATA_ENC_KEY`, servicio `DataEncryption`) |
| Ley 29733 | Consentimiento en registro (`consent_29733`, `consent_at`), aviso de privacidad, derecho de acceso/rectificación (endpoints de datos personales), auditoría de accesos al historial |
| Autorización | Middleware `EnsureRole` + relación clínica (Parte 4.5); CORS restringido a `FRONTEND_URL` |
| Rate limiting | `RateLimiter` por ruta: login 5/min, registro 3/h, webhooks 100/min por IP |
| Headers | Middleware con CSP, HSTS, frame-ancestors para la TV |
| Validación | `FormRequest` en todos los body/query/params |
| Errores | Exception handler: nunca exponer stack ni detalles de BD; JSON consistente |
| Logs | Log de Laravel estructurado con `request-id` (middleware), niveles dev/prod |
| Webhooks | Firma Culqi v2 verificada antes de procesar |
| Pruebas de seguridad | OWASP top-10 básico: inyección (Eloquent), XSS (escapado en frontend), CSRF (tokens en header, Sanctum SPA o bearer) |

---

## Parte 12 · Despliegue y ambientes

### 12.1 docker-compose

```yaml
services:
  postgres:
    image: postgres:16-alpine
    environment: { POSTGRES_DB: sgcm_cmas, POSTGRES_USER: cmas, POSTGRES_PASSWORD: ... }
    volumes: [ pgdata:/var/lib/postgresql/data ]
    healthcheck: { test: ["CMD-SHELL", "pg_isready -U cmas"], interval: 5s }

  redis:
    image: redis:7-alpine

  app:
    build: { context: ./backend }
    command: php artisan serve --host=0.0.0.0 --port=8000   # dev
    # prod: php-fpm + nginx (imagen multi-stage) o Laravel Octane
    ports: [ "8000:8000" ]
    environment: { DB_HOST: postgres, REDIS_HOST: redis, APP_ENV: production }
    depends_on: [ postgres, redis ]

  queue:
    build: { context: ./backend }
    command: php artisan queue:work redis --sleep=1 --tries=3   # colas

  horizon:
    build: { context: ./backend }
    command: php artisan horizon                        # panel + workers (prod)

  scheduler:
    build: { context: ./backend }
    command: php artisan schedule:work                   # tareas programadas

  reverb:
    build: { context: ./backend }
    command: php artisan reverb:start                    # WebSockets (puerto 8080)
    ports: [ "8080:8080" ]
volumes: { pgdata: {} }
```

> En producción se recomienda **Laravel Octane** (Swoole/RoadRunner) para alto rendimiento, o `php-fpm` + nginx estándar. El scheduler usa `schedule:work` (o cron `* * * * * php artisan schedule:run`).

### 12.2 Pipeline CI/CD (GitHub Actions)

1. **CI** (push/PR): `composer install` → `pint --test` → `php artisan test` (PHPUnit, BD+Redis de test) → `npm run build` (solo si hay assets).
2. **Migrations**: `php artisan migrate --force` (job con acceso a la BD de prod, con lock y backup previo).
3. **CD**: build de imagen → push al registry → deploy (Render/Fly/VPS con watchtower).

### 12.3 Ambientes y datos

| Ambiente | Uso | BD | Seed |
|---|---|---|---|
| dev | desarrollo local | docker-compose | sí (mock.js) |
| test | CI/e2e | efímera | parcial |
| staging | validación de integraciones (Culqi test, RENIEC test) | clon anonimizado | sí |
| prod | producción | real | no |

**Backups**: `pg_dump` diario + WAL (PITR) con retención 30 días; restauración probada cada mes; backups cifrados.

---

## Parte 13 · Orden de implementación (roadmap en fases)

| Fase | Contenido | Depende de |
|---|---|---|
| **0 · Scaffold** | `composer create-project laravel/laravel`, PostgreSQL, Eloquent, Scribe, errores JSON unificados, Docker + CI básico | — |
| **1 · Datos** | Migración `0001_init` (DDL de la Parte 2), `DatabaseSeeder` (mock), modelo `Setting` | 0 |
| **2 · Auth** | Sanctum login + refresh rotativo, `EnsureRole`, registro paciente (29733), recuperar clave, rate limits | 1 |
| **3 · Catálogos** | specialties, consultorios, doctors, schedules, exceptions (CRUD + API Resources) | 2 |
| **4 · Disponibilidad** | `/api/availability` (cache Redis) + reserva con transacción (409 + alternativas) | 3 |
| **5 · Citas** | CRUD citas, check-in, cancelar (12 h), reprogramar, turnos A-00X | 4 |
| **6 · Triaje** | cola de enfermería, formulario, historial | 5 |
| **7 · Cola + TV** | `/api/queue/*`, transiciones, Reverb + eventos, token TV, tablero | 5 |
| **8 · Pagos** | caja, Culqi charge, webhooks, verificación, saldo 50%, reembolsos | 5 |
| **9 · Lista de espera** | inscripción, ofertas, confirm/expire + jobs Horizon | 5, 8 |
| **10 · Historial + PDF** | relación clínica, historial, `DocumentService` (dompdf) → S3 | 6 |
| **11 · Notificaciones** | emails/SMS (recordatorios), plantillas Blade, broadcast `NotificationCreated` | 9 |
| **12 · Admin** | usuarios CRUD, reportes, auditoría, settings, exportación | todo |
| **13 · Hardening** | cifrado en reposo, RENIEC, monitoreo, backups, e2e completos | todo |

> Cada fase termina con: endpoints + Scribe actualizados, tests del flujo crítico y documentación reflejada en `README.md` / `docs/MODULOS.md` (regla de `AGENTS.md`).

---

## Parte 14 · Lo que falta en la estructura propuesta (gaps detectados)

La arquitectura C4 que se brindó era de **nivel 1-2** (contexto y contenedores). Para iniciar el backend faltaban estos elementos, que este documento ya incorpora:

1. **Migraciones y versionado de esquema** (Laravel migrations) — la estructura solo mencionaba PostgreSQL como "fuente de verdad".
2. **Contrato de API completo** (Parte 5): la estructura no listaba endpoints, ni FormRequests, ni el formato de error/paginación, ni Scribe.
3. **Manejo real de la disponibilidad**: el prototipo tiene `slots` fijos por médico; falta el diseño de plantillas semanales (`doctor_schedules`) + días bloqueados, que aquí se resuelve.
4. **Turnos por día (A-00X) en BD** — la estructura no decía cómo se generan; resuelto en Parte 2.4 con índice único `(date, turno)`.
5. **Pipeline CI/CD, backups y plan de recuperación** (Parte 12) — ausentes en el diagrama.
6. **Monitoreo, logs centralizados y alertas** — la estructura solo mencionaba "logs" de pasada; falta definir request-id, métricas y umbrales de alerta (p. ej. tasa de webhooks fallidos).
7. **Pruebas automatizadas** (unit + feature) — no estaba en el diagrama.
8. **Conciliación de pagos y manejo de fallos** (reintentos, `order.expired`) — la estructura solo decía "webhooks".
9. **Almacenamiento y emisión de PDFs en detalle** — la estructura decía "S3/Cloud Storage" pero no definía URLs firmadas, metadatos ni el servicio `DocumentService`.
10. **Autorización por recurso** (relación clínica médico-paciente) — el diagrama mencionaba roles, pero no la regla de acceso por recurso que el prototipo ya exige.
11. **TV multi-consultorio** — la estructura asumía una pantalla global; hay que definir token de solo lectura y canales por consultorio.
12. **Métricas de tiempo de espera por médico** (siguiente paso del README) — no definida en la estructura; los datos (check_in_time, timestamps de transición) quedan listos para calcularse en la Parte 2.
13. **Migración de datos del prototipo** → tabla de mapeo `mock.js` → entidades (Parte 0.3); no estaba contemplada.

---

## Parte 15 · Tabla de mapeo prototipo → backend (referencia rápida)

| Prototipo (`src/…`) | Backend Laravel |
|---|---|
| `data/mock.js` `SPECIALTIES` | tabla `specialties` |
| `CONSULTORIOS` (especialidades[]) | `consultorios` + `consultorio_specialties` |
| `DOCTORS` (`slots[]`) | `doctors` + `doctor_schedules` + `doctor_date_exceptions` |
| `PATIENTS` / `ME` | `patients` (+ `users` para la cuenta) |
| `USERS` | `users` (rol real, no heurística) |
| `INITIAL_APPOINTMENTS` (`diag`, `triage` embebidos) | `appointments` + `diagnoses` + `triages` |
| `INITIAL_PAYMENTS` (`gateway`, `opRef`) | `payments` (`culqi_order_id`, `culqi_charge_id`, `culqi_data`) |
| `INITIAL_WAITLIST` (`offer` embebido) | `waitlist_entries` (columnas de oferta) |
| `AUDIT_LOG` ("Hace unos segundos") | `audit_log` (timestamps reales, IP, route) |
| `settings` (AppContext) | tabla `settings` (clave-valor JSONB) |
| `AppContext` acciones | Services de cada módulo (Parte 3) |
| `queuedToday`, `turnoOf`, `nextTurno` | `QueueService` + scopes Eloquent + Parte 2.4 |
| `PaymentGateway` (simulado) | `PaymentService` con Culqi (Parte 7) |
| `clinicPdf.js` (membrete) | `DocumentService` con dompdf (Parte 10.3) |
| `useCountdown` (15 min) | `settings.waitlistWindowMin` + job de expiración |
| TV por `storage` event | Reverb canales por consultorio (Parte 8) |