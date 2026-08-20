# SGCM-CMAS · Requisitos del sistema — Documento funcional

Documento de **requisitos funcionales y no funcionales** del SGCM-CMAS, derivados del prototipo (`README.md`, `docs/MODULOS.md`) y de los planes de implementación (`docs/BACKEND.md`, `docs/FRONTEND.md`). Sirve como puente entre lo que hace el prototipo y lo que debe cumplir el sistema en producción: cada requisito tiene **criterios de aceptación medibles** que las pruebas (`docs/PRUEBAS.md`) deben verificar.

> **Convenciones**
>
> - ID: `RF-<MODULO>-<N>` (funcional) / `RNF-<N>` (no funcional).
> - Prioridad: **Alta** (bloquea el lanzamiento) · **Media** (deseable en v1) · **Baja** (posterior).
> - Cada requisito cita su trazabilidad: página del prototipo → endpoint (BACKEND.md) → componente (FRONTEND.md).

---

## 1. Índice

1. [Módulo de autenticación y cuentas](#2-módulo-de-autenticación-y-cuentas)
2. [Módulo de catálogos y disponibilidad](#3-módulo-de-catálogos-y-disponibilidad)
3. [Módulo de citas](#4-módulo-de-citas)
4. [Módulo de pagos (caja y Culqi)](#5-módulo-de-pagos-caja-y-culqi)
5. [Módulo de triaje](#6-módulo-de-triaje)
6. [Módulo de cola del día y pantalla TV](#7-módulo-de-cola-del-día-y-pantalla-tv)
7. [Módulo de lista de espera](#8-módulo-de-lista-de-espera)
8. [Módulo de historial clínico y documentos](#9-módulo-de-historial-clínico-y-documentos)
9. [Módulo de administración](#10-módulo-de-administración)
10. [Módulo de notificaciones](#11-módulo-de-notificaciones)
11. [Reglas de negocio transversales](#12-reglas-de-negocio-transversales)
12. [Requisitos no funcionales](#13-requisitos-no-funcionales)
13. [Matriz de trazabilidad prototipo → requisito](#14-matriz-de-trazabilidad-prototipo--requisito)

---

## 2. Módulo de autenticación y cuentas

| ID | Requisito | Prioridad | Criterio de aceptación |
|---|---|---|---|
| RF-AUTH-01 | Login con correo y contraseña para los 5 roles | Alta | POST `/auth/login` valida credenciales; devuelve access (15 min) + refresh (30 días); rol real de BD, no heurística |
| RF-AUTH-02 | Renovación automática de sesión | Alta | Con refresh válido, cualquier llamada 401 se reintenta una vez y la sesión no se pierde; si el refresh expira → logout y redirect a `/login` |
| RF-AUTH-03 | Cierre de sesión revoca el refresh | Alta | POST `/auth/logout` invalida el token; recargar no restaura la sesión |
| RF-AUTH-04 | Registro público solo de pacientes | Alta | Valida nombre ≥5, correo/DNI/celular **únicos**, clave (≥6, mayúscula, número), términos y **consentimiento Ley 29733** obligatorio (`consent_29733=true`) |
| RF-AUTH-05 | Validación de DNI contra RENIEC | Media | En registro/check-in, si la integración está activa, el DNI debe coincidir con nombre/fecha de nacimiento; error claro si no |
| RF-AUTH-06 | Recuperación de contraseña en 2 pasos | Media | Enlace de un solo uso con expiración `tokenExpiryMin` (30 min); nueva clave cumple la política y revoca sesiones previas |
| RF-AUTH-07 | Alta de usuarios por el administrador | Alta | Admin crea cuentas de cualquier rol con correo único; puede activar/desactivar (usuario inactivo no puede iniciar sesión) |
| RF-AUTH-08 | Protección de rutas y acciones por rol | Alta | Sin sesión → `/login`; sesión con rol no autorizado → home de su rol; el backend devuelve 403 en acciones no permitidas |
| RF-AUTH-09 | Bloqueo por intentos fallidos | Alta | 5 intentos/min por email+IP → bloqueo temporal 429/423 con mensaje; se registra en auditoría (sev `warning`) |

**Trazabilidad:** `Login.jsx`, `Register.jsx`, `RecoverPassword.jsx`, `NewPassword.jsx` (FRONTEND Parte 4) · `/auth/*`, `/users*` (BACKEND Parte 4 y 5.1-5.2).

---

## 3. Módulo de catálogos y disponibilidad

| ID | Requisito | Prioridad | Criterio de aceptación |
|---|---|---|---|
| RF-CAT-01 | Especialidades con precio visible | Alta | GET `/specialties` devuelve las 7 especialidades activas con su precio; el admin puede editar precio/activar con advertencia si hay médicos asociados |
| RF-CAT-02 | Consultorios con pisos, áreas y especialidades | Alta | GET `/consultorios`; admin asigna especialidades (M:N) y bloquea consultorios en uso (advertencia) |
| RF-CAT-03 | Perfiles de médico con rating y consultorio | Alta | GET `/doctors` y `/doctors/:id` con bio, estudios, experiencia, rating y consultorio asignado |
| RF-CAT-04 | Plantilla semanal de disponibilidad | Alta | El médico define franjas por día de la semana (`doctor_schedules`) y días bloqueados (`doctor_date_exceptions`); sin solapamientos (regla del prototipo `Availability.jsx`) |
| RF-CAT-05 | Búsqueda pública de horarios | Alta | GET `/availability` cruza plantillas con citas activas y días no laborables; devuelve solo franjas libres en el rango; una especialidad sin cupos muestra estado vacío con CTA a lista de espera |
| RF-CAT-06 | Cache de horarios libres | Media | GET `/doctors/:id/slots` con cache Redis (30 s) para no golpear la BD en cada búsqueda |

**Trazabilidad:** `SearchAvailability.jsx`, `Availability.jsx`, `Specialties.jsx`, `Consultorios.jsx` · `/specialties`, `/consultorios`, `/doctors*`, `/availability` (BACKEND 5.3-5.4).

---

## 4. Módulo de citas

| ID | Requisito | Prioridad | Criterio de aceptación |
|---|---|---|---|
| RF-CIT-01 | Reserva de cita en wizard de 3 pasos | Alta | Especialidad → médico/calendario → confirmación; el horario se bloquea en el momento de la reserva |
| RF-CIT-02 | **No doble reserva** (consistencia fuerte) | Alta | Dos reservas concurrentes sobre el mismo doctor/fecha/hora: solo una gana; la otra recibe **409 con 3 alternativas** sugeridas |
| RF-CIT-03 | Pago anticipado 50% / 100% o en caja | Alta | Abono 50% → cita `pagada` + `paid_type='adelanto'` y **check-in habilitado**; total → `paid_type='total'`; caja → cita `agendada` |
| RF-CIT-04 | Check-in móvil del paciente | Alta | Solo desde `agendada`/`pagada`; POST `/appointments/:id/checkin` → `check_in`; aviso de llegar 10 min antes con DNI. La cita queda `check_in` hasta que recepción la envía a la cola (`en_espera_triaje`) con turno |
| RF-CIT-05 | Cancelación con aviso de cancelación tardía | Alta | Si faltan <12 h (`minCancelHours`) → advertencia explícita (modal) antes de confirmar; libera la franja; auditoría sev `warning` |
| RF-CIT-06 | Reprogramación | Media | Mueve la cita a otra franja libre con validación de no solapamiento; estado `reprogramada`; historial visible del cambio |
| RF-CIT-07 | Agenda del día por rol | Alta | Médico ve su agenda en timeline (filtros todos/en camino/por atender/en atención/documentadas); recepción ve todos los médicos con filtro por especialidad/médico |
| RF-CIT-08 | Alta rápida de paciente desde recepción | Media | `NewAppointment.jsx` crea paciente (si no existe) y cita en el mismo flujo |

**Trazabilidad:** `BookAppointment.jsx`, `MyAppointments.jsx`, `PatientCheckin.jsx`, `Agenda.jsx`, `NewAppointment.jsx` · `/appointments*` (BACKEND 5.5 y 6.1).

---

## 5. Módulo de pagos (caja y Culqi)

| ID | Requisito | Prioridad | Criterio de aceptación |
|---|---|---|---|
| RF-PAG-01 | Cobro en línea con Culqi (50%/100%) | Alta | El navegador tokeniza con Culqi.js; el backend cobra con `sk_*`; el frontend **nunca ve datos de tarjeta**; pago `pagado` con `gateway=true` y `op_ref` |
| RF-PAG-02 | Verificación de webhooks con firma | Alta | Webhook `order.paid/expired/charge.failed` rechazado si la firma no es válida; procesamiento **idempotente** por `culqi_order_id` |
| RF-PAG-03 | Cobro en caja con comprobante | Alta | Recepción cobra (efectivo/Yape/Plin/transferencia) y genera comprobante `R-2026-XXXX`; pago `pagado` verificado por recepcionista |
| RF-PAG-04 | Completar saldo de abonos 50% | Alta | Recepción cobra el saldo restante; el total de pagos `pagado` alcanza el precio y la cita pasa a `paid_type='total'` (queda al 100%) |
| RF-PAG-05 | Pagos declarados por el paciente | Media | Yape/Plin/Transferencia declarados → `pendiente_verificacion`; recepción los confirma (<15 min) |
| RF-PAG-06 | Reembolso por cancelación | Alta | Si la cita pagada por Culqi se cancela → reembolso (job automático o manual según política); pago `reembolsado`; auditoría |
| RF-PAG-07 | Conciliación diaria | Media | Job cruza órdenes de Culqi del día con `payments`; detecta cargos sin webhook y genera reporte al admin |
| RF-PAG-08 | Consulta de mis pagos | Media | Paciente ve sus pagos con estado, tipo (`Abono 50%`/`Pago total`), método y comprobante descargable |

**Trazabilidad:** `PaymentGateway.jsx`, `Payment.jsx`, `PatientPayments.jsx` · `/payments*`, `/webhooks/culqi` (BACKEND 5.6 y 7).

---

## 6. Módulo de triaje

| ID | Requisito | Prioridad | Criterio de aceptación |
|---|---|---|---|
| RF-TRI-01 | Cola de triaje ordenada por tiempo de espera | Alta | GET `/triage/queue` lista `en_espera_triaje` por turno/tiempo de espera + en progreso; vista de enfermería (`TriageQueue.jsx`) |
| RF-TRI-02 | Formulario de signos vitales | Alta | PA, temperatura, FC, peso, talla, motivo, alergias, observaciones; guardado solo desde `en_espera_triaje` → `triaje_completado` |
| RF-TRI-03 | Historial de triajes del turno | Media | GET `/triage/history?date=` con enfermera y hora reales |
| RF-TRI-04 | Transición desde el tablero de cola | Alta | `finalize-triage` desde el tablero genera un triaje mínimo (motivo = reason, observación "registrado desde la lista de espera") |

**Trazabilidad:** `TriageQueue.jsx`, `TriageForm.jsx`, `TriageHistory.jsx`, `WaitingQueue.jsx` · `/triage*` (BACKEND 5.7).

---

## 7. Módulo de cola del día y pantalla TV

| ID | Requisito | Prioridad | Criterio de aceptación |
|---|---|---|---|
| RF-COL-01 | Turnos secuenciales A-00X por día | Alta | El check-in presencial asigna `A-00X` (siguiente del día) y pasa la cita a `en_espera_triaje`, tanto desde `pagada` como desde `check_in`; único por `(date, turno)`; la TV los muestra en orden |
| RF-COL-02 | Tablero con pipeline de cola | Alta | Citas `en_espera_triaje → en_triaje → triaje_completado → en_atencion` ordenadas por turno, con acciones según estado y stats en vivo |
| RF-COL-03 | Actualización en tiempo real | Alta | Al llamar/pasar a un paciente, el tablero y la TV se actualizan **sin recargar** (evento `queue.updated`); caída de red → refetch cada 15 s |
| RF-COL-04 | Pantalla TV kiosco por consultorio | Alta | `/tv` con token de solo lectura; muestra AHORA (triaje/consulta) en grande, próximos 5 y resto atenuado; reloj y contador de atendidos |
| RF-COL-05 | Reconexión automática de la TV | Alta | Si el socket se cae, reconecta con backoff y re-sincroniza `GET /queue/day` |

**Trazabilidad:** `WaitingQueue.jsx`, `TvDisplay.jsx`, `Checkin.jsx` · `/queue*`, `/tv/token` (BACKEND 5.8 y 8).

---

## 8. Módulo de lista de espera

| ID | Requisito | Prioridad | Criterio de aceptación |
|---|---|---|---|
| RF-LSE-01 | Inscripción por especialidad/médico/preferencia | Alta | POST `/waitlist` asigna posición cronológica; el paciente ve su posición `~N°` |
| RF-LSE-02 | Oferta de cupo con ventana de confirmación | Alta | Al liberarse un cupo, el primero en espera recibe oferta con expiración = `waitlistWindowMin` (15 min); cuenta regresiva visible (`useCountdown`) |
| RF-LSE-03 | Confirmar crea la cita automáticamente | Alta | POST `/waitlist/:id/confirm` crea la cita (misma transacción anti doble reserva) + pago `pendiente_verificacion` |
| RF-LSE-04 | Rechazar mantiene la posición | Alta | POST `/waitlist/:id/reject` vuelve a `en_espera` sin perder posición; el cupo pasa al siguiente |
| RF-LSE-05 | Expiración automática | Alta | Worker marca `expirada` a las ofertas vencidas y ofrece al siguiente; el paciente ve el estado `expirada` con flujo de re-inscripción |
| RF-LSE-06 | Idempotencia de confirmación | Media | Confirmar dos veces (doble clic/recarga) no crea dos citas |

**Trazabilidad:** `Waitlist.jsx`, `WaitlistEnroll.jsx`, `WaitlistOffer.jsx`, `WaitlistExpired.jsx` · `/waitlist*` (BACKEND 5.9 y 6.4).

---

## 9. Módulo de historial clínico y documentos

| ID | Requisito | Prioridad | Criterio de aceptación |
|---|---|---|---|
| RF-HIS-01 | Historial clínico en línea de tiempo | Alta | Motivo, triaje completo, diagnóstico (severidad/notas), consultorio, turno y costo; expandible por cita (UX actual de `PatientHistory.jsx`) |
| RF-HIS-02 | **Regla de relación clínica** | Alta | Un médico solo ve historial de pacientes con los que tiene citas; si no → 403 + auditoría `Acceso denegado` (sev `danger`) |
| RF-HIS-03 | Descarga PDF con membrete oficial | Alta | GET `/documents/*` genera PDF (membrete `clinic.js`), lo sube a S3 y devuelve URL firmada; registra emisión (quién/cuándo) |
| RF-HIS-04 | Comprobantes de pago descargables | Media | Comprobante `R-2026-XXXX` disponible como PDF desde pagos |
| RF-HIS-05 | Registro de diagnóstico del médico | Alta | POST `/appointments/:id/diagnose` desde `en_atencion` → `documentada`; el diagnóstico alimenta el historial |

**Trazabilidad:** `PatientHistory.jsx`, `PatientDetail.jsx`, `Diagnosis.jsx`, `clinicPdf.js` · `/documents*`, `/appointments/patient/:pid` (BACKEND 5.5 y 10.3).

---

## 10. Módulo de administración

| ID | Requisito | Prioridad | Criterio de aceptación |
|---|---|---|---|
| RF-ADM-01 | Indicadores del centro | Alta | Citas del mes, tasa de cancelación, inasistencia, ingresos, ocupación por especialidad y tendencia semanal (`Dashboard.jsx`) |
| RF-ADM-02 | Gestión de usuarios y roles | Alta | CRUD de usuarios, activar/desactivar, correo único |
| RF-ADM-03 | Catálogo de especialidades y consultorios | Alta | Precios, activación, pisos/áreas y especialidades (RF-CAT-01/02) |
| RF-ADM-04 | Reportes exportables | Media | Exportación CSV/PDF **real** (reemplaza el toast simulado) |
| RF-ADM-05 | Auditoría consultable | Alta | GET `/audit` paginado con `at` real, usuario, IP, severidad y filtros |
| RF-ADM-06 | Configuración de reglas de negocio | Alta | `minCancelHours`, `tokenExpiryMin`, `waitlistWindowMin`, días no laborables; los cambios se aplican sin redeploy |
| RF-ADM-07 | Registro de veredictos en reportes | Baja | Veredictos Éxito/Advertencia/Bloqueado (UX actual) calculados con datos reales |

**Trazabilidad:** `admin/*` (7 páginas) · `/reports*`, `/audit`, `/settings`, `/users*` (BACKEND 5.2 y 5.10).

---

## 11. Módulo de notificaciones

| ID | Requisito | Prioridad | Criterio de aceptación |
|---|---|---|---|
| RF-NOT-01 | Recordatorio de cita 24 h antes | Alta | Email (+SMS) a citas `agendada/pagada/check_in` del día siguiente |
| RF-NOT-02 | Recordatorio 2 h antes | Media | Email (+SMS) con turno y consultorio si ya hizo check-in |
| RF-NOT-03 | Aviso de cupo de lista de espera | Alta | Al asignarse la oferta, notificar al paciente (email + campana) |
| RF-NOT-04 | Campana en el panel (tiempo real) | Media | Evento `notification.new` vía socket; lista de avisos en el topbar |
| RF-NOT-05 | Confirmación de reserva y comprobante | Media | Email de confirmación al reservar y al pagar (adjunta comprobante) |
| RF-NOT-06 | Intento de entrega y fallos | Media | El worker reintenta 3 veces; fallo → log + cola de pendientes visible al admin |

**Trazabilidad:** `PanelLayout.jsx` topbar (FRONTEND Parte 10) · Laravel Queues/Horizon (BACKEND 5.9 y Parte 9).

---

## 12. Reglas de negocio transversales

| Regla | Descripción | Criterio de aceptación |
|---|---|---|
| R-01 | Una franja = un doctor, una cita | Transacción con bloqueo; 409 con alternativas |
| R-02 | Turno = orden de llegada del día | Asignado solo en check-in; único por día |
| R-03 | Pipeline de cola secuencial | `en_espera_triaje → en_triaje → triaje_completado → en_atencion → (atendida|documentada)`; sin saltos |
| R-04 | Pago 50% habilita check-in | Cita `pagada` + `paid_type='adelanto'` entra directo a triaje en recepción |
| R-05 | Cancelación tardía < 12 h | Aviso obligatorio; auditoría `warning` |
| R-06 | Oferta de lista de espera 15 min | `offer_expires_at` = ahora + `waitlistWindowMin`; worker expira y pasa el cupo |
| R-07 | Relación clínica para historial | Médico con citas previas (no canceladas) con el paciente |
| R-08 | Días no laborables bloquean reservas | No se ofrecen ni reservan franjas en esos días |
| R-09 | ID legible de negocio | Citas `C-XXXX`, pagos `P-XXXX`/`R-2026-XXXX`, lista de espera `WL-XXX` (legibles para soporte) |
| R-10 | Registro de auditoría en acciones sensibles | Login, pagos, triaje, diagnóstico, cancelaciones, accesos denegados y cambios de config |

---

## 13. Requisitos no funcionales

| ID | Requisito | Criterio de aceptación |
|---|---|---|
| RNF-01 | Rendimiento de búsqueda de disponibilidad | P95 < 500 ms con cache Redis; sin golpear BD por búsqueda |
| RNF-02 | Disponibilidad | 99,5 % en horario de atención; TV tolera caídas de red con reconexión automática |
| RNF-03 | Tiempo real | Latencia de evento de cola a TV < 2 s (misma red) |
| RNF-04 | Seguridad de datos (Ley 29733) | Cifrado en reposo de DNI/dirección; consentimiento registrado; auditoría de accesos al historial |
| RNF-05 | Compatibilidad | Chrome/Edge/Firefox/Safari actuales; móvil ≤480 px (drawer + bottom-nav); la TV en Chrome kiosco |
| RNF-06 | Accesibilidad | `aria-label` en controles icon-only; contraste AA en paneles y TV |
| RNF-07 | Observabilidad | Cada request con `request-id`; logs estructurados; métricas de errores y webhooks fallidos |
| RNF-08 | Backups | Copia diaria + WAL con retención 30 días; restauración probada mensualmente |
| RNF-09 | Privacidad por defecto | El historial clínico nunca se expone en URLs públicas; descargas con URL firmada (7 días) |
| RNF-10 | Escalabilidad básica | API stateless (tokens Sanctum) → horizontal; WebSockets con Reverb + Redis; colas con Horizon |

---

## 14. Matriz de trazabilidad prototipo → requisito

| Prototipo | Requisitos |
|---|---|
| `Login.jsx` (heurística) | RF-AUTH-01/08/09 |
| `Register.jsx` (Ley 29733) | RF-AUTH-04/05 |
| `BookAppointment.jsx` (wizard + conflicto) | RF-CIT-01/02/03 |
| `MyAppointments.jsx` (cancelación tardía) | RF-CIT-05, R-05 |
| `PaymentGateway.jsx` (simulado) | RF-PAG-01/02/06 |
| `WaitingQueue.jsx` / `TvDisplay.jsx` | RF-COL-01…05 |
| `Waitlist*.jsx` + `useCountdown` | RF-LSE-01…06, R-06 |
| `PatientDetail.jsx` (relación clínica) | RF-HIS-02, R-07 |
| `AppContext.jsx` (acciones) | R-01/02/03/10 |
| `Settings.jsx` | RF-ADM-06, R-05/06/08 |