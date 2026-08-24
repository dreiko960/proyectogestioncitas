<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        
        
        
        
        DB::unprepared(<<<'SQL'
            DROP TYPE IF EXISTS audit_sev CASCADE;
            DROP TYPE IF EXISTS waitlist_status CASCADE;
            DROP TYPE IF EXISTS paid_type CASCADE;
            DROP TYPE IF EXISTS payment_method CASCADE;
            DROP TYPE IF EXISTS payment_status CASCADE;
            DROP TYPE IF EXISTS appointment_status CASCADE;
            DROP TYPE IF EXISTS user_role CASCADE;

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

            -- ------------------------------------------------------------
            -- USERS · cuentas del sistema (5 roles del prototipo)
            -- ------------------------------------------------------------
            CREATE TABLE usuarios (
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

            -- ------------------------------------------------------------
            -- PATIENTS · perfil clínico (1:1 con users rol=paciente)
            -- DNI y dirección se cifran en la capa de aplicación (AES-256-GCM)
            -- ------------------------------------------------------------
            CREATE TABLE pacientes (
              id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              user_id    UUID NOT NULL UNIQUE REFERENCES usuarios(id) ON DELETE CASCADE,
              dni        VARCHAR(8) NOT NULL UNIQUE,
              phone      VARCHAR(15),
              dob        DATE NOT NULL,
              address    TEXT,
              consent_29733 BOOLEAN NOT NULL DEFAULT FALSE,   -- Ley N.º 29733
              consent_at TIMESTAMPTZ,
              created_at TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            -- ------------------------------------------------------------
            -- SPECIALTIES · catálogo (precios del prototipo)
            -- ------------------------------------------------------------
            CREATE TABLE especialidades (
              id       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              code     VARCHAR(30) NOT NULL UNIQUE,   -- 'medicina','pediatria',...
              name     VARCHAR(80) NOT NULL,
              icon     VARCHAR(30) NOT NULL DEFAULT 'stethoscope',
              price    NUMERIC(10,2) NOT NULL CHECK (price > 0),
              "desc"   TEXT,                            -- desc es palabra reservada en PG; va citado
              active   BOOLEAN NOT NULL DEFAULT TRUE
            );

            -- ------------------------------------------------------------
            -- CONSULTORIOS + especialidades asociadas (M:N)
            -- ------------------------------------------------------------
            CREATE TABLE consultorios (
              id      UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              nombre  VARCHAR(80) NOT NULL,
              piso    VARCHAR(20) NOT NULL,
              area    VARCHAR(80),
              activo  BOOLEAN NOT NULL DEFAULT TRUE
            );

            CREATE TABLE consultorio_especialidad (
              consultorio_id UUID NOT NULL REFERENCES consultorios(id) ON DELETE CASCADE,
              specialty_id   UUID NOT NULL REFERENCES especialidades(id)   ON DELETE CASCADE,
              PRIMARY KEY (consultorio_id, specialty_id)
            );

            -- ------------------------------------------------------------
            -- DOCTORS · perfil profesional (1:1 con users rol=medico)
            -- ------------------------------------------------------------
            CREATE TABLE doctores (
              id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              user_id        UUID NOT NULL UNIQUE REFERENCES usuarios(id) ON DELETE CASCADE,
              initials       VARCHAR(5) NOT NULL,
              specialty_id   UUID NOT NULL REFERENCES especialidades(id),
              consultorio_id UUID REFERENCES consultorios(id),
              phone          VARCHAR(15),
              bio            TEXT,
              rating         NUMERIC(3,2) NOT NULL DEFAULT 0,
              rating_count   INTEGER NOT NULL DEFAULT 0,
              studies        VARCHAR(120),
              exp            INTEGER NOT NULL DEFAULT 0,
              created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            -- ------------------------------------------------------------
            -- DOCTOR_SCHEDULES · plantilla de disponibilidad semanal
            -- (en el prototipo eran slots concretos; aquí: franjas por día de semana)
            -- ------------------------------------------------------------
            CREATE TABLE horarios_doctores (
              id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              doctor_id    UUID NOT NULL REFERENCES doctores(id) ON DELETE CASCADE,
              day_of_week  SMALLINT NOT NULL CHECK (day_of_week BETWEEN 0 AND 6), -- 0=Dom
              start_time   TIME NOT NULL,
              end_time     TIME NOT NULL,
              UNIQUE (doctor_id, day_of_week, start_time, end_time)
            );

            -- Días bloqueados (vacaciones, ausencias puntuales)
            CREATE TABLE excepciones_doctores (
              id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              doctor_id  UUID NOT NULL REFERENCES doctores(id) ON DELETE CASCADE,
              date       DATE NOT NULL,
              reason     VARCHAR(120),
              UNIQUE (doctor_id, date)
            );

            -- ------------------------------------------------------------
            -- APPOINTMENTS · citas (núcleo del sistema)
            -- ------------------------------------------------------------
            CREATE TABLE citas (
              id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              code           VARCHAR(12) NOT NULL UNIQUE,   -- 'C-1042' legible
              patient_id     UUID NOT NULL REFERENCES pacientes(id),
              doctor_id      UUID NOT NULL REFERENCES doctores(id),
              specialty_id   UUID NOT NULL REFERENCES especialidades(id),
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
            CREATE INDEX idx_appt_patient ON citas (patient_id, date DESC);
            CREATE INDEX idx_appt_doctor_day ON citas (doctor_id, date);
            CREATE INDEX idx_appt_day_status ON citas (date, status);
            CREATE UNIQUE INDEX idx_appt_turno_day ON citas (date, turno) WHERE turno IS NOT NULL;

            -- ------------------------------------------------------------
            -- TRIAGES · triaje de enfermería (1:1 con cita)
            -- ------------------------------------------------------------
            CREATE TABLE triajes (
              id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              appointment_id UUID NOT NULL UNIQUE REFERENCES citas(id) ON DELETE CASCADE,
              nurse_id       UUID NOT NULL REFERENCES usuarios(id),
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

            -- ------------------------------------------------------------
            -- DIAGNOSES · diagnóstico médico (1:1 con cita)
            -- ------------------------------------------------------------
            CREATE TABLE diagnosticos (
              id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              appointment_id UUID NOT NULL UNIQUE REFERENCES citas(id) ON DELETE CASCADE,
              doctor_id      UUID NOT NULL REFERENCES usuarios(id),
              dx             TEXT NOT NULL,
              notes          TEXT,
              at             TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            -- ------------------------------------------------------------
            -- PAYMENTS · pagos (caja + Culqi). El prototipo suma pagos pagados
            -- para calcular el total (paidTotalOf); se mantiene la misma lógica.
            -- ------------------------------------------------------------
            CREATE TABLE pagos (
              id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              code           VARCHAR(12) NOT NULL UNIQUE,     -- 'P-0813' / 'R-2026-0813'
              appointment_id UUID NOT NULL REFERENCES citas(id),
              patient_id     UUID NOT NULL REFERENCES pacientes(id),
              amount         NUMERIC(10,2) NOT NULL CHECK (amount >= 0),
              method         payment_method NOT NULL,
              status         payment_status NOT NULL DEFAULT 'pendiente_verificacion',
              paid_type      paid_type NOT NULL,
              receipt_code   VARCHAR(16),                      -- comprobante 'R-2026-XXXX'
              verified_by    UUID REFERENCES usuarios(id),        -- recepcionista / NULL=Sistema
              gateway        BOOLEAN NOT NULL DEFAULT FALSE,   -- pagado por Culqi
              culqi_order_id VARCHAR(60),                      -- order_xxx
              culqi_charge_id VARCHAR(60),                     -- charge_xxx
              culqi_data     JSONB,                            -- payload del webhook
              refunded_at    TIMESTAMPTZ,
              created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
              UNIQUE (appointment_id, culqi_order_id)          -- idempotencia de webhooks
            );

            CREATE INDEX idx_pay_appt ON pagos (appointment_id);
            CREATE INDEX idx_pay_status ON pagos (status);

            -- ------------------------------------------------------------
            -- WAITLIST_ENTRIES · lista de espera de cupos (módulo del paciente)
            -- ------------------------------------------------------------
            CREATE TABLE lista_espera (
              id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              code            VARCHAR(10) NOT NULL UNIQUE,     -- 'WL-008'
              patient_id      UUID NOT NULL REFERENCES pacientes(id),
              specialty_id    UUID NOT NULL REFERENCES especialidades(id),
              doctor_id       UUID NOT NULL REFERENCES doctores(id),
              preferred       VARCHAR(160),
              position        INTEGER NOT NULL,
              status          waitlist_status NOT NULL DEFAULT 'en_espera',
              offer_date      DATE,                            -- cupo ofrecido
              offer_time      TIME,
              offer_expires_at TIMESTAMPTZ,                    -- ventana (15 min)
              confirm_window_min INTEGER NOT NULL DEFAULT 15,  -- settings.waitlistWindowMin
              created_appointment_id UUID REFERENCES citas(id),
              enrolled_at     TIMESTAMPTZ NOT NULL DEFAULT now()
            );

            CREATE INDEX idx_wl_patient ON lista_espera (patient_id);
            CREATE INDEX idx_wl_spec_status ON lista_espera (specialty_id, status);
            CREATE INDEX idx_wl_offer_expiry ON lista_espera (status, offer_expires_at)
              WHERE status = 'oferta';

            -- ------------------------------------------------------------
            -- SETTINGS · reglas de negocio (Admin → Configuración)
            -- ------------------------------------------------------------
            CREATE TABLE configuraciones (
              key        VARCHAR(60) PRIMARY KEY,
              value      JSONB NOT NULL
            );

            INSERT INTO configuraciones (key, value) VALUES
              ('minCancelHours',     '{"v": 12}'),
              ('minReserveHours',    '{"v": 2}'),
              ('tokenExpiryMin',     '{"v": 30}'),
              ('waitlistWindowMin',  '{"v": 15}'),
              ('lateFeeDays',        '{"v": 2}'),
              ('nonWorkingDays',     '{"v": ["2026-08-01","2026-08-02","2026-07-28","2026-07-29"]}');

            -- ------------------------------------------------------------
            -- AUDIT_LOG · auditoría persistente (reemplaza el mock 'Hace unos segundos')
            -- ------------------------------------------------------------
            CREATE TABLE registro_auditoria (
              id         BIGSERIAL PRIMARY KEY,
              at         TIMESTAMPTZ NOT NULL DEFAULT now(),
              user_id    UUID REFERENCES usuarios(id),
              email      VARCHAR(160),
              action     VARCHAR(80) NOT NULL,
              detail     TEXT,
              sev        audit_sev NOT NULL DEFAULT 'info',
              ip         INET,
              user_agent TEXT,
              route      VARCHAR(120),
              method     VARCHAR(10)
            );

            CREATE INDEX idx_audit_at ON registro_auditoria (at DESC);
            CREATE INDEX idx_audit_user ON registro_auditoria (user_id, at DESC);

            -- ------------------------------------------------------------
            -- REFRESH_TOKENS · sesiones rotativas
            -- ------------------------------------------------------------
            CREATE TABLE tokens_refresco (
              id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
              user_id    UUID NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
              token_hash TEXT NOT NULL UNIQUE,        -- SHA-256 del token
              expires_at TIMESTAMPTZ NOT NULL,
              revoked_at TIMESTAMPTZ,
              created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
              ip         INET,
              user_agent TEXT
            );

            CREATE INDEX idx_rt_user ON tokens_refresco (user_id);
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS tokens_refresco CASCADE;
            DROP TABLE IF EXISTS registro_auditoria CASCADE;
            DROP TABLE IF EXISTS configuraciones CASCADE;
            DROP TABLE IF EXISTS lista_espera CASCADE;
            DROP TABLE IF EXISTS pagos CASCADE;
            DROP TABLE IF EXISTS diagnosticos CASCADE;
            DROP TABLE IF EXISTS triajes CASCADE;
            DROP TABLE IF EXISTS citas CASCADE;
            DROP TABLE IF EXISTS excepciones_doctores CASCADE;
            DROP TABLE IF EXISTS horarios_doctores CASCADE;
            DROP TABLE IF EXISTS doctores CASCADE;
            DROP TABLE IF EXISTS consultorio_especialidad CASCADE;
            DROP TABLE IF EXISTS consultorios CASCADE;
            DROP TABLE IF EXISTS especialidades CASCADE;
            DROP TABLE IF EXISTS pacientes CASCADE;
            DROP TABLE IF EXISTS usuarios CASCADE;

            DROP TYPE IF EXISTS user_role CASCADE;
            DROP TYPE IF EXISTS appointment_status CASCADE;
            DROP TYPE IF EXISTS payment_status CASCADE;
            DROP TYPE IF EXISTS payment_method CASCADE;
            DROP TYPE IF EXISTS paid_type CASCADE;
            DROP TYPE IF EXISTS waitlist_status CASCADE;
            DROP TYPE IF EXISTS audit_sev CASCADE;
        SQL);
    }
};
