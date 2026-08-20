# Módulos del sistema — Documentación detallada

Documento técnico-funcional del prototipo **SGCM-CMAS** (gestión de citas médicas, Ayacucho). Complementa el `README.md` con el detalle de **cada módulo**: propósito, rutas, flujo paso a paso, reglas de negocio, datos/estados involucrados, integraciones y archivos de implementación.

> **Convenciones usadas en este documento**
>
> - **Estado de cita**: cadena como `agendada`, `pagada`, `en_espera_triaje`, etc. (ver sección 9 del README).
> - **Contexto**: todas las páginas consumen `useApp()` de `src/context/AppContext.jsx`.
> - **Auditoría**: las acciones sensibles se registran con `pushAudit(...)`.
> - **Responsive**: todas las páginas son adaptables a móvil (breakpoints ≤768 px y ≤480 px); ver "Sistema de diseño responsive" en el Módulo 2.
> - Los archivos citados están bajo `src/` salvo que se indique lo contrario.

---

## Índice de módulos

| # | Módulo | Audiencia | Rutas principales |
|---|---|---|---|
| 1 | Autenticación y acceso | Todos | `/login`, `/registro`, `/recuperar*` |
| 2 | Público y búsqueda de disponibilidad | Público | `/`, `/disponibilidad`, `/componentes` |
| 3 | Reserva de citas | Paciente | `/paciente/reservar` |
| 4 | Mis citas y check-in móvil | Paciente | `/paciente/citas`, `/paciente/checkin` |
| 5 | Historial clínico | Paciente | `/paciente/historial` |
| 6 | Lista de espera (cupos) | Paciente | `/paciente/lista-espera*` |
| 7 | Pagos del paciente | Paciente | `/paciente/pagos` |
| 8 | Perfil del paciente | Paciente | `/paciente/perfil` |
| 9 | Agenda del médico | Médico | `/medico` |
| 10 | Disponibilidad del médico | Médico | `/medico/disponibilidad` |
| 11 | Atención, diagnóstico y ficha del paciente | Médico | `/medico/diagnostico/:cid`, `/medico/paciente/:pid`, `/medico/perfil` |
| 12 | Triaje de enfermería | Enfermería | `/enfermeria`, `/enfermeria/triaje/:cid`, `/enfermeria/historial` |
| 13 | Cola del día + pantalla de TV | Recepción y Enfermería | `/recepcion/lista-espera`, `/enfermeria/lista-espera`, `/tv` |
| 14 | Operación de recepción | Recepción | `/recepcion*` |
| 15 | Administración | Administración | `/admin*` |

---

## Módulo 1 · Autenticación y acceso

**Propósito.** Ingreso, registro y recuperación de contraseña. Un único formulario de login sirve a los 5 roles; el rol se detecta por heurística del correo.

**Rutas.** `/login`, `/registro`, `/recuperar`, `/recuperar/confirmacion`, `/recuperar/nueva-password`.

**Flujo — inicio de sesión** (`src/pages/public/Login.jsx`):
1. El usuario ingresa correo y contraseña.
2. Validación: correo con regex, contraseña ≥ 6 caracteres.
3. Tras 700 ms se determina el rol por heurística y se llama `login(role)` del contexto (guarda `auth` y registra auditoría "Inicio de sesión").
4. Redirección a `PANEL_HOME[role]`: paciente → `/paciente`, médico → `/medico`, enfermera → `/enfermeria`, recepcionista → `/recepcion`, administrador → `/admin`.

**Heurística de rol** (orden de evaluación):
1. correo contiene `medico`, o (`cmas.com` y `rosa`) → médico
2. contiene `diana` o `enfermera` → enfermera
3. contiene `sofia` → recepcionista
4. contiene `huaraca` o `admin` → administrador
5. cualquier otro → paciente

**Acceso rápido demo.** Botones que ejecutan `quickLogin(role)` (500 ms) sin credenciales.

**Flujo — registro de paciente** (`src/pages/public/Register.jsx`):
1. Formulario: nombre, correo, DNI (8 dígitos), celular (9), fecha de nacimiento, contraseña + confirmación, "cómo te enteraste", términos.
2. Validaciones: nombre ≥ 5 caracteres; correo válido y **no duplicado** (verifica disponibilidad al salir del campo contra `patients` + `demo@cmas.com`); clave con mayúscula, número y ≥ 6 caracteres; confirmación idéntica; términos aceptados.
3. Al enviar: `login('paciente')` + `navigate('/paciente')` tras 700 ms. Menciona la Ley N.º 29733.

**Flujo — recuperación de contraseña** (2 pasos):
- `RecoverPassword.jsx`: solicita correo → vista "Revisa tu correo" (enlace válido 30 min, un solo uso). El botón "Continuar con enlace de ejemplo" lleva a `/recuperar/nueva-password`.
- `NewPassword.jsx`: "Paso 2 de 2", valida nueva clave (misma política) y confirmación; al guardar muestra toast y redirige a `/login` en 1.4 s.

**Integraciones.** `login`/`logout` y `auth` del contexto; auditoría en cada acceso. `AuthLayout.jsx` provee el marco visual.

**Archivos.** `pages/public/Login.jsx`, `Register.jsx`, `RecoverPassword.jsx`, `NewPassword.jsx`, `components/layout/AuthLayout.jsx`.

---

## Módulo 2 · Público y búsqueda de disponibilidad

**Propósito.** Landing de marketing, buscador público de horarios (sin sesión) y galería del sistema de diseño.

**Rutas.** `/`, `/disponibilidad`, `/componentes`.

### Landing (`/`)
- Header con CTAs → `/login` y `/disponibilidad`.
- Hero con estadísticas ficticias y una tarjeta de cita simulada ("Pago verificado", "Recordatorio").
- Grilla de especialidades desde el contexto (cada una enlaza a `/disponibilidad`), sección "Cómo funciona" (3 pasos) y CTA de **Lista de Espera Inteligente**.
- Footer con datos de contacto y enlace a `/componentes`.

### Búsqueda de disponibilidad (`/disponibilidad`, `SearchAvailability.jsx`)
1. El usuario elige especialidad (todas o una) y rango de fechas (por defecto `2026-08-05` → `2026-08-09`).
2. `useMemo` calcula resultados: filtra doctores por especialidad, cruza `appointments` no canceladas para marcar ocupados (`doctorId|date|time`) y conserva solo slots libres dentro del rango.
3. Cada médico se muestra con `DoctorSearchCard` (rating, bio, consultorio, chips de horarios). Al elegir un slot, "Reservar" redirige a `/login` con toast "Casi listo".
4. **Estado demo**: la especialidad `cardiologia` hasta el `2026-08-09` fuerza el `EmptyState` "No hay horarios disponibles" con sugerencia de inscripción en la lista de espera (`/paciente/lista-espera/inscripcion`). Botón "Ver ejemplo sin disponibilidad" lo replica.

### Galería de componentes (`/componentes`)
Catálogo vivo de todos los componentes UI (badges del ciclo de vida, botones, formularios, tabs, step indicators, avatares, modales, toasts). Sirve como referencia del sistema de diseño.

### Sistema de diseño responsive (base transversal)
Todas las páginas son **responsive móvil** con dos breakpoints principales:
- **≤ 768 px**: el sidebar escritorio pasa a **drawer lateral** (`PanelLayout.jsx`) + **navegación inferior** con los primeros 5 ítems del menú; cualquier `.grid` de 2+ columnas (incluidas las definidas con `style={{ gridTemplateColumns }}`) apila a 1 columna vía `global.css` (`grid-template-columns: 1fr !important`); las filas `.row`/`.row-between` agregan `flex-wrap`; `PageHeader` apila título/acciones y expande los botones; `.segmented` ocupa todo el ancho.
- **≤ 480 px**: los **modales** se convierten en *bottom-sheet* (`Modal.css`), se reducen paddings de tarjetas y formularios, y las filas tipo listado (agenda, cancelaciones, pagos, historial) envuelven contenido con `flex-wrap`.
- Las **tablas densas** de administración (`Users`, `AuditLog`, `Reports`) y la **grilla de disponibilidad** (`avail-grid`) conservan `min-width` y hacen **scroll horizontal** dentro de su tarjeta (`overflow-x: auto`).
- Utilidades `hidden-mobile` / `hidden-desktop` controlan qué elementos se muestran en cada tamaño; el `Toast` móvil se ancla arriba del bottom-nav.

**Archivos.** `pages/public/Landing.jsx`, `SearchAvailability.jsx`, `Components.jsx`.

---

## Módulo 3 · Reserva de citas (paciente)

**Propósito.** Wizard de 3 pasos para que el paciente reserve una cita por especialidad → médico/horario → confirmación.

**Ruta.** `/paciente/reservar` (`BookAppointment.jsx`).

**Flujo paso a paso:**
1. **Especialidad**: grilla con las 7 especialidades (ícono, descripción, precio). Sin selección no se continúa. Al continuar se preselecciona el primer médico de la especialidad.
2. **Médico y calendario semanal**: lista de médicos de la especialidad (rating, años de experiencia, universidad y contador de horarios libres de la semana). Al elegir uno se muestra el **calendario semanal** (WEEK: 05–11 de agosto con etiquetas "Hoy", "Mañana", "Vie"…). Los slots del médico se calculan restando las citas ya tomadas (no canceladas) de `doctor.slots`. El paciente toca un slot libre (marcador Libre/Ocupado/Elegido).
3. **Confirmación**: resumen (especialidad, médico, fecha, hora, costo, ubicación), **selector de pago anticipado**, motivo opcional (`Textarea`) y toggle "Simular conflicto de concurrencia". Botón "Confirmar reserva" (o "Confirmar y pagar S/ XX") → `ConfirmDialog`.

**Pago anticipado (pasarela 50% / 100%):**
- El paciente elige cuánto paga ahora para **asegurar su asistencia**:
  - **Abono 50%** (recomendado): paga la mitad (`Math.round(precio/2)`) en línea y el resto en caja.
  - **Pago total 100%**: paga todo en línea.
  - **Pagar en caja**: sin pago en línea (la cita queda `agendada` y espera pago presencial).
- Si elige 50% o 100%, al confirmar se abre **`PaymentGateway`** (`components/PaymentGateway.jsx`): formulario simulado de tarjeta (titular, número con auto-formato 0000 0000 0000 0000, detección de marca Visa/Mastercard/Amex, vencimiento MM/AA, CVV) → procesamiento con spinner "Comunicando con tu banco…" (~1.6 s) → pantalla de éxito con monto, operación `OP-2026-XXXX` y resumen.
- Al aprobar: `bookAppointment` crea la cita con `status: 'pagada'` y `paidType: 'adelanto' | 'total'`, y `addPayment` registra un pago `pagado` (método "Tarjeta (pasarela)", `verifiedBy: 'Sistema'`, `gateway: true`, `opRef`). Con el abono 50% la cita ya queda **lista para check-in** (no pasa por caja); el saldo se cobra en recepción.

**Reglas de negocio:**
- Un horario es "libre" solo si no existe una cita no cancelada con `doctorId|date|time`.
- **Conflicto de concurrencia (demo)**: si el toggle está activo, al confirmar se abre un `Modal` "Este horario ya no está disponible" con **3 alternativas sugeridas**; elegir una reemplaza el slot y continúa.
- `bookAppointment` crea la cita con `status: 'pagada'` (si hubo pago en línea, con `paidType`) o `'agendada'` (espera pago en caja), y registra auditoría. El ID se genera como `C-<aleatorio>`.
- La cita creada bloquea el horario en todo el sistema (lista de ocupados).

**Pantalla de éxito**: resumen completo (médico, especialidad, consultorio, fecha, hora, costo, N.º de cita) y, si pagó en línea, fila "Pago en línea" con `Abono 50%`/`Pago total` + monto, "Saldo en caja" (solo abono 50%) y operación. Accesos a "Ver mis citas" / "Ir al inicio". Tips: llegar 10 min antes con DNI, reprogramar hasta 12 h antes, pago en línea habilita el check-in automáticamente.

**Archivos.** `pages/patient/BookAppointment.jsx` + `BookAppointment.css`. Usa `StepIndicator`, `ConfirmDialog`, `Modal`, `PaymentGateway` (`components/PaymentGateway.jsx` + `.css`) y helpers `consultorioOf`, `fmtDate`, `fmtPrice`, `fmtPayType`.

---

## Módulo 4 · Mis citas y check-in móvil

**Propósito.** El paciente administra sus citas (ver, reprogramar, cancelar, confirmar llegada).

**Rutas.** `/paciente/citas` (`MyAppointments.jsx`), `/paciente/checkin` (`PatientCheckin.jsx`).

### Mis citas
- 3 pestañas (`Tabs`): **Próximas**, **Pasadas**, **Canceladas**. Cada cita usa `AppointmentCard` con acciones según estado.
- Estados de flujo clínico con mensajes vivos: "En la cola de triaje", "Te está evaluando la enfermera", "Tu médico ya te espera", "Estás en consulta".
- **Reprogramar**: mueve la cita a una fecha fija (`11/08 · 11:00`) con estado `reprogramada`.
- **Cancelar**: si la cita es del `04`/`05` de agosto y después de las 12:00, el modal advierte **cancelación tardía** (regla de mínimo 12 h). Al cancelar se libera el horario y se registra auditoría con el email del usuario.
- **Check-in**: disponible solo en `agendada`/`pagada`.

### Check-in móvil
- Solo accesible con cita `agendada` o `pagada`; si no, muestra estado vacío.
- Tarjeta con médico, especialidad, fecha/hora, consultorio, precio y recordatorio de DNI.
- Botón "Confirmar mi llegada" → la cita pasa a `check_in` y se registra auditoría. Pantalla de éxito explica que recepción enviará al paciente a la cola de triaje.
- **Regla**: sin pago no hay check-in.

**Archivos.** `pages/patient/MyAppointments.jsx`, `PatientCheckin.jsx`, `components/AppointmentCard.jsx`.

---

## Módulo 5 · Historial clínico del paciente

**Propósito.** Línea de tiempo con las atenciones documentadas del paciente (diagnóstico, notas, triaje) y **descarga en PDF real** con membrete oficial de la clínica.

**Ruta.** `/paciente/historial` (`PatientHistory.jsx`).

**Flujo:**
- Filtra citas `atendida`/`documentada` del paciente, ordenadas de más reciente a más antigua.
- Cada entrada es expandible y muestra el historial **completo**:
  - Metadatos: fecha, hora, duración, consultorio, turno `A-00X` y N.º de cita.
  - Motivo de consulta (`a.reason`).
  - Diagnóstico (`diag.dx`) con badge de severidad (`diag.severity`: Leve/Moderada/Severa) y notas/indicaciones del médico.
  - Triaje de enfermería cuando existe: signos vitales (PA, temperatura, FC, peso, talla) en grilla, alergias, motivo, observaciones y quién lo registró.
  - Costo de la especialidad y estado de la cita.
- **Descarga PDF real** (jsPDF + jspdf-autotable, cargado con `import()` diferido):
  - Por cita → `generateAppointmentPdf` (`utils/clinicPdf.js`): "Resumen de atención clínica" con membrete, datos del paciente, datos de la atención, diagnóstico, triaje y firma del médico. Modal de confirmación previo.
  - Completo → `generateClinicalRecordPdf`: "Historia clínica" con tabla resumen de atenciones, detalle por atención y bloque de validación al final.
  - Ambos incluyen **membrete de la clínica** (escudo + nombre + RUC/dirección/teléfono, pie con paginación y documento N.° `RA-…`/`HC-…`) definido en `data/clinic.js` (`CLINIC`).
- Encabezado: conteo de atenciones documentadas y nota de protección de datos.

**Archivos.** `pages/patient/PatientHistory.jsx` + `History.css`, `utils/clinicPdf.js`, `data/clinic.js`.

---

## Módulo 6 · Lista de espera inteligente del paciente (cupos)

**Propósito.** El paciente se inscribe en la lista de espera de **citas liberadas** y recibe ofertas de cupo con ventana de decisión.

**Rutas.** `/paciente/lista-espera`, `/paciente/lista-espera/inscripcion`, `/paciente/lista-espera/oferta`, `/paciente/lista-espera/expirada`.

### Mis inscripciones (`Waitlist.jsx`)
- Si hay una oferta activa muestra un **banner urgente animado** hacia la pantalla de oferta.
- Cada inscripción: especialidad, médico, preferencia horaria, fecha de inscripción y badge de estado.
  - `en_espera` → posición estimada `~N°` con barra de progreso y nota de ascenso automático si el paciente anterior rechaza/expira.
  - `oferta` → botón "Confirmar cupo ofrecido".
  - `confirmada` → el cupo ya es una cita; enlaza a "Mis citas".
  - `expirada` → mensaje y opción de reinscripción.

### Inscripción (`WaitlistEnroll.jsx`) — wizard 3 pasos
1. Elegir especialidad.
2. Elegir médico preferido y rango horario (Mañana / Mediodía / Tarde / Cualquier horario).
3. Resumen con costo estimado y aviso de la ventana de **15 minutos**.

Al confirmar: `enrollWaitlist` crea la entrada `en_espera` con posición aproximada `~N°3`, pantalla de éxito (notificación por banner/correo/SMS simulados) y tras 2.2 s redirige a la oferta (flujo de demo).

### Oferta de cupo (`WaitlistOffer.jsx`)
- Banner urgente + **cuenta regresiva** (`useCountdown`, 900 s o `offer.confirmWindowMin`, 15 min). Cambia a estado de advertencia a ≤ 180 s.
- Detalle del cupo: médico, fecha/hora/costo y motivo "cupo por cancelación".
- **Confirmar** → `confirmOffer` crea la cita automáticamente (estado `agendada`) + pago `pendiente_verificacion`, y muestra resumen con N.º de cita.
- **Rechazar** → `rejectOffer` devuelve la entrada a `en_espera` (conserva la posición para futuras ofertas).
- Al llegar a 0 → `expireOffer` (entrada a `expirada`).
- Herramientas demo: "Simular oferta" (cupo `2026-08-07 09:00`) y "Simular expiración".

### Cupo expirado (`WaitlistExpired.jsx`)
- Informa que el cupo pasó al siguiente por no responder en los 15 minutos.
- Acciones: "Mis inscripciones" o "Inscribirme de nuevo".

**Integraciones.** `enrollWaitlist`, `offerWaitlist`, `confirmOffer`, `rejectOffer`, `expireOffer` (contexto). `confirmOffer` encadena `bookAppointment` + `addPayment`.

**Archivos.** `pages/patient/Waitlist*.jsx` (+ `Waitlist.css`, `WaitlistOffer.css`), `hooks/useCountdown.js`.

---

## Módulo 7 · Pagos del paciente

**Propósito.** El paciente declara pagos de sus citas, consulta comprobantes y ve el tipo de pago realizado (abono 50% o pago total).

**Ruta.** `/paciente/pagos` (`PatientPayments.jsx`).

**Flujo:**
- Lista de pagos con badge de estado (`pagado` / `pendiente_verificacion`), método, referencia, especialidad, médico, fecha y monto. Si el pago tiene `paidType`, se muestra un chip `Abono 50%` o `Pago total` (`fmtPayType`).
- **Declarar pago**: modal que selecciona la cita sin pagar (`confirmada`/`reprogramada`/`agendada`) y permite elegir **¿Cuánto pagas?**: **Abono 50%** (prellena la mitad del precio) o **Pago total 100%** (prellena el total; editable). Método (Yape/Plin/Transferencia/Efectivo) y código de operación.
- Al declarar se crea un pago `pendiente_verificacion` con `paidType`; **recepción lo verifica en <15 min** y recién entonces se habilita el comprobante (PDF simulado).

**Archivos.** `pages/patient/PatientPayments.jsx` + `Payments.css`.

---

## Módulo 8 · Perfil del paciente

**Propósito.** Datos personales y configuración de cuenta del paciente.

**Ruta.** `/paciente/perfil` (`PatientProfile.jsx`).

**Detalle:**
- Tarjeta lateral: avatar, "Paciente desde enero 2026", badges (historia clínica digital, correo verificado, datos protegidos).
- Formulario: nombre, correo, celular (9 dígitos), DNI (8), fecha de nacimiento, dirección — con validación por campo y toast de error; al guardar "Guardado ✓" (no persiste en contexto; estado local).
- Cambio de contraseña: simulado (envía enlace al correo).

**Archivos.** `pages/patient/PatientProfile.jsx` + `Profile.css`.

---

## Módulo 9 · Agenda del día del médico

**Propósito.** Vista principal del médico: timeline de las citas del día en su consultorio con filtros y actualización "en vivo".

**Ruta.** `/medico` (`src/pages/doctor/Agenda.jsx`).

**Detalle:**
- Médico autenticado: **Dra. Rosa Quispe (d1)**, Consultorio 2.
- Timeline por horas (08:00–17:00) con bloques por cita: hora, duración, consultorio, paciente, motivo y `Badge` de estado.
- Filtros (`Segmented`): Todos / En camino (`check_in`, `en_espera_triaje`, `en_triaje`) / Por atender (`triaje_completado`) / En atención / Documentadas.
- Banner de cola: "X en triaje/en camino · Y listos para atender".
- **Acciones por estado:**
  - `triaje_completado` → botón **"Iniciar atención"** (modal de confirmación → `startAttention`, pasa a `en_atencion`).
  - `en_atencion` → botón **"Registrar diagnóstico"** (enlace a `/medico/diagnostico/:cid`).
  - Otras → "Ver historial" y simulador "Simular cancelación en vivo" (libera el horario con flash y toast, registra auditoría).
- Barra "Sincronizar" (toast) + chip "En vivo".
- Estadísticas al pie: en triaje/camino, por atender, en atención, documentadas.

**Archivos.** `pages/doctor/Agenda.jsx` + `Agenda.css`.

---

## Módulo 10 · Disponibilidad del médico

**Propósito.** El médico administra su grilla semanal de bloques de atención.

**Ruta.** `/medico/disponibilidad` (`Availability.jsx`).

**Detalle:**
- Grilla 7 días × franjas de 30 min (08:00–17:00). Clic para activar/desactivar un bloque.
- Modal para añadir rangos (día, inicio, fin) y diálogo para eliminar bloques ocupados.
- Detecta y marca en rojo **solapamientos** de bloques consecutivos.
- Las **citas confirmadas se conservan** aunque se elimine el bloque (se excluyen canceladas/reprogramadas).
- Se apoya en `doctors` y `consultorios`; el estado de disponibilidad deriva de `doctor.slots`.

**Archivos.** `pages/doctor/Availability.jsx` + `Availability.css`.

---

## Módulo 11 · Atención, diagnóstico y ficha del paciente (médico)

**Rutas.** `/medico/diagnostico/:cid`, `/medico/paciente/:pid`, `/medico/perfil`.

### Registro de diagnóstico (`Diagnosis.jsx`)
- Solo editable si la cita está `en_atencion` (si no, formulario bloqueado con candado).
- Campos: diagnóstico (mín. 5 caracteres), severidad (Leve/Moderada/Severa) y observaciones.
- Al guardar: `updateAppointment` → la cita pasa a `documentada` (con auditoría) y redirige a la agenda.
- Panel lateral: datos de la consulta, triaje de enfermería (si existe) y flujo "En atención → Documentada".

### Ficha/historial del paciente (`PatientDetail.jsx`)
- Historial de atenciones del paciente con el médico autenticado, con **regla de acceso**: si no hay citas previas no canceladas del médico, muestra "Acceso denegado" (y registra auditoría del intento).
- Muestra datos del paciente (DNI, edad, dirección), lista de citas descendentemente y triaje expandible de cada atención (PA, temp, FC, peso, talla, alergias, motivo, observaciones, enfermera).
- **"Ficha (PDF)"**: descarga real de la ficha clínica (`generateClinicalRecordPdf` con título "FICHA CLÍNICA") con membrete oficial, historial completo con el médico y bloque de validación.

### Perfil profesional (`Profile.jsx`)
- Tarjeta de identidad (avatar, rating ★4.8, experiencia, universidad, CMP) + formulario editable (especialidad, consultorio, teléfono, correo, bio con contador de 280 caracteres).
- Guardado local con toast; "Ver como paciente" simula la vista previa pública.

**Archivos.** `pages/doctor/Diagnosis.jsx` + `Diagnosis.css`, `PatientDetail.jsx` + `PatientDetail.css`, `Profile.jsx`.

---

## Módulo 12 · Triaje de enfermería

**Propósito.** La enfermera gestiona la cola de triaje, toma signos vitales y consulta el historial del turno.

**Rutas.** `/enfermeria`, `/enfermeria/triaje/:cid`, `/enfermeria/historial`.

### Cola de triaje (`TriageQueue.jsx`)
- Dos columnas: **"Esperando triaje"** (citas `en_espera_triaje` ordenadas por tiempo de espera desde el check-in) y **"En progreso"** (`en_triaje`).
- Cada tarjeta: paciente, especialidad · médico, consultorio, tiempo de espera (advertencia >10 min), hora de cita.
- "Iniciar triaje" → `startTriage` (pasa a `en_triaje`) y navega al formulario. "Continuar triaje" reingresa a un triaje en curso.

### Formulario de triaje (`TriageForm.jsx`)
- Campos obligatorios: **presión arterial, temperatura, frecuencia cardíaca, peso, talla y motivo** de consulta; opcionales: alergias y observaciones.
- Solo editable si la cita está `en_espera_triaje`/`en_triaje`; si el triaje ya existe muestra vista de solo lectura.
- "Completar triaje y enviar al médico" → `completeTriage` guarda `triage` (con `nurseName` y hora `at`) y la cita pasa a `triaje_completado` (el médico la ve como "Por atender").

### Historial de triajes (`TriageHistory.jsx`)
- Lista los triajes del turno (día 05/08) con estados `triaje_completado`, `en_atencion`, `atendida`, `documentada` y objeto `triage` presente. Muestra signos vitales, motivo, alergias, observaciones, médico/especialidad y responsable.

**Archivos.** `pages/nurse/TriageQueue.jsx`, `TriageForm.jsx`, `TriageHistory.jsx` + `Triage.css`.

---

## Módulo 13 · Cola del día + pantalla de TV

**Propósito.** Lista de espera inteligente del día gestionada por **recepción y enfermería**, con pantalla de TV que se actualiza automáticamente. (Implementado como funcionalidad nueva sobre el flujo de triaje/consulta.)

**Rutas.** `/recepcion/lista-espera` y `/enfermeria/lista-espera` (tablero, `pages/queue/WaitingQueue.jsx`) · `/tv` (pantalla, `pages/display/TvDisplay.jsx`).

### Concepto de turnos
- Cada paciente recibe un **turno secuencial** (`A-001`, `A-002`, …) al hacer **check-in presencial** (`pages/reception/Checkin.jsx` llama a `sendToTriage` con el turno calculado por `nextTurno`).
- El turno = orden de llegada = el número mostrado en la TV.
- Están "en cola" las citas del día con estado en `QUEUE_PIPELINE`: `en_espera_triaje`, `en_triaje`, `triaje_completado`, `en_atencion`.
- El orden siempre es por turno (`queuedToday` ordena con `turnoOf`).

### Tablero de gestión (`WaitingQueue.jsx`)
- Dos columnas: **"Esperando turno"** (ordenado por turno, con chip "SIGUIENTE EN LLAMAR") y **"Activos ahora"**.
- Tarjeta por paciente: turno, avatar, especialidad · médico, consultorio, hora de cita y tiempo de espera.
- **Acciones según estado:**

| Estado | Acción | Transición | Acción de contexto |
|---|---|---|---|
| `en_espera_triaje` | Llamar a triaje | → `en_triaje` | `startTriage` |
| `en_triaje` | Finalizar triaje | → `triaje_completado` | `finalizeTriage` |
| `triaje_completado` | Llamar a consulta | → `en_atencion` | `startAttention` |
| `en_atencion` | Marcar atendida | → `atendida` (sale de la cola) | `markAttended` |

- Fila de estadísticas en vivo: esperando / en triaje / en consulta / atendidos hoy.
- Botón **"Abrir pantalla TV"** (`window.open('/tv')`) y **"Restablecer demo"** (`resetDemo` vuelve al mock inicial).
- Sección "Atendidos hoy" con historial compacto.
- `finalizeTriage` guarda un triaje mínimo si no existe (`motivo`, alergias, observaciones, enfermera, hora) para no romper el flujo del médico.

### Pantalla de TV (`TvDisplay.jsx`)
- Fullscreen, tema oscuro, sin panel. Encabezado con marca, fecha, **reloj en vivo** y contador de atendidos.
- Paneles "**AHORA · EN TRIAGE**" y "**AHORA · EN CONSULTA**" con el turno en grande (animación de pulso), nombre y consultorio.
- Lista "**PRÓXIMOS TURNOS**" (hasta 5) y resto de la cola atenuado.
- Pie con indicador "Transmisión en vivo".

### Actualización automática
1. **Misma pestaña**: el tablero y el TV comparten el `AppContext`; cada acción re-renderiza el TV al instante.
2. **Otras pestañas**: `appointments` se persiste en `localStorage` (`procitas-appointments-v1`) y se sincroniza vía el evento `storage` (`AppContext.jsx`), por lo que un TV abierto en otra ventana del mismo navegador se actualiza en el momento en que recepción/enfermería llaman o pasan pacientes.
3. **Modo demo**: botón en el TV que avanza la cola sola cada 4.5 s (llama triaje → finaliza triaje → llama consulta → marca atendida) para demostrar el cambio de números sin intervención.

**Helpers exportados por el contexto:** `turnoOf`, `nextTurno`, `queuedToday`, `QUEUE_TODAY`, `QUEUE_PIPELINE`.

**Archivos.** `pages/queue/WaitingQueue.jsx` + `WaitingQueue.css`, `pages/display/TvDisplay.jsx` + `TvDisplay.css`, y adiciones en `context/AppContext.jsx` (`finalizeTriage`, `markAttended`, `resetDemo`, persistencia/sync).

---

## Módulo 14 · Operación de recepción

**Rutas.** `/recepcion`, `/recepcion/nueva-cita`, `/recepcion/checkin`, `/recepcion/pago`, `/recepcion/cancelaciones`, `/recepcion/lista-espera`.

### Agenda general (`Agenda.jsx`)
- Todas las citas del día de todos los médicos, filtrables por especialidad y médico.
- Pestañas de navegación de días (04–07 agosto, visuales).
- Estadísticas: citas hoy, en flujo de atención, documentadas, médicos con agenda.

### Registro de cita (`NewAppointment.jsx`) — wizard 3 pasos
1. **Paciente**: búsqueda por nombre/DNI (hasta 4 resultados) o **"alta rápida"** (nombre ≥5, DNI de 8 dígitos, celular de 9, correo opcional).
2. **Especialidad y médico**: misma selección de especialidades que el paciente; lista de médicos con rating y bio.
3. **Horario y confirmar**: calendario semanal (05–08 agosto) con slots libres; checkbox "Registrar el pago ahora (efectivo/yape)".
- Al confirmar: `bookAppointment` crea la cita con `status: 'confirmada'` y, si `payNow`, `addPayment` registra el pago `pagado` (Efectivo, comprobante al instante). Pantalla final con resumen y accesos a agenda/pago.
- **Nota**: a diferencia del paciente, aquí la cita nace `confirmada` (ya pagada o por cobrar en caja).

### Check-in presencial (`Checkin.jsx`)
- Lista de citas del día filtrable por nombre/DNI/N.º de cita.
- Estado por cita: `pagada` → botón **"Llegó · enviar a Triaje"**; ya enviadas → "En triaje"; `agendada` → "Esperando pago"; otros → `Badge`.
- `mark(a)`: llama `sendToTriage(id, nombre, consultorio, turno)` — **asigna el turno `A-00X`** y muestra en el toast "Turno A-00X en la pantalla". Reglas de cancelación: `canCheckIn` solo para `pagada`.

### Cobros (`Payment.jsx`)
- Dropdown con citas `agendada` sin pago registrado; monto prellenado según especialidad (editable), método (Efectivo, Yape, Plin, Transferencia, Tarjeta POS).
- Al registrar: crea pago `pagado` (`paidType: 'total'`, `verifiedBy: 'Sofía Mendoza'`, recibo aleatorio `R-2026-XXXX`) y la cita pasa a `pagada`, habilitando el check-in. Modal con comprobante (membrete centralizado de `data/clinic.js`) y descarga PDF simulada.
- **Completar abonos 50% (pasarela)**: panel que lista citas `pagada` con `paidType: 'adelanto'` que aún tienen saldo. Muestra total, abonado (via `paidTotalOf`) y saldo; botón "Cobrar S/ XX" abre modal con método y registra un pago `pagado` `paidType: 'total'` por el saldo; la cita pasa a `paidType: 'total'` (pagada al 100%).
- Panel lateral con pagos `pendiente_verificacion`.

### Cancelaciones y reprogramaciones (`Cancellations.jsx`)
- Pestañas **Próximas** (activas) y **Canceladas**.
- Acciones: cancelar (modal de confirmación → `cancelada`, libera el cupo, auditoría) o reprogramar (modal con 07/08 15:30 → `reprogramada`, confirmación SMS simulada).
- Detecta **cancelaciones tardías** (<12 h: citas del 05/08 desde las 14:00) con advertencia.

**Archivos.** `pages/reception/Agenda.jsx`, `NewAppointment.jsx`, `Checkin.jsx`, `Payment.jsx`, `Cancellations.jsx` (+ CSS por página), y el tablero compartido `pages/queue/WaitingQueue.jsx`.

---

## Módulo 15 · Administración

**Rutas.** `/admin`, `/admin/usuarios`, `/admin/especialidades`, `/admin/consultorios`, `/admin/reportes`, `/admin/configuracion`, `/admin/auditoria`.

### Indicadores (`Dashboard.jsx`)
- KPIs desde el contexto: citas del mes, tasa de cancelación, inasistencia (valor fijo) e ingresos por pagos `pagado`.
- Gráficos estáticos: ocupación por especialidad y tendencia semanal.
- Accesos rápidos a Usuarios, Especialidades, Auditoría y Reportes.

### Usuarios y roles (`Users.jsx`)
- Lista con filtro por rol y búsqueda por nombre/correo.
- `Switch` activar/desactivar cuenta (con toast), `Badge` de rol.
- "Crear cuenta": modal validado (nombre ≥5, correo con regex y **unicidad** contra la lista) → agrega usuario al contexto y muestra invitación simulada. Botón "⋯" es demo (toast).

### Especialidades (`Specialties.jsx`)
- Tarjetas por especialidad con `SpecialtyIcon`, `Switch` de activación, precio y badge de médicos asociados.
- Modal crear/editar (nombre y precio; id generado como slug). Al desactivar una especialidad con médicos activos → `ConfirmDialog` de advertencia ("dejarán de recibir reservas nuevas"). "Eliminar" es demo.

### Consultorios (`Consultorios.jsx`)
- Tarjetas por consultorio: piso (Piso 1/2), área, chips de especialidades, badge de médicos y contador de citas futuras (fecha ≥ `2026-08-05`, sin canceladas/reprogramadas/documentadas).
- Modal crear/editar con selector tipo chip de especialidades (validación: nombre, área, ≥1 especialidad). Al desactivar un consultorio en uso → `ConfirmDialog` de advertencia.

### Reportes (`Reports.jsx`)
- 8 operaciones simuladas (citas, pagos, cancelaciones) filtrables por tipo y periodo (Agosto/Julio/Junio 2026, Últimos 90 días).
- Resumen: citas, cancelaciones e ingresos confirmados. Botón "Exportar" → toast demo.

### Configuración (`Settings.jsx`)
- Reglas editables (estado `settings` del contexto): anticipación mínima de cancelación (12 h), anticipación mínima de reserva (referencial), expiración de token (30 min), ventana de confirmación de lista de espera (15 min).
- Días no laborables: calendario táctil (Agosto 2026) para marcar/desmarcar.

### Auditoría (`AuditLog.jsx`)
- Tabla de eventos con filtro por veredicto (Éxito/Advertencia/Bloqueado) y búsqueda por texto (usuario, acción, detalle).
- Nota sobre la política de bloqueo tras 5 intentos fallidos. "Políticas" y "Exportar CSV" son demo.

**Archivos.** `pages/admin/Dashboard.jsx`, `Users.jsx`, `Specialties.jsx`, `Consultorios.jsx`, `Reports.jsx`, `Settings.jsx`, `AuditLog.jsx` (+ CSS por página).

---

## Mapa de dependencias entre módulos

```
Público (2) ──▶ Login (1) ──▶ Paneles por rol (9–15)
      │                          │
      └── Disponibilidad ──┐     └── Estado global (AppContext) + mock.js
                           ▼
                    Reserva (3) ──▶ Mis citas (4) ──▶ Pagos (7)
                           │
                           ├── Pasarela 50%/100% (3) ──▶ cita "pagada" (check-in directo)
                           ├──▶ Lista de espera cupos (6)
                           ▼
                  Pago recepción (14) ──▶ Check-in (14) ──▶ Turno A-00X
                           │
                           └── Cobro de saldo de abono 50% (14)
                                                                 │
        Triaje enfermería (12) ◀─────────── Cola del día (13) ───┘
                                                                 │
        Agenda médico (9) ◀─ triaje_completado                    │
                          │                                       │
                 Diagnóstico (11) ──▶ Historial (5)               │
                                                                  ▼
                                                       Pantalla TV /tv (13)
```

**Ciclo de datos central:** `agendada → pagada → check-in (turno) → en_espera_triaje → en_triaje → triaje_completado → en_atencion → documentada`, compartido por los módulos 3, 4, 9, 11, 12, 13 y 14.
