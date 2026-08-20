# Diagrama de Casos de Uso — SGCM-CMAS

Modelado de los **casos de uso** del prototipo **SGCM-CMAS** (gestión de citas médicas, Ayacucho). Complementa las historias de usuario ([`HISTORIAS_DE_USUARIO.md`](HISTORIAS_DE_USUARIO.md)) y los requisitos ([`REQUISITOS.md`](REQUISITOS.md)) con la vista de actores, funcionalidades y relaciones de inclusión/extensión.

> **Nota de prototipo:** no hay protección de rutas por rol (cualquier usuario puede navegar a cualquier panel). Los casos de uso reflejan el **modelo conceptual** de roles; en una versión con backend, cada caso de uso estaría protegido por autenticación y autorización (ver RNF-12/RNF-14 en `REQUISITOS.md`).

---

## Índice

1. [Diagrama general (Mermaid)](#1-diagrama-general-mermaid)
2. [Actores](#2-actores)
3. [Catálogo de casos de uso](#3-catálogo-de-casos-de-uso)
4. [Relaciones de inclusión y extensión](#4-relaciones-de-inclusión-y-extensión)
5. [Trazabilidad casos de uso → historias](#5-trazabilidad-casos-de-uso--historias)

---

## 1. Diagrama general (Mermaid)

```mermaid
useCaseDiagram
    actor Visitante
    actor Paciente
    actor Medico
    actor Enfermera
    actor Recepcionista
    actor Administrador
    actor Sistema as SYS
    actor PasarelaPago

    Paciente --|> Visitante

    usecase "Iniciar sesión con detección de rol" as UC01
    usecase "Registrarse como paciente" as UC02
    usecase "Recuperar contraseña" as UC03
    usecase "Ver landing del centro" as UC04
    usecase "Buscar disponibilidad sin sesión" as UC05

    Visitante --> UC01
    Visitante --> UC02
    Visitante --> UC03
    Visitante --> UC04
    Visitante --> UC05

    usecase "Reservar cita en 3 pasos" as UC10
    usecase "Pagar cita en línea (50%/100%)" as UC11
    usecase "Ver mis citas" as UC12
    usecase "Reprogramar cita" as UC13
    usecase "Cancelar cita" as UC14
    usecase "Confirmar llegada (check-in móvil)" as UC15
    usecase "Consultar historial clínico" as UC16
    usecase "Descargar historial en PDF" as UC17
    usecase "Inscribirse en lista de espera" as UC18
    usecase "Confirmar o rechazar oferta de cupo" as UC19
    usecase "Declarar pagos" as UC20
    usecase "Editar perfil" as UC21

    Paciente --> UC10
    Paciente --> UC11
    Paciente --> UC12
    Paciente --> UC13
    Paciente --> UC14
    Paciente --> UC15
    Paciente --> UC16
    Paciente --> UC17
    Paciente --> UC18
    Paciente --> UC19
    Paciente --> UC20
    Paciente --> UC21

    usecase "Ver agenda del día" as UC30
    usecase "Gestionar disponibilidad semanal" as UC31
    usecase "Iniciar atención" as UC32
    usecase "Registrar diagnóstico" as UC33
    usecase "Ver ficha del paciente" as UC34
    usecase "Editar perfil profesional" as UC35

    Medico --> UC30
    Medico --> UC31
    Medico --> UC32
    Medico --> UC33
    Medico --> UC34
    Medico --> UC35

    usecase "Ver cola de triaje" as UC40
    usecase "Registrar signos vitales" as UC41
    usecase "Ver historial de triajes" as UC42

    Enfermera --> UC40
    Enfermera --> UC41
    Enfermera --> UC42

    usecase "Ver agenda general del día" as UC50
    usecase "Registrar cita (con alta rápida)" as UC51
    usecase "Check-in presencial y asignar turno" as UC52
    usecase "Cobrar citas y completar abonos" as UC53
    usecase "Verificar pagos declarados" as UC54
    usecase "Cancelar y reprogramar citas" as UC55
    usecase "Gestionar cola del día" as UC56
    usecase "Abrir pantalla de TV" as UC57

    Recepcionista --> UC50
    Recepcionista --> UC51
    Recepcionista --> UC52
    Recepcionista --> UC53
    Recepcionista --> UC54
    Recepcionista --> UC55
    Recepcionista --> UC56
    Recepcionista --> UC57

    usecase "Ver dashboard de indicadores" as UC60
    usecase "Gestionar usuarios y roles" as UC61
    usecase "Gestionar especialidades" as UC62
    usecase "Gestionar consultorios" as UC63
    usecase "Generar reportes" as UC64
    usecase "Configurar reglas de negocio" as UC65
    usecase "Revisar auditoría" as UC66

    Administrador --> UC60
    Administrador --> UC61
    Administrador --> UC62
    Administrador --> UC63
    Administrador --> UC64
    Administrador --> UC65
    Administrador --> UC66

    usecase "Registrar auditoría" as UC70
    usecase "Asignar turno secuencial A-00X" as UC71
    usecase "Actualizar pantalla TV en tiempo real" as UC72
    usecase "Procesar pago (pasarela simulada)" as UC73
    usecase "Verificar pago declarado" as UC74

    UC10 ..> UC70 : <<include>>
    UC13 ..> UC70 : <<include>>
    UC14 ..> UC70 : <<include>>
    UC15 ..> UC70 : <<include>>
    UC33 ..> UC70 : <<include>>
    UC52 ..> UC71 : <<include>>
    UC52 ..> UC70 : <<include>>
    UC56 ..> UC72 : <<include>>
    UC57 ..> UC72 : <<include>>
    UC41 ..> UC72 : <<include>>
    UC11 ..> UC73 : <<include>>
    UC53 ..> UC74 : <<include>>

    UC19 ..> UC10 : <<include>>
    UC14 ..> UC11 : <<extend>>
    UC15 ..> UC10 : <<extend>>
    UC34 ..> UC32 : <<extend>>
```

---

## 2. Actores

| Actor | Descripción |
|---|---|
| **Visitante** | Persona sin sesión: puede ver la landing, buscar disponibilidad, registrarse, iniciar sesión o recuperar contraseña. |
| **Paciente** | Usuario autenticado como paciente (`julia.mamani@gmail.com`). **Generaliza a Visitante** (hereda sus casos de uso) y añade reserva, pagos, historial, lista de espera y perfil. |
| **Médico** | Profesional de la salud (`rosa.quispe@cmas.com`): agenda, disponibilidad, atención y diagnóstico. |
| **Enfermera** | Personal de enfermería (`diana.prado@cmas.com`): triaje y gestión de la cola. |
| **Recepcionista** | Personal de recepción (`sofia.mendoza@cmas.com`): agenda, registro, check-in, cobros, cancelaciones y cola/TV. |
| **Administrador** | Usuario con acceso a la gestión (`miguel.huaraca@cmas.com`): indicadores, catálogos, reportes, configuración y auditoría. |
| **Sistema** | Actor interno: ejecuta acciones transversales (auditoría, turnos, TV, verificación). |
| **PasarelaPago** | Actor externo **simulado**: procesa pagos con tarjeta en línea (sin integración real con Culqi). |

---

## 3. Catálogo de casos de uso

### Módulo 1 · Autenticación y acceso

| ID | Caso de uso | Actor | Precondición | Postcondición |
|---|---|---|---|---|
| UC-01 | Iniciar sesión con detección de rol | Visitante | Correo válido + clave ≥ 6 | `auth` seteado, redirige al panel del rol, auditoría |
| UC-02 | Registrarse como paciente | Visitante | Campos válidos, correo único | Cuenta creada, sesión como paciente |
| UC-03 | Recuperar contraseña | Visitante | Correo válido | Enlace enviado (30 min) y nueva clave definida |

### Módulo 2 · Público

| ID | Caso de uso | Actor | Precondición | Postcondición |
|---|---|---|---|---|
| UC-04 | Ver landing del centro | Visitante | — | Página renderizada con especialidades y CTAs |
| UC-05 | Buscar disponibilidad sin sesión | Visitante | Especialidad + fechas | Slots libres mostrados; "Reservar" → login |

### Módulo 3-8 · Paciente

| ID | Caso de uso | Actor | Precondición | Postcondición |
|---|---|---|---|---|
| UC-10 | Reservar cita en 3 pasos | Paciente | Sesión iniciada | Cita creada (`agendada`/`pagada`), horario bloqueado |
| UC-11 | Pagar cita en línea (50%/100%) | Paciente | Cita en reserva | Pago `pagado` + operación `OP-2026-XXXX`, cita lista para check-in |
| UC-12 | Ver mis citas | Paciente | Sesión iniciada | Citas en pestañas Próximas/Pasadas/Canceladas |
| UC-13 | Reprogramar cita | Paciente | Cita próxima | Cita `reprogramada` |
| UC-14 | Cancelar cita | Paciente | Cita próxima | Cita `cancelada`, horario liberado, auditoría |
| UC-15 | Confirmar llegada (check-in móvil) | Paciente | Cita `agendada`/`pagada` | Cita `check_in`, auditoría |
| UC-16 | Consultar historial clínico | Paciente | Sesión iniciada | Línea de tiempo con atenciones documentadas |
| UC-17 | Descargar historial en PDF | Paciente | Historial disponible | Descarga simulada (toast) |
| UC-18 | Inscribirse en lista de espera | Paciente | Sesión iniciada | Inscripción `en_espera` con posición |
| UC-19 | Confirmar o rechazar oferta de cupo | Paciente | Oferta activa (15 min) | Confirmar → cita + pago; Rechazar → `en_espera`; Expirar → `expirada` |
| UC-20 | Declarar pagos | Paciente | Cita sin pagar | Pago `pendiente_verificacion` |
| UC-21 | Editar perfil | Paciente | Sesión iniciada | Datos guardados (local) |

### Módulo 9-11 · Médico

| ID | Caso de uso | Actor | Precondición | Postcondición |
|---|---|---|---|---|
| UC-30 | Ver agenda del día | Médico | Sesión iniciada | Timeline con filtros por estado |
| UC-31 | Gestionar disponibilidad semanal | Médico | Sesión iniciada | Grilla actualizada, sin perder citas confirmadas |
| UC-32 | Iniciar atención | Médico | Cita `triaje_completado` | Cita `en_atencion` |
| UC-33 | Registrar diagnóstico | Médico | Cita `en_atencion` | Cita `documentada`, auditoría |
| UC-34 | Ver ficha del paciente | Médico | Relación clínica vigente | Historial visible; si no, "Acceso denegado" + auditoría |
| UC-35 | Editar perfil profesional | Médico | Sesión iniciada | Perfil actualizado (local) |

### Módulo 12 · Enfermera

| ID | Caso de uso | Actor | Precondición | Postcondición |
|---|---|---|---|---|
| UC-40 | Ver cola de triaje | Enfermera | Sesión iniciada | Pacientes en espera/en progreso ordenados por tiempo |
| UC-41 | Registrar signos vitales | Enfermera | Cita `en_espera_triaje`/`en_triaje` | Cita `triaje_completado` |
| UC-42 | Ver historial de triajes | Enfermera | Triajes del turno | Listado con signos vitales y responsable |

### Módulo 13-14 · Recepcionista

| ID | Caso de uso | Actor | Precondición | Postcondición |
|---|---|---|---|---|
| UC-50 | Ver agenda general del día | Recepcionista | Sesión iniciada | Citas de todos los médicos filtrables |
| UC-51 | Registrar cita (con alta rápida) | Recepcionista | Paciente (existente o nuevo) | Cita `confirmada`, pago opcional |
| UC-52 | Check-in presencial y asignar turno | Recepcionista | Cita `pagada` o `check_in` | Cita `en_espera_triaje` con turno `A-00X` |
| UC-53 | Cobrar citas y completar abonos | Recepcionista | Cita `agendada` / abono 50% | Cita `pagada` (100%), comprobante `R-2026-XXXX` |
| UC-54 | Verificar pagos declarados | Recepcionista | Pago `pendiente_verificacion` | Pago `pagado`, comprobante habilitado |
| UC-55 | Cancelar y reprogramar citas | Recepcionista | Cita activa | `cancelada`/`reprogramada` + auditoría |
| UC-56 | Gestionar cola del día | Recepcionista/Enfermera | Sesión iniciada | Cola avanzada por acciones de estado |
| UC-57 | Abrir pantalla de TV | Recepcionista/Enfermera | Cola con pacientes | `/tv` abierta en pestaña nueva |

### Módulo 15 · Administrador

| ID | Caso de uso | Actor | Precondición | Postcondición |
|---|---|---|---|---|
| UC-60 | Ver dashboard de indicadores | Administrador | Sesión iniciada | KPIs y gráficos mostrados |
| UC-61 | Gestionar usuarios y roles | Administrador | Sesión iniciada | Cuenta creada/actualizada, invitación simulada |
| UC-62 | Gestionar especialidades | Administrador | Sesión iniciada | Catálogo actualizado (con advertencias) |
| UC-63 | Gestionar consultorios | Administrador | Sesión iniciada | Consultorios actualizados (con advertencias) |
| UC-64 | Generar reportes | Administrador | Sesión iniciada | Reportes filtrados y exportación demo |
| UC-65 | Configurar reglas de negocio | Administrador | Sesión iniciada | `settings` actualizados |
| UC-66 | Revisar auditoría | Administrador | Sesión iniciada | Eventos filtrables por veredicto/búsqueda |

### Transversales (Sistema)

| ID | Caso de uso | Actor | Precondición | Postcondición |
|---|---|---|---|---|
| UC-70 | Registrar auditoría | Sistema | Operación sensible | Evento en `AUDIT_LOG` (`pushAudit`) |
| UC-71 | Asignar turno secuencial A-00X | Sistema | Check-in presencial | `turno` asignado a la cita |
| UC-72 | Actualizar pantalla TV en tiempo real | Sistema | Cambio de estado en la cola | TV re-renderizada (misma/otras pestañas) |
| UC-73 | Procesar pago (pasarela simulada) | PasarelaPago | Datos de tarjeta válidos | Operación `OP-2026-XXXX`, pago `pagado` |
| UC-74 | Verificar pago declarado | Sistema/Recepción | Pago `pendiente_verificacion` | Pago `pagado`, comprobante habilitado |

---

## 4. Relaciones de inclusión y extensión

| Origen | Tipo | Destino | Justificación |
|---|---|---|---|
| UC-10 Reservar cita | `<<include>>` | UC-70 Registrar auditoría | Toda reserva registra auditoría. |
| UC-13/UC-14/UC-15/UC-33 | `<<include>>` | UC-70 Registrar auditoría | Reprogramar, cancelar, check-in y diagnóstico son operaciones auditadas. |
| UC-52 Check-in presencial | `<<include>>` | UC-71 Asignar turno | El check-in siempre asigna el turno `A-00X`. |
| UC-56/UC-57 | `<<include>>` | UC-72 Actualizar TV | Las acciones de la cola y la apertura del TV disparan la actualización. |
| UC-41 Registrar signos vitales | `<<include>>` | UC-72 Actualizar TV | Completar triaje mueve la cola y actualiza el TV. |
| UC-11 Pagar en línea | `<<include>>` | UC-73 Procesar pago | El pago en línea invoca la pasarela simulada. |
| UC-53 Cobrar | `<<include>>` | UC-74 Verificar pago | El cobro/flujo de abono implica la verificación de pagos. |
| UC-19 Confirmar cupo | `<<include>>` | UC-10 Reservar cita | Aceptar la oferta crea la cita automáticamente. |
| UC-14 Cancelar cita | `<<extend>>` | UC-11 Pagar en línea | La cancelación puede aplicarse a citas pagadas (regla de cancelación tardía). |
| UC-15 Check-in móvil | `<<extend>>` | UC-10 Reservar cita | Solo aplica a citas reservadas (agendada/pagada). |
| UC-34 Ver ficha del paciente | `<<extend>>` | UC-32 Iniciar atención | El médico puede consultar la ficha desde la atención. |

---

## 5. Trazabilidad casos de uso → historias

| Casos de uso | Historias | Requisitos |
|---|---|---|
| UC-01, UC-02, UC-03 | US-01, US-02, US-03 | RF-01 a RF-05 |
| UC-04, UC-05 | US-05, US-06 | RF-06 a RF-08 |
| UC-10, UC-11 | US-07, US-08, US-09 | RF-09 a RF-12 |
| UC-12, UC-13, UC-14, UC-15, UC-21 | US-10 a US-13, US-20 | RF-13 a RF-15, RF-18 |
| UC-16, UC-17 | US-14, US-15 | RF-16, RF-17 |
| UC-18, UC-19 | US-16, US-17, US-18 | RF-19 a RF-22 |
| UC-20 | US-19 | RF-23 |
| UC-30, UC-31, UC-32, UC-33, UC-34, UC-35 | US-21 a US-26 | RF-24 a RF-29 |
| UC-40, UC-41, UC-42 | US-27, US-28, US-29 | RF-30 a RF-32 |
| UC-56, UC-57 | US-30, US-31, US-32 | RF-33 a RF-35 |
| UC-50 a UC-55 | US-33 a US-37 | RF-36 a RF-40 |
| UC-60 a UC-66 | US-38 a US-44 | RF-41 a RF-47 |
| UC-70 a UC-74 | Transversal | RF-48 a RF-50 |
