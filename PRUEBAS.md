# SGCM-CMAS · Estrategia de pruebas

Plan de testing del SGCM-CMAS para backend y frontend. Define qué se prueba, con qué herramientas, qué datos y cuál es la cobertura mínima para pasar el gate de CI. Complementa `docs/REQUISITOS.md` (los criterios de aceptación aquí se convierten en casos de prueba).

---

## 1. Estrategia general (pirámide)

| Nivel | Qué cubre | Herramientas | Dónde corre |
|---|---|---|---|
| **Unit** | Reglas de negocio puras, servicios, helpers, utilidades | PHPUnit (backend), Vitest (frontend) | CI, por PR |
| **Integración** | Eloquent/transacciones contra PostgreSQL + Redis reales (BD de test o test containers) | PHPUnit (Laravel tests) | CI |
| **E2E API** | Flujos críticos completos vía HTTP (reserva → pago → triaje → diagnóstico) | **PHPUnit Feature tests** (`php artisan test`) | CI |
| **E2E UI** | Flujos del paciente y de la cola con API mockeada | Vitest + Testing Library + MSW | CI |
| **Manual/QA** | Checklist de humo por rol antes de cada release | Checklist (sección 7) | staging |

**Regla de oro:** las reglas de negocio viven en el backend y se prueban a nivel de servicio (unit) y e2e API; el frontend se prueba con datos mock (MSW) sin depender del backend real.

---

## 2. Herramientas

| Ámbito | Herramienta | Notas |
|---|---|---|
| Backend unit/feature | **PHPUnit** + `RefreshDatabase` (o **Pest**) | Feature tests arrancan la app HTTP completa contra BD de test |
| Frontend unit | **Vitest** + **@testing-library/react** + **user-event** | jsdom |
| Mock de API (frontend) | **MSW** (Mock Service Worker) | Replica el contrato de BACKEND.md Parte 5 |
| BD de pruebas | PostgreSQL 16 + Redis 7 (docker, volumen efímero) | Misma imagen que prod |
| Cobertura | `php artisan test --coverage` / `vitest --coverage` | Umbrales en CI (sección 6) |
| Datos | Factories (sección 5) | Reutilizables entre unit/feature |

**Comandos (Windows):**

```bash
# Backend (Laravel)
php artisan test                  # unit + feature (coverage mínimo)
php artisan test --filter=Appointment  # solo el área afectada
# Frontend
npm.cmd run test                  # Vitest unit + componentes
```

---

## 3. Casos críticos por módulo (obligatorios)

### 3.1 Autenticación
| Caso | Nivel | Criterio |
|---|---|---|
| Login correcto devuelve access+refresh y rol real | e2e API | 200, token válido, `role` de BD |
| Login con usuario inactivo | e2e API | 403 + auditoría |
| 5 intentos fallidos → bloqueo | e2e API | 429 tras el 5.º intento |
| Refresh rotativo: usar el mismo refresh dos veces | e2e API | El segundo uso revoca la familia de tokens |
| Registro con DNI/correo duplicado | e2e API | 422 con mensaje de campo |
| Registro sin consentimiento 29733 | unit/e2e | 422 |

### 3.2 Citas (núcleo)
| Caso | Nivel | Criterio |
|---|---|---|
| **Doble reserva concurrente** (2 peticiones simultáneas mismo slot) | e2e API | 1 × 201, 1 × 409 con `alternatives[]` (3 sugerencias libres) |
| Reserva en franja ocupada (no cancelada) | unit | 409 |
| Reserva en día no laborable | unit | 422 |
| Cancelación a < 12 h | unit | Aviso `late_cancellation: true` en respuesta; auditoría `warning` |
| Cancelación libera la franja | e2e API | Tras cancelar, la franja vuelve a estar disponible |
| Reprogramación a franja ocupada | unit | 409 |
| Check-in móvil solo desde `agendada`/`pagada` | unit | 422 desde otros estados |
| Asignación de turno secuencial `A-00X` | e2e API | 3 check-ins del día → A-001, A-002, A-003; nunca se repite |

### 3.3 Pagos Culqi
| Caso | Nivel | Criterio |
|---|---|---|
| Cobro 50% con token válido (mock de Culqi) | e2e API | Payment `pagado`, `gateway=true`, cita `pagada` + `paid_type='adelanto'` |
| Cobro 100% | e2e API | `paid_type='total'` |
| **Webhook repetido** (mismo `culqi_order_id`) | e2e API | Idempotente: un solo pago, sin duplicados |
| Webhook con firma inválida | e2e API | 401, sin efectos |
| `order.expired` | e2e API | Cita vuelve a `agendada` |
| Saldo 50% completado en caja | e2e API | Suma de pagos `pagado` = precio total |
| Cancelación de cita pagada → reembolso | e2e API | Payment `reembolsado` + auditoría |

### 3.4 Triaje y cola
| Caso | Nivel | Criterio |
|---|---|---|
| Transiciones válidas del pipeline | unit | Tabla de estados: cada paso solo desde su estado origen |
| Transición inválida (p. ej. `en_triaje` → `en_atencion` directo) | unit | 422 |
| Orden de la cola por turno | unit | `queuedToday` equivalent en SQL: orden `A-00X` ascendente |
| Evento `queue.updated` emitido tras cada transición | e2e (socket) | El cliente suscrito recibe el evento en < 2 s |

### 3.5 Lista de espera
| Caso | Nivel | Criterio |
|---|---|---|
| Posición cronológica al inscribirse | unit | 1.º inscrito = posición 1 |
| Oferta expira y pasa al siguiente | unit (worker) | `offer_expires_at` vencido → `expirada` + oferta al siguiente |
| Confirmar crea la cita + pago pendiente | e2e API | Cita creada, franja bloqueada, payment `pendiente_verificacion` |
| **Doble confirmación** (mismo id dos veces) | e2e API | Una sola cita creada |

### 3.6 Historial y permisos
| Caso | Nivel | Criterio |
|---|---|---|
| Médico sin relación clínica ve historial | e2e API | 403 + auditoría `Acceso denegado` |
| Médico con citas previas ve historial | e2e API | 200 con citas `documentada` |
| Paciente ve solo su propio historial | e2e API | 403 si `pid` ≠ propio |
| Descarga de PDF (URL firmada) | e2e API | 200 con URL; expira a los 7 días |

### 3.7 Reportes y settings
| Caso | Nivel | Criterio |
|---|---|---|
| Sumatorias de reportes consistentes | unit | Citas/cancelaciones/ingresos coinciden con datos sembrados |
| Cambio de `minCancelHours` se aplica sin redeploy | e2e API | Tras PATCH `/settings`, la cancelación respeta el nuevo umbral |

---

## 4. Flujos e2e completos (smoke de lanzamiento)

1. **Paciente feliz**: registro → reserva (pago 100% Culqi mock) → check-in móvil → triaje → diagnóstico → historial con PDF.
2. **Abono 50%**: reserva con abono → check-in presencial con turno → saldo en caja → atención.
3. **Doble reserva**: dos reservas simultáneas → 409 + alternativas → reserva con alternativa.
4. **Lista de espera**: inscripción → oferta → confirmar (crea cita) → cobro en recepción.
5. **Cancelación tardía**: cita a <12 h → aviso → cancelar → reembolso (si pagó) → auditoría.
6. **Cola + TV**: 3 pacientes en cola → llamar/finalizar/atender → payload de TV correcto en cada paso (socket).

---

## 5. Datos de prueba

- **Seed base**: el mismo de `src/db/seed.ts` (replica `mock.js`): 9 usuarios, 5 pacientes, 8 médicos, 7 especialidades, 5 consultorios, citas del `2026-08-05` con turnos, pagos y lista de espera.
- **Factories** (`test/factories/`): `makeUser(role)`, `makePatient()`, `makeDoctor()`, `makeAppointment({status})`, `makePayment({paidType})` — evitan duplicar fixtures.
- **Culqi mock**: en test, el módulo de pagos usa un proveedor stub (`CulqiProvider` fake) que devuelve `order_xxx`/`charge_xxx` deterministas y puede disparar webhooks de prueba. En staging, sandbox real de Culqi.
- **Fecha de prueba**: `TODAY_TEST` configurable por env (`APP_TODAY`) para no depender de la fecha real.

---

## 6. Cobertura mínima y gate de CI

| Métrica | Umbral |
|---|---|
| Cobertura de líneas (backend, módulos de negocio: citas, pagos, cola, waitlist) | ≥ 80 % |
| Cobertura global backend | ≥ 70 % |
| Cobertura global frontend | ≥ 60 % |
| Casos de la sección 3 | 100 % presentes (no marcados `skip`) |
| e2e de flujos de la sección 4 | Deben pasar en CI antes de merge a `main` |

El gate falla si: tests fallan, cobertura menor al umbral, o lint da error. Ver `docs/DEVOPS.md` (pipeline).

---

## 7. Checklist manual de QA por rol (staging, previo a cada release)

- [ ] **Paciente**: registro (con DNI duplicado → error), reserva 50%/100%/caja, 409 con alternativas, check-in móvil, cancelación tardía, historial + PDF, lista de espera completa, mis pagos.
- [ ] **Médico**: agenda del día, disponibilidad sin solapamientos, atención + diagnóstico, historial con relación clínica (y 403 sin ella), ficha PDF.
- [ ] **Enfermería**: cola por tiempo de espera, triaje completo, historial del turno, tablero de cola.
- [ ] **Recepción**: agenda general, alta rápida, check-in con turno, cobros + saldo 50%, verificación de pagos declarados, cancelaciones.
- [ ] **Admin**: indicadores, usuarios (activar/desactivar), especialidades/consultorios, reportes exportados, auditoría real, configuración.
- [ ] **TV**: kiosko por consultorio, turnos en vivo, reconexión (apagar red 10 s), reloj y contador.
- [ ] **Mobile (≤480 px)**: drawer + bottom-nav, modales bottom-sheet, formularios completos.
- [ ] **Seguridad**: 403 por rol, login bloqueado tras 5 intentos, webhook con firma inválida rechazado, PDF con URL firmada.