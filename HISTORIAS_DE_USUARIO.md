# Historias de Usuario — SGCM-CMAS

Documento que desglosa en **historias de usuario** toda la funcionalidad del prototipo **SGCM-CMAS** (gestión de citas médicas, Ayacucho). Complementa el `README.md` y el `docs/MODULOS.md` con el inventario de requerimientos funcionales redactado en formato estándar.

> **Formato usado**
>
> - **Historia**: `Como [rol], quiero [acción], para [beneficio]`.
> - **Criterios de aceptación**: condiciones que deben cumplirse para dar la historia por terminada.
> - **Escenarios**: casos de prueba en formato Gherkin (`Dado` / `Cuando` / `Entonces`), redactados según el comportamiento del prototipo.
> - **Archivos de implementación** referidos a `src/`.
> - Los **requisitos funcionales y no funcionales** derivados de estas historias se documentan en [`REQUISITOS.md`](REQUISITOS.md), el modelo de datos en [`MER.md`](MER.md) y los casos de uso en [`CASOS_DE_USO.md`](CASOS_DE_USO.md).

---

## Índice

| # | Área | Rol | Historias |
|---|---|---|---|
| 1 | Autenticación y acceso | Todos | US-01 a US-04 |
| 2 | Público y búsqueda de disponibilidad | Público | US-05, US-06 |
| 3 | Reserva y pago en línea | Paciente | US-07 a US-09 |
| 4 | Mis citas y check-in móvil | Paciente | US-10 a US-13 |
| 5 | Historial clínico | Paciente | US-14, US-15 |
| 6 | Lista de espera de cupos | Paciente | US-16 a US-18 |
| 7 | Pagos del paciente | Paciente | US-19 |
| 8 | Perfil del paciente | Paciente | US-20 |
| 9 | Agenda y disponibilidad del médico | Médico | US-21 a US-23 |
| 10 | Atención, diagnóstico y ficha del paciente | Médico | US-24 a US-26 |
| 11 | Triaje de enfermería | Enfermería | US-27 a US-29 |
| 12 | Cola del día + pantalla de TV | Recepción y Enfermería | US-30 a US-32 |
| 13 | Operación de recepción | Recepción | US-33 a US-37 |
| 14 | Administración | Administración | US-38 a US-44 |

**Total: 44 historias de usuario** (US-01 … US-44).

---

## 1. Autenticación y acceso (Todos)

### US-01 · Iniciar sesión con detección de rol
**Como** usuario del sistema, **quiero** ingresar con correo y contraseña en un único formulario y que se detecte mi rol automáticamente, **para** acceder al panel correspondiente sin pasos adicionales.

**Criterios de aceptación:**
- El correo se valida con regex y la contraseña debe tener ≥ 6 caracteres.
- El rol se infiere por heurística del correo (`medico`, `diana`/`enfermera`, `sofia`, `huaraca`/`admin`, cualquier otro → `paciente`).
- Al validar, redirige al panel inicial del rol en ~700 ms y registra auditoría "Inicio de sesión".
- Existen botones de **acceso rápido demo** que ingresan a cada panel en 500 ms.

**Escenarios:**
- **Inicio de sesión exitoso**: Dado que ingreso el correo `rosa.quispe@cmas.com` y una contraseña de ≥ 6 caracteres, cuando presiono "Ingresar", entonces el sistema detecta el rol médico, redirige a `/medico` y registra la auditoría.
- **Datos inválidos**: Dado que ingreso un correo mal formado o una contraseña de < 6 caracteres, cuando presiono "Ingresar", entonces se muestra un error de validación y no se inicia sesión.
- **Acceso rápido demo**: Dado que estoy en `/login`, cuando hago clic en un botón de acceso rápido (p. ej. "Paciente"), entonces entro al panel correspondiente en 500 ms sin credenciales.

**Archivos:** `src/pages/public/Login.jsx`, `src/context/AppContext.jsx` (`login`, `logout`).

### US-02 · Registrarse como paciente
**Como** persona interesada, **quiero** crear una cuenta de paciente desde el registro público, **para** poder reservar y gestionar citas médicas.

**Criterios de aceptación:**
- Campos: nombre (≥ 5), correo (válido y **no duplicado**), DNI (8 dígitos), celular (9), fecha de nacimiento, contraseña (mayúscula + número + ≥ 6) y confirmación, "cómo te enteraste" y términos.
- Valida la unicidad del correo contra los pacientes existentes.
- Al enviar, inicia sesión como paciente y navega a `/paciente`.

**Escenarios:**
- **Registro exitoso**: Dado que completo todos los campos correctamente y acepto los términos, cuando presiono "Crear cuenta", entonces se inicia sesión como paciente y se navega a `/paciente` tras 700 ms.
- **Correo duplicado**: Dado que ingreso un correo que ya existe en el sistema, cuando el campo pierde el foco, entonces se muestra "Este correo ya está registrado" y no se permite continuar.
- **Campos inválidos**: Dado que el DNI no tiene 8 dígitos o la clave no cumple la política, cuando intento enviar, entonces se muestran errores por campo y no se crea la cuenta.

**Archivos:** `src/pages/public/Register.jsx`.

### US-03 · Recuperar contraseña
**Como** usuario que olvidó su clave, **quiero** solicitar un enlace de recuperación y definir una nueva contraseña, **para** recuperar el acceso a mi cuenta.

**Criterios de aceptación:**
- Paso 1: solicitar correo → pantalla "Revisa tu correo" (enlace válido 30 min, un solo uso).
- Paso 2: definir nueva clave con la misma política de seguridad y confirmación.
- Al guardar, redirige a `/login` tras 1.4 s.

**Escenarios:**
- **Solicitud de enlace**: Dado que estoy en `/recuperar`, cuando ingreso mi correo y confirmo, entonces se muestra "Revisa tu correo" con un enlace válido por 30 minutos.
- **Nueva contraseña**: Dado que abro el enlace de ejemplo en `/recuperar/nueva-password`, cuando ingreso una clave válida y su confirmación, entonces se guarda, se muestra un toast y se redirige a `/login`.

**Archivos:** `src/pages/public/RecoverPassword.jsx`, `src/pages/public/NewPassword.jsx`.

### US-04 · Cerrar sesión
**Como** usuario autenticado, **quiero** cerrar sesión desde mi panel, **para** proteger mi cuenta al terminar la sesión de trabajo.

**Criterios de aceptación:**
- El cierre de sesión limpia `auth` y redirige al login.
- Registra auditoría del cierre.

**Escenario:**
- **Cierre de sesión**: Dado que tengo una sesión iniciada, cuando presiono "Cerrar sesión", entonces se limpia la autenticación, se redirige a `/login` y se registra la auditoría.

**Archivos:** `src/context/AppContext.jsx` (`logout`), `src/components/layout/PanelLayout.jsx`.

---

## 2. Público y búsqueda de disponibilidad (Público)

### US-05 · Ver la landing del centro
**Como** visitante, **quiero** ver la página de inicio con las especialidades y el proceso de reserva, **para** conocer el centro y decidir agendar una cita.

**Criterios de aceptación:**
- Muestra hero, estadísticas ficticias, grilla de especialidades, "Cómo funciona" (3 pasos) y CTA de lista de espera.
- Cada especialidad enlaza a `/disponibilidad`.

**Escenarios:**
- **Ver la landing**: Dado que visito `/`, cuando la página carga, entonces se muestran el hero, las 7 especialidades, el proceso en 3 pasos y el CTA de lista de espera.
- **Navegar a una especialidad**: Dado que estoy en la landing, cuando hago clic en una especialidad, entonces navego a `/disponibilidad`.

**Archivos:** `src/pages/public/Landing.jsx`.

### US-06 · Buscar disponibilidad sin iniciar sesión
**Como** visitante, **quiero** buscar horarios libres por especialidad y rango de fechas sin estar autenticado, **para** evaluar opciones antes de registrarme.

**Criterios de aceptación:**
- Se elige especialidad (todas o una) y rango de fechas (por defecto 05–09/08/2026).
- Solo se muestran slots libres (se excluyen citas ocupadas no canceladas).
- Al elegir un slot, "Reservar" redirige a `/login`.
- Estado demo: la especialidad `cardiologia` fuerza el `EmptyState` "No hay horarios disponibles" con sugerencia de lista de espera.

**Escenarios:**
- **Búsqueda con resultados**: Dado que elijo Medicina General y el rango 05–09/08/2026, cuando presiono "Buscar", entonces se listan los médicos con solo sus slots libres.
- **Sin disponibilidad**: Dado que elijo la especialidad Cardiología hasta el 09/08/2026, cuando busco, entonces se muestra "No hay horarios disponibles" con sugerencia de inscripción a la lista de espera.
- **Reserva sin sesión**: Dado que elijo un slot libre, cuando presiono "Reservar", entonces se redirige a `/login` con el toast "Casi listo".

**Archivos:** `src/pages/public/SearchAvailability.jsx`.

---

## 3. Reserva y pago en línea (Paciente)

### US-07 · Reservar una cita en 3 pasos
**Como** paciente autenticado, **quiero** reservar una cita eligiendo especialidad, médico y horario en un asistente de 3 pasos, **para** asegurar mi atención en la fecha que prefiera.

**Criterios de aceptación:**
- Paso 1: grilla de 7 especialidades (ícono, descripción, precio); no se continúa sin selección.
- Paso 2: lista de médicos de la especialidad (rating, experiencia, universidad, contador de libres) y calendario semanal 05–11/08 con slots libres/ocupados.
- Paso 3: resumen (especialidad, médico, fecha, hora, costo, ubicación), motivo opcional y selector de pago.
- Al confirmar, la cita se crea y bloquea el horario en todo el sistema.
- Si el toggle "Simular conflicto de concurrencia" está activo, se muestra un modal con 3 alternativas.

**Escenarios:**
- **Reserva exitosa**: Dado que seleccioné especialidad, médico y un slot libre y confirmé el resumen, cuando presiono "Confirmar reserva", entonces se crea la cita con su N.º (`C-XXXX`), se bloquea el horario y se muestra la pantalla de éxito.
- **Sin especialidad seleccionada**: Dado que estoy en el paso 1, cuando no selecciono ninguna especialidad e intento continuar, entonces el botón "Continuar" está deshabilitado.
- **Conflicto de concurrencia**: Dado que el toggle "Simular conflicto de concurrencia" está activo, cuando confirmo la reserva, entonces se muestra el modal "Este horario ya no está disponible" con 3 alternativas sugeridas; al elegir una se reemplaza el slot y continúa.

**Archivos:** `src/pages/patient/BookAppointment.jsx`, `src/context/AppContext.jsx` (`bookAppointment`).

### US-08 · Pagar la cita en línea (abono 50% o pago total)
**Como** paciente, **quiero** pagar por adelantado en línea el 50% o el 100% del costo de mi cita con tarjeta, **para** asegurar mi asistencia y agilizar el check-in.

**Criterios de aceptación:**
- Opciones: **Abono 50%** (recomendado), **Pago total 100%** o **Pagar en caja**.
- El `PaymentGateway` simula: formulario de tarjeta (titular, número auto-formateado, marca detectada, vencimiento, CVV) → procesamiento ~1.6 s → éxito con operación `OP-2026-XXXX`.
- Con pago en línea la cita nace `pagada` con `paidType: 'adelanto' | 'total'` y queda **lista para check-in** (el saldo 50% se cobra en recepción).
- Sin pago en línea la cita queda `agendada` (espera pago en caja).

**Escenarios:**
- **Abono 50%**: Dado que elijo "Abono 50%" y completo la tarjeta, cuando confirmo el pago, entonces se muestra el procesamiento (~1.6 s) y la operación `OP-2026-XXXX`; la cita nace `pagada` con `paidType: 'adelanto'` y queda lista para el check-in.
- **Pago total**: Dado que elijo "Pago total 100%", cuando confirmo el pago, entonces la cita nace `pagada` con `paidType: 'total'`.
- **Pagar en caja**: Dado que elijo "Pagar en caja", cuando confirmo la reserva, entonces la cita queda `agendada` sin pago registrado.

**Archivos:** `src/components/PaymentGateway.jsx`, `src/pages/patient/BookAppointment.jsx`.

### US-09 · Ver la confirmación de la reserva
**Como** paciente, **quiero** ver un resumen completo de mi cita tras reservar, **para** guardar los datos de mi atención (fecha, hora, consultorio, N.º de cita y estado de pago).

**Criterios de aceptación:**
- Pantalla de éxito con médico, especialidad, consultorio, fecha, hora, costo y N.º de cita.
- Si pagó en línea: fila "Pago en línea" con `Abono 50%`/`Pago total`, saldo en caja y operación.
- Accesos a "Ver mis citas" e "Ir al inicio".

**Escenarios:**
- **Confirmación con pago en línea**: Dado que reservé y pagué en línea, cuando se muestra la pantalla de éxito, entonces veo el resumen, la fila "Pago en línea" con el tipo de pago, el saldo en caja (si es 50%) y la operación `OP-2026-XXXX`.
- **Confirmación sin pago**: Dado que reservé con "Pagar en caja", cuando se muestra la pantalla de éxito, entonces veo el resumen básico sin fila de pago en línea.

**Archivos:** `src/pages/patient/BookAppointment.jsx`.

---

## 4. Mis citas y check-in móvil (Paciente)

### US-10 · Ver mis citas (próximas, pasadas y canceladas)
**Como** paciente, **quiero** ver mis citas agrupadas en pestañas, **para** tener claro qué atenciones tengo pendientes y cuáles ya ocurrieron.

**Criterios de aceptación:**
- Pestañas Próximas / Pasadas / Canceladas usando `AppointmentCard`.
- Estados de flujo clínico con mensajes vivos ("Te está evaluando la enfermera", "Estás en consulta", etc.).

**Escenario:**
- **Visualizar citas**: Dado que tengo citas en distintos estados, cuando entro a `/paciente/citas`, entonces veo las pestañas Próximas/Pasadas/Canceladas y cada tarjeta muestra el badge de estado y el mensaje vivo correspondiente.

**Archivos:** `src/pages/patient/MyAppointments.jsx`, `src/components/AppointmentCard.jsx`.

### US-11 · Reprogramar una cita
**Como** paciente, **quiero** reprogramar una cita próxima, **para** ajustarla a mi disponibilidad.

**Criterios de aceptación:**
- Mueve la cita a una fecha fija demo (`11/08 · 11:00`) con estado `reprogramada`.

**Escenario:**
- **Reprogramar**: Dado que tengo una cita próxima, cuando presiono "Reprogramar" y confirmo, entonces la cita pasa a `reprogramada` con fecha `11/08 · 11:00`.

**Archivos:** `src/pages/patient/MyAppointments.jsx`.

### US-12 · Cancelar una cita con alerta de cancelación tardía
**Como** paciente, **quiero** cancelar una cita y recibir advertencia si es tarde, **para** liberar mi cupo de forma informada.

**Criterios de aceptación:**
- Si es menos de 12 h antes (citas del 04/05 de agosto después de las 12:00), muestra advertencia de cancelación tardía.
- Al cancelar se libera el horario y se registra auditoría con el email del usuario.

**Escenarios:**
- **Cancelación normal**: Dado que tengo una cita con más de 12 h de anticipación, cuando confirmo la cancelación, entonces la cita pasa a `cancelada`, el horario se libera y se registra la auditoría.
- **Cancelación tardía**: Dado que tengo una cita del 05/08 después de las 12:00, cuando intento cancelar, entonces el modal advierte sobre la cancelación tardía antes de confirmar.

**Archivos:** `src/pages/patient/MyAppointments.jsx`.

### US-13 · Confirmar mi llegada desde el móvil (check-in)
**Como** paciente, **quiero** confirmar mi llegada al centro desde el celular, **para** avisar que estoy presente y avanzar en la cola.

**Criterios de aceptación:**
- Solo disponible con cita `agendada` o `pagada` (sin pago no hay check-in).
- Al confirmar la cita pasa a `check_in` y se registra auditoría.
- Recuerda llegar 10 minutos antes con DNI.

**Escenarios:**
- **Check-in de cita pagada**: Dado que tengo una cita `pagada`, cuando presiono "Confirmar mi llegada", entonces la cita pasa a `check_in`, se registra la auditoría y se muestra el aviso de llegar 10 minutos antes con DNI.
- **Sin pago**: Dado que tengo una cita `agendada` sin pago, cuando entro a `/paciente/checkin`, entonces veo la advertencia de que sin pago no hay check-in.

**Archivos:** `src/pages/patient/PatientCheckin.jsx`.

---

## 5. Historial clínico (Paciente)

### US-14 · Consultar mi historial clínico
**Como** paciente, **quiero** ver mis atenciones documentadas en una línea de tiempo expandible, **para** revisar diagnósticos, notas y triajes de forma accesible.

**Criterios de aceptación:**
- Filtra citas `atendida`/`documentada` del paciente, de más reciente a más antigua.
- Cada entrada expandible muestra diagnóstico (`diag.dx`), notas, costo y N.º de cita.

**Escenarios:**
- **Con atenciones**: Dado que tengo citas `documentada`, cuando entro a `/paciente/historial`, entonces veo la línea de tiempo ordenada y puedo expandir cada entrada para ver diagnóstico, notas, costo y N.º de cita.
- **Sin atenciones**: Dado que no tengo citas documentadas, cuando entro a `/paciente/historial`, entonces veo el estado vacío con el conteo en cero.

**Archivos:** `src/pages/patient/PatientHistory.jsx`.

### US-15 · Descargar el historial en PDF
**Como** paciente, **quiero** descargar mi historial (por cita o completo) en PDF, **para** llevarlo a otro especialista o conservarlo.

**Criterios de aceptación:**
- La descarga es **simulada** (solo toast), desde el encabezado o desde cada cita con confirmación.

**Escenario:**
- **Descargar PDF**: Dado que estoy en mi historial, cuando presiono "Descargar PDF" (por cita con confirmación o completo), entonces se muestra un toast simulando la descarga.

**Archivos:** `src/pages/patient/PatientHistory.jsx`.

---

## 6. Lista de espera de cupos (Paciente)

### US-16 · Inscribirse en la lista de espera
**Como** paciente, **quiero** inscribirme en la lista de espera de citas liberadas por especialidad, médico y horario preferido, **para** recibir un cupo si otro paciente cancela.

**Criterios de aceptación:**
- Wizard 3 pasos: especialidad → médico + rango horario (Mañana/Mediodía/Tarde/Cualquiera) → resumen con aviso de ventana de 15 min.
- La inscripción queda `en_espera` con posición estimada `~N°3`.
- Tras 2.2 s la demo redirige a la oferta de cupo.

**Escenarios:**
- **Inscripción exitosa**: Dado que completé los 3 pasos del wizard, cuando confirmo la inscripción, entonces la entrada queda `en_espera` con posición `~N°3` y tras 2.2 s la demo redirige a la oferta de cupo.
- **Wizard incompleto**: Dado que no seleccioné especialidad, cuando intento avanzar, entonces no puedo continuar al siguiente paso.

**Archivos:** `src/pages/patient/WaitlistEnroll.jsx`, `src/context/AppContext.jsx` (`enrollWaitlist`).

### US-17 · Confirmar o rechazar una oferta de cupo
**Como** paciente inscrito, **quiero** recibir una oferta de cupo con cuenta regresiva de 15 minutos y decidir si la acepto, **para** aprovechar la cancelación de otro paciente.

**Criterios de aceptación:**
- Banner urgente + cuenta regresiva (`useCountdown`, advertencia a ≤ 180 s).
- **Confirmar** → crea la cita automáticamente (`agendada`) + pago `pendiente_verificacion`.
- **Rechazar** → vuelve a `en_espera` conservando la posición.
- Herramientas demo: "Simular oferta" y "Simular expiración".

**Escenarios:**
- **Confirmar el cupo**: Dado que tengo una oferta activa con cuenta regresiva, cuando presiono "Confirmar cupo ofrecido", entonces se crea la cita (`agendada`) con un pago `pendiente_verificacion` y se muestra el resumen con el N.º de cita.
- **Rechazar el cupo**: Dado que tengo una oferta activa, cuando presiono "Rechazar", entonces la entrada vuelve a `en_espera` conservando la posición.
- **Expiración**: Dado que el contador llega a 0, cuando no respondo, entonces la entrada pasa a `expirada` y el cupo pasa al siguiente.

**Archivos:** `src/pages/patient/WaitlistOffer.jsx`, `src/context/AppContext.jsx` (`confirmOffer`, `rejectOffer`, `expireOffer`).

### US-18 · Ver cupo expirado
**Como** paciente que no respondió a tiempo, **quiero** ver una notificación de cupo expirado, **para** saber que mi cupo pasó al siguiente e inscribirme de nuevo si lo deseo.

**Criterios de aceptación:**
- Informa que el cupo pasó al siguiente por no responder en 15 min.
- Acciones: "Mis inscripciones" o "Inscribirme de nuevo".

**Escenario:**
- **Ver cupo expirado**: Dado que mi oferta expiró, cuando entro a `/paciente/lista-espera/expirada`, entonces veo la notificación y las acciones "Mis inscripciones" e "Inscribirme de nuevo".

**Archivos:** `src/pages/patient/WaitlistExpired.jsx`.

---

## 7. Pagos del paciente (Paciente)

### US-19 · Declarar pagos y ver comprobantes
**Como** paciente, **quiero** declarar mis pagos (Yape/Plin/Transferencia/Efectivo) eligiendo abono 50% o pago total y ver el estado de mis comprobantes, **para** cumplir con el pago de mis citas.

**Criterios de aceptación:**
- Lista de pagos con badge de estado, método, referencia y chip `Abono 50%`/`Pago total`.
- Declarar pago: modal con selección de cita sin pagar, monto (mitad o total, editable) y método.
- El pago queda `pendiente_verificacion` hasta que recepción lo verifique (<15 min); recién entonces se habilita el comprobante (PDF simulado).

**Escenarios:**
- **Declarar abono 50%**: Dado que tengo una cita sin pagar, cuando declaro un pago eligiendo "Abono 50%" y el método, entonces se crea el pago `pendiente_verificacion` con el chip `Abono 50%`.
- **Declarar pago total**: Dado que tengo una cita sin pagar, cuando declaro el pago eligiendo "Pago total 100%", entonces el monto se prellena con el total de la especialidad y el pago queda `pendiente_verificacion`.
- **Comprobante tras verificación**: Dado que recepción verificó mi pago, cuando reviso mis pagos, entonces el badge pasa a `pagado` y se habilita el comprobante (PDF simulado).

**Archivos:** `src/pages/patient/PatientPayments.jsx`.

---

## 8. Perfil del paciente (Paciente)

### US-20 · Editar mi perfil y contraseña
**Como** paciente, **quiero** actualizar mis datos personales y cambiar mi contraseña, **para** mantener mi información al día.

**Criterios de aceptación:**
- Formulario con validación por campo (nombre, correo, celular 9, DNI 8, fecha de nacimiento, dirección); guardado local con toast "Guardado ✓".
- Cambio de contraseña simulado (envía enlace al correo).

**Escenarios:**
- **Guardado válido**: Dado que completo todos los campos correctamente, cuando presiono "Guardar cambios", entonces se muestra el toast "Guardado ✓".
- **Campo inválido**: Dado que el celular no tiene 9 dígitos, cuando intento guardar, entonces se muestra el error en el campo y no se guarda.
- **Cambio de contraseña**: Dado que presiono "Cambiar contraseña", cuando confirmo, entonces se muestra un toast simulando el envío del enlace al correo.

**Archivos:** `src/pages/patient/PatientProfile.jsx`.

---

## 9. Agenda y disponibilidad del médico (Médico)

### US-21 · Ver la agenda del día
**Como** médico, **quiero** ver el timeline de mis citas del día con filtros por estado, **para** organizar mi atención en el consultorio.

**Criterios de aceptación:**
- Timeline 08:00–17:00 con bloques (hora, duración, consultorio, paciente, motivo, badge de estado).
- Filtros: Todos / En camino / Por atender / En atención / Documentadas.
- Banner de cola ("X en triaje · Y listos para atender") y simulador "Simular cancelación en vivo".

**Escenarios:**
- **Ver agenda filtrada**: Dado que entro a `/medico`, cuando aplico el filtro "Por atender", entonces solo se muestran las citas `triaje_completado`.
- **Cancelación en vivo**: Dado que estoy en la agenda, cuando presiono "Simular cancelación en vivo", entonces el horario se libera con un flash, se muestra el toast y se registra la auditoría.

**Archivos:** `src/pages/doctor/Agenda.jsx`.

### US-22 · Administrar mi disponibilidad semanal
**Como** médico, **quiero** activar o desactivar bloques de atención en una grilla de 7 días, **para** definir mis horarios de consulta.

**Criterios de aceptación:**
- Grilla 7 días × franjas de 30 min; clic para activar/desactivar.
- Modal para añadir rangos (día, inicio, fin) y diálogo para eliminar bloques.
- Detecta y marca en rojo solapamientos; las citas confirmadas se conservan.

**Escenarios:**
- **Activar/desactivar bloque**: Dado que estoy en la grilla de disponibilidad, cuando hago clic en una franja, entonces el bloque se activa o desactiva.
- **Solapamiento**: Dado que añado rangos consecutivos que se superponen, cuando se aplican, entonces los bloques solapados se marcan en rojo.
- **Bloque con cita confirmada**: Dado que un bloque tiene citas confirmadas, cuando intento eliminarlo, entonces las citas confirmadas se conservan (solo se excluyen canceladas/reprogramadas).

**Archivos:** `src/pages/doctor/Availability.jsx`.

### US-23 · Editar mi perfil profesional
**Como** médico, **quiero** editar mi información profesional (especialidad, consultorio, contacto, bio), **para** mantener mi perfil visible y actualizado.

**Criterios de aceptación:**
- Tarjeta de identidad (avatar, rating ★4.8, experiencia, universidad, CMP) + formulario editable con contador de 280 caracteres en la bio.
- Guardado local con toast y "Ver como paciente" simula la vista pública.

**Escenarios:**
- **Guardar perfil**: Dado que edité mis datos, cuando presiono "Guardar", entonces se muestra un toast de éxito y el perfil se actualiza localmente.
- **Límite de bio**: Dado que escribo en la bio, cuando supero los 280 caracteres, entonces el contador lo impide o advierte.

**Archivos:** `src/pages/doctor/Profile.jsx`.

---

## 10. Atención, diagnóstico y ficha del paciente (Médico)

### US-24 · Iniciar la atención de un paciente
**Como** médico, **quiero** iniciar la atención cuando un paciente tiene el triaje completado, **para** comenzar la consulta.

**Criterios de aceptación:**
- Botón "Iniciar atención" solo en citas `triaje_completado`, con modal de confirmación → pasa a `en_atencion`.

**Escenario:**
- **Iniciar atención**: Dado que tengo una cita `triaje_completado`, cuando presiono "Iniciar atención" y confirmo, entonces la cita pasa a `en_atencion` y habilita el registro del diagnóstico.

**Archivos:** `src/pages/doctor/Agenda.jsx`, `src/context/AppContext.jsx` (`startAttention`).

### US-25 · Registrar el diagnóstico
**Como** médico, **quiero** registrar el diagnóstico, severidad y observaciones de una consulta, **para** documentar la atención en el historial del paciente.

**Criterios de aceptación:**
- Solo editable si la cita está `en_atencion` (si no, formulario bloqueado con candado).
- Diagnóstico mínimo 5 caracteres; severidad Leve/Moderada/Severa.
- Al guardar, la cita pasa a `documentada` y redirige a la agenda.

**Escenarios:**
- **Registro exitoso**: Dado que la cita está `en_atencion`, cuando ingreso el diagnóstico (≥ 5 caracteres), la severidad y las observaciones y guardo, entonces la cita pasa a `documentada` y se redirige a la agenda.
- **Cita no editable**: Dado que la cita no está `en_atencion`, cuando entro al formulario de diagnóstico, entonces el formulario aparece bloqueado con candado.

**Archivos:** `src/pages/doctor/Diagnosis.jsx`.

### US-26 · Ver la ficha del paciente con regla de acceso
**Como** médico, **quiero** consultar el historial clínico de un paciente y sus triajes, **para** atenderlo con contexto completo.

**Criterios de aceptación:**
- Regla de acceso: si no hay citas previas no canceladas del médico, muestra "Acceso denegado" y registra auditoría del intento.
- Muestra datos del paciente, lista de citas y triaje expandible de cada atención.
- Descarga de ficha PDF simulada.

**Escenarios:**
- **Acceso permitido**: Dado que el paciente tiene citas previas no canceladas conmigo, cuando entro a `/medico/paciente/:pid`, entonces veo sus datos, citas y triajes expandibles.
- **Acceso denegado**: Dado que el paciente no tiene citas previas conmigo, cuando intento entrar a su ficha, entonces veo "Acceso denegado" y se registra la auditoría del intento.

**Archivos:** `src/pages/doctor/PatientDetail.jsx`.

---

## 11. Triaje de enfermería (Enfermería)

### US-27 · Ver la cola de triaje
**Como** enfermera, **quiero** ver los pacientes en espera de triaje y los que están en progreso, **para** atenderlos por orden de llegada.

**Criterios de aceptación:**
- Dos columnas: "Esperando triaje" (`en_espera_triaje`, ordenadas por tiempo de espera, advertencia >10 min) y "En progreso" (`en_triaje`).
- "Iniciar triaje" navega al formulario; "Continuar triaje" reingresa a uno en curso.

**Escenarios:**
- **Ver la cola**: Dado que hay pacientes `en_espera_triaje`, cuando entro a `/enfermeria`, entonces veo la cola ordenada por tiempo de espera con advertencia visual en los que superan 10 min.
- **Advertencia por demora**: Dado que un paciente lleva más de 10 min esperando, cuando reviso la cola, entonces su tarjeta muestra la advertencia de tiempo de espera.

**Archivos:** `src/pages/nurse/TriageQueue.jsx`.

### US-28 · Registrar signos vitales (triaje)
**Como** enfermera, **quiero** registrar los signos vitales y el motivo de consulta de cada paciente, **para** enviar la evaluación completa al médico.

**Criterios de aceptación:**
- Campos obligatorios: PA, temperatura, FC, peso, talla y motivo; opcionales: alergias y observaciones.
- Al completar, la cita pasa a `triaje_completado` (el médico la ve como "Por atender").
- Si el triaje ya existe, muestra vista de solo lectura.

**Escenarios:**
- **Triaje completo**: Dado que completo los obligatorios (PA, temperatura, FC, peso, talla, motivo), cuando presiono "Completar triaje y enviar al médico", entonces la cita pasa a `triaje_completado` y guarda `nurseName` y la hora.
- **Campos faltantes**: Dado que faltan campos obligatorios, cuando intento enviar, entonces el formulario no se envía y se marcan los errores.
- **Triaje existente**: Dado que el triaje ya fue registrado, cuando abro el formulario, entonces veo la vista de solo lectura.

**Archivos:** `src/pages/nurse/TriageForm.jsx`, `src/context/AppContext.jsx` (`completeTriage`).

### US-29 · Consultar el historial de triajes del turno
**Como** enfermera, **quiero** ver los triajes realizados en el turno, **para** hacer seguimiento de la atención de los pacientes.

**Criterios de aceptación:**
- Lista los triajes del día 05/08 con estados posteriores a `triaje_completado`.
- Muestra signos vitales, motivo, alergias, observaciones, médico/especialidad y responsable.

**Escenario:**
- **Ver historial**: Dado que hay triajes del turno, cuando entro a `/enfermeria/historial`, entonces veo cada registro con signos vitales, motivo, alergias, observaciones y responsable.

**Archivos:** `src/pages/nurse/TriageHistory.jsx`.

---

## 12. Cola del día + pantalla de TV (Recepción y Enfermería)

### US-30 · Asignar turno y gestionar la cola del día
**Como** recepcionista/enfermera, **quiero** gestionar la cola del día con turnos secuenciales y acciones por estado, **para** ordenar el flujo de atención en la sala de espera.

**Criterios de aceptación:**
- Cada paciente recibe un turno `A-00X` en el check-in presencial; el turno = orden de llegada.
- Acciones por estado: Llamar a triaje → `en_triaje`; Finalizar triaje → `triaje_completado`; Llamar a consulta → `en_atencion`; Marcar atendida → `atendida` (sale de la cola).
- Fila de estadísticas en vivo: esperando / en triaje / en consulta / atendidos hoy.
- "Restablecer demo" vuelve a las citas del mock inicial.

**Escenarios:**
- **Cola ordenada por turno**: Dado que hay pacientes en cola, cuando abro el tablero, entonces la cola se ordena por turno y el primero muestra el chip "SIGUIENTE EN LLAMAR".
- **Llamar a triaje**: Dado que un paciente está `en_espera_triaje`, cuando presiono "Llamar a triaje", entonces pasa a `en_triaje` y el siguiente turno asciende.
- **Finalizar triaje**: Dado que un paciente está `en_triaje`, cuando presiono "Finalizar triaje", entonces pasa a `triaje_completado` (se guarda un triaje mínimo si no existe).
- **Marcar atendida**: Dado que un paciente está `en_atencion`, cuando presiono "Marcar atendida", entonces pasa a `atendida` y sale de la cola.
- **Restablecer demo**: Dado que modifiqué la cola, cuando presiono "Restablecer demo", entonces las citas vuelven al mock inicial.

**Archivos:** `src/pages/queue/WaitingQueue.jsx`, `src/context/AppContext.jsx` (`finalizeTriage`, `markAttended`, `resetDemo`, helpers `turnoOf`, `nextTurno`, `queuedToday`).

### US-31 · Abrir la pantalla de TV en tiempo real
**Como** recepcionista/enfermera, **quiero** abrir una pantalla de TV que muestre los turnos actuales y próximos, **para** que los pacientes en la sala vean su turno y esperen informados.

**Criterios de aceptación:**
- Ruta `/tv`, fullscreen, tema oscuro, reloj en vivo y contador de atendidos.
- Paneles "AHORA · EN TRIAGE" y "AHORA · EN CONSULTA" con el turno en grande (pulso) + lista de próximos turnos (hasta 5).
- Se actualiza al instante cuando recepción/enfermería llaman o pasan pacientes (misma pestaña y otras pestañas vía `localStorage` + evento `storage`).
- Modo automático demo avanza la cola cada 4.5 s.

**Escenarios:**
- **Mostrar turnos en TV**: Dado que abro `/tv`, cuando hay pacientes en cola, entonces se muestran los paneles "AHORA · EN TRIAGE" y "AHORA · EN CONSULTA" con el turno en grande, el reloj en vivo y la lista de próximos turnos.
- **Actualización en vivo**: Dado que el tablero y el TV están abiertos, cuando recepción llama a un paciente, entonces el TV se actualiza en el instante (misma pestaña y otras pestañas del mismo navegador).
- **Modo automático**: Dado que activo "Modo automático (demo)", cuando transcurren 4.5 s por paso, entonces la cola avanza sola (triaje → consulta → atendida).

**Archivos:** `src/pages/display/TvDisplay.jsx`, `src/context/AppContext.jsx` (persistencia/sincronización).

### US-32 · Ver "Atendidos hoy"
**Como** recepcionista/enfermera, **quiero** ver el historial compacto de pacientes atendidos en el día, **para** llevar el control del cierre del turno.

**Criterios de aceptación:**
- Sección "Atendidos hoy" con el historial compacto de las citas `atendida`.

**Escenario:**
- **Ver atendidos**: Dado que hay citas `atendida` en el día, cuando reviso el tablero, entonces la sección "Atendidos hoy" las muestra en orden.

**Archivos:** `src/pages/queue/WaitingQueue.jsx`.

---

## 13. Operación de recepción (Recepción)

### US-33 · Ver la agenda general del día
**Como** recepcionista, **quiero** ver la agenda del día de todos los médicos con filtros, **para** orientar a los pacientes y controlar la operación.

**Criterios de aceptación:**
- Todas las citas del día de todos los médicos, filtrables por especialidad y médico.
- Estadísticas: citas hoy, en flujo de atención, documentadas, médicos con agenda.

**Escenario:**
- **Agenda filtrada**: Dado que entro a `/recepcion`, cuando filtro por especialidad o médico, entonces solo se muestran las citas correspondientes junto con las estadísticas del día.

**Archivos:** `src/pages/reception/Agenda.jsx`.

### US-34 · Registrar una cita (con alta rápida de paciente)
**Como** recepcionista, **quiero** registrar citas para pacientes existentes o nuevos, **para** atender solicitudes presenciales o telefónicas.

**Criterios de aceptación:**
- Wizard 3 pasos: paciente (búsqueda por nombre/DNI o "alta rápida") → especialidad/médico → horario + checkbox "Registrar el pago ahora".
- La cita nace `confirmada`; con pago inmediato se genera comprobante al instante.

**Escenarios:**
- **Paciente existente**: Dado que busco a un paciente por nombre/DNI, cuando completo especialidad, médico, horario y confirmo, entonces la cita nace `confirmada` y se muestra el resumen.
- **Alta rápida**: Dado que no encuentro al paciente, cuando uso "Alta rápida" con nombre (≥ 5), DNI (8) y celular (9), entonces se crea el paciente y la cita.
- **Con pago inmediato**: Dado que marco "Registrar el pago ahora", cuando confirmo la cita, entonces se registra el pago `pagado` (Efectivo) con comprobante al instante.

**Archivos:** `src/pages/reception/NewAppointment.jsx`.

### US-35 · Realizar el check-in presencial y asignar turno
**Como** recepcionista, **quiero** confirmar la llegada del paciente, pagarlo si hace falta y asignarle su turno, **para** enviarlo a la cola de triaje.

**Criterios de aceptación:**
- Lista de citas del día filtrable por nombre/DNI/N.º de cita.
- `pagada` → "Llegó · enviar a Triaje" (asigna turno `A-00X` y lo muestra en el toast).
- `check_in` (confirmó llegada desde el móvil) → "Llegó · enviar a Triaje" (asigna turno `A-00X` y lo muestra en el toast).
- `agendada` → "Esperando pago"; el check-in solo está habilitado para citas pagadas.

**Escenarios:**
- **Check-in de cita pagada**: Dado que el paciente tiene una cita `pagada`, cuando presiono "Llegó · enviar a Triaje", entonces se asigna el turno secuencial `A-00X` y se muestra "Turno A-00X en la pantalla" en el toast.
- **Check-in de cita con llegada móvil**: Dado que el paciente ya hizo check-in desde el móvil (cita `check_in`), cuando presiono "Llegó · enviar a Triaje", entonces se asigna el turno `A-00X` y la cita pasa a `en_espera_triaje`.
- **Cita sin pagar**: Dado que el paciente tiene una cita `agendada`, cuando reviso su fila, entonces veo "Esperando pago" y el botón de check-in deshabilitado.

**Archivos:** `src/pages/reception/Checkin.jsx`, `src/context/AppContext.jsx` (`sendToTriage`).

### US-36 · Cobrar citas y completar abonos del 50%
**Como** recepcionista, **quiero** cobrar citas pendientes y completar los abonos del 50% pagados por pasarela, **para** registrar los ingresos del centro.

**Criterios de aceptación:**
- Cobrar cita `agendada`: monto prellenado según especialidad (editable), método (Efectivo, Yape, Plin, Transferencia, Tarjeta POS) → la cita pasa a `pagada` y habilita el check-in.
- Completar abono 50%: muestra total, abonado y saldo; al cobrar el saldo la cita queda al 100% (`paidType: 'total'`).
- Comprobante `R-2026-XXXX` con descarga PDF simulada.
- Panel lateral con pagos `pendiente_verificacion`.

**Escenarios:**
- **Cobrar cita pendiente**: Dado que una cita está `agendada`, cuando selecciono el método y registro el cobro, entonces se crea el pago `pagado`, se genera el comprobante `R-2026-XXXX` y la cita pasa a `pagada` (habilita el check-in).
- **Completar abono 50%**: Dado que una cita tiene `paidType: 'adelanto'`, cuando cobro el saldo, entonces se registra el pago por el saldo y la cita queda al 100% (`paidType: 'total'`).
- **Verificar pago declarado**: Dado que hay pagos `pendiente_verificacion`, cuando los verifico, entonces quedan `pagado` y se habilita el comprobante del paciente.

**Archivos:** `src/pages/reception/Payment.jsx`, `src/context/AppContext.jsx` (`addPayment`).

### US-37 · Cancelar y reprogramar citas
**Como** recepcionista, **quiero** cancelar o reprogramar citas con confirmación, **para** gestionar cambios de última hora.

**Criterios de aceptación:**
- Pestañas Próximas / Canceladas; acciones cancelar (modal → `cancelada`, libera el cupo, auditoría) o reprogramar (`reprogramada` + confirmación SMS simulada).
- Detecta cancelaciones tardías (<12 h: citas del 05/08 desde las 14:00) con advertencia.

**Escenarios:**
- **Cancelar cita**: Dado que una cita está activa, cuando la cancelo y confirmo, entonces pasa a `cancelada`, se libera el cupo y se registra la auditoría.
- **Reprogramar cita**: Dado que una cita está activa, cuando la reprogramo y confirmo, entonces pasa a `reprogramada` y se muestra la confirmación SMS simulada.
- **Cancelación tardía**: Dado que es una cita del 05/08 desde las 14:00, cuando intento cancelarla, entonces se muestra la advertencia de cancelación tardía.

**Archivos:** `src/pages/reception/Cancellations.jsx`.

---

## 14. Administración (Administración)

### US-38 · Ver el dashboard de indicadores
**Como** administrador, **quiero** ver KPIs del centro (citas, cancelaciones, inasistencia, ingresos) y gráficos, **para** monitorear la operación.

**Criterios de aceptación:**
- KPIs desde el contexto y gráficos estáticos (ocupación por especialidad, tendencia semanal).
- Accesos rápidos a Usuarios, Especialidades, Auditoría y Reportes.

**Escenario:**
- **Ver dashboard**: Dado que entro a `/admin`, cuando la página carga, entonces veo los KPIs (citas del mes, tasa de cancelación, inasistencia, ingresos), los gráficos y los accesos rápidos.

**Archivos:** `src/pages/admin/Dashboard.jsx`.

### US-39 · Gestionar usuarios y roles
**Como** administrador, **quiero** crear cuentas, asignar roles y activar/desactivar usuarios, **para** controlar quién accede al sistema.

**Criterios de aceptación:**
- Lista con filtro por rol y búsqueda por nombre/correo.
- Crear cuenta validado (nombre ≥ 5, correo con regex y unicidad) con invitación simulada.
- `Switch` activar/desactivar cuenta.

**Escenarios:**
- **Crear cuenta**: Dado que ingreso nombre válido y un correo único, cuando presiono "Crear cuenta", entonces se agrega el usuario al contexto y se muestra la invitación simulada.
- **Correo duplicado**: Dado que ingreso un correo ya existente, cuando intento crear la cuenta, entonces se muestra el error de unicidad.
- **Activar/desactivar**: Dado que presiono el `Switch` de una cuenta, cuando cambia su estado, entonces la cuenta se activa o desactiva con toast.

**Archivos:** `src/pages/admin/Users.jsx`.

### US-40 · Gestionar especialidades
**Como** administrador, **quiero** crear/editar especialidades, definir precios y activarlas, **para** mantener el catálogo de servicios.

**Criterios de aceptación:**
- Tarjetas con `Switch` de activación, precio y badge de médicos asociados.
- Modal crear/editar (nombre, precio; id slug). Al desactivar con médicos activos → advertencia.

**Escenarios:**
- **Editar especialidad**: Dado que edito nombre/precio, cuando guardo, entonces la tarjeta se actualiza.
- **Desactivar con médicos**: Dado que una especialidad tiene médicos activos, cuando la desactivo, entonces se muestra el `ConfirmDialog` de advertencia ("dejarán de recibir reservas nuevas").

**Archivos:** `src/pages/admin/Specialties.jsx`.

### US-41 · Gestionar consultorios
**Como** administrador, **quiero** crear/editar consultorios, asignar piso, área y especialidades, **para** organizar la infraestructura del centro.

**Criterios de aceptación:**
- Tarjetas con piso, área, chips de especialidades, badge de médicos y contador de citas futuras.
- Modal con selector tipo chip (validación: nombre, área, ≥ 1 especialidad). Al desactivar en uso → advertencia.

**Escenarios:**
- **Crear consultorio**: Dado que ingreso nombre, área y al menos 1 especialidad, cuando guardo, entonces el consultorio se agrega al listado.
- **Sin especialidad**: Dado que no selecciono ninguna especialidad, cuando intento guardar, entonces se muestra el error de validación.
- **Desactivar en uso**: Dado que un consultorio tiene citas futuras, cuando lo desactivo, entonces se muestra la advertencia de que está en uso.

**Archivos:** `src/pages/admin/Consultorios.jsx`.

### US-42 · Generar reportes
**Como** administrador, **quiero** generar reportes de citas, pagos y cancelaciones filtrables por tipo y periodo, **para** analizar el desempeño del centro.

**Criterios de aceptación:**
- 8 operaciones simuladas filtrables por tipo y periodo (Agosto/Julio/Junio 2026, Últimos 90 días).
- Resumen con citas, cancelaciones e ingresos; botón "Exportar" → toast demo.

**Escenarios:**
- **Filtrar reportes**: Dado que elijo un tipo y un periodo, cuando presiono "Generar", entonces se muestran las operaciones y el resumen (citas, cancelaciones, ingresos).
- **Exportar**: Dado que tengo un reporte generado, cuando presiono "Exportar", entonces se muestra el toast demo de exportación.

**Archivos:** `src/pages/admin/Reports.jsx`.

### US-43 · Configurar las reglas de negocio
**Como** administrador, **quiero** editar las reglas del sistema (cancelación, reserva, token, lista de espera y días no laborables), **para** adaptar la operación a las políticas del centro.

**Criterios de aceptación:**
- Campos editables: anticipación de cancelación (12 h), de reserva (referencial), expiración de token (30 min) y ventana de confirmación de cupo (15 min).
- Calendario táctil (Agosto 2026) para marcar/desmarcar días no laborables.

**Escenarios:**
- **Editar reglas**: Dado que modifico los valores de configuración, cuando guardo, entonces el estado `settings` del contexto se actualiza.
- **Marcar día no laborable**: Dado que toco un día del calendario, cuando lo marco/desmarco, entonces el día cambia de estado en el calendario.

**Archivos:** `src/pages/admin/Settings.jsx`.

### US-44 · Revisar la auditoría del sistema
**Como** administrador, **quiero** ver el registro de eventos de seguridad con filtros y búsqueda, **para** detectar accesos o intentos indebidos.

**Criterios de aceptación:**
- Tabla de eventos filtrable por veredicto (Éxito/Advertencia/Bloqueado) y búsqueda por texto.
- Nota sobre la política de bloqueo tras 5 intentos fallidos; "Políticas" y "Exportar CSV" son demo.

**Escenarios:**
- **Filtrar por veredicto**: Dado que elijo el veredicto "Bloqueado", cuando aplico el filtro, entonces solo se muestran los eventos de seguridad bloqueados.
- **Buscar por texto**: Dado que escribo un término de búsqueda, cuando presiono buscar, entonces la tabla muestra los eventos que coinciden con usuario, acción o detalle.

**Archivos:** `src/pages/admin/AuditLog.jsx`.

---

## Mapa de historias → módulos

Cada historia se corresponde con uno o más módulos de `docs/MODULOS.md`:

| Historias | Módulos |
|---|---|
| US-01 a US-04 | 1 · Autenticación y acceso |
| US-05, US-06 | 2 · Público y búsqueda de disponibilidad |
| US-07 a US-09 | 3 · Reserva de citas |
| US-10 a US-13 | 4 · Mis citas y check-in móvil |
| US-14, US-15 | 5 · Historial clínico |
| US-16 a US-18 | 6 · Lista de espera (cupos) |
| US-19 | 7 · Pagos del paciente |
| US-20 | 8 · Perfil del paciente |
| US-21 a US-23 | 9 y 10 · Agenda/Disponibilidad del médico |
| US-24 a US-26 | 11 · Atención, diagnóstico y ficha |
| US-27 a US-29 | 12 · Triaje de enfermería |
| US-30 a US-32 | 13 · Cola del día + pantalla de TV |
| US-33 a US-37 | 14 · Operación de recepción |
| US-38 a US-44 | 15 · Administración |
