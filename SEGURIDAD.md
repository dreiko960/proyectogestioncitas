# SGCM-CMAS · Seguridad y cumplimiento (Ley N.º 29733)

Plan de seguridad del SGCM-CMAS y checklist de cumplimiento de la **Ley N.º 29733 (Protección de Datos Personales, Perú)**. Complementa `docs/BACKEND.md` (Parte 11) y `docs/FRONTEND.md` (Parte 13) con el detalle de controles y el checklist pre-producción.

---

## 1. Modelo de amenazas (resumen)

| Activo | Amenazas principales |
|---|---|
| Datos personales y clínicos de pacientes (DNI, dirección, historial, triaje) | Acceso no autorizado, fuga por endpoint mal protegido, robo de BD |
| Citas y horarios | Doble reserva (integridad), manipulación de estados |
| Pagos (Culqi) | Fraude, replay de webhooks, uso de claves expuestas |
| Cuentas (5 roles) | Suplantación, fuerza bruta, robo de tokens |
| TV / cola | Acceso no autorizado al panel, spoofing de turnos |

---

## 2. Cumplimiento Ley N.º 29733

| Requisito de la ley | Implementación en SGCM-CMAS | Evidencia / trazabilidad |
|---|---|---|
| **Consentimiento previo, expreso e informado** | Checkbox obligatorio en `/registro`; se guarda `consent_29733 = true` y `consent_at` | `patients.consent_29733`, `consent_at` (BACKEND Parte 2); `Register.jsx` (FRONTEND) |
| **Finalidad determinada** | Los datos se recogen solo para agendar/atender citas y emitir documentos | Política de privacidad en el registro y footer |
| **Principio de minimización** | Solo los campos necesarios por rol; el historial completo solo al médico con relación clínica | Relación clínica (RF-HIS-02) |
| **Derecho de acceso** | El paciente puede descargar su historial clínico completo (PDF) | `GET /documents/historial` |
| **Derecho de rectificación** | Edición de datos personales en `/paciente/perfil` con validación | `PATCH /patients/me` |
| **Derecho de cancelación** | Baja/desactivación de cuenta (admin) con anonimización opcional | `PATCH /users/:id/activate` |
| **Deber de confidencialidad y seguridad** | Cifrado en reposo + TLS + control de accesos + auditoría de accesos al historial | secciones 3-6 de este documento |
| **Registro de accesos** | `audit_log` registra cada acceso al historial, intentos de login y acciones sensibles con usuario, IP y fecha reales | `audit_log` (BACKEND Parte 2), `/audit` |
| **Notificación de incidentes** | Procedimiento de respuesta (sección 8); registro de incidentes para reporte a la autoridad si aplica | Runbook de incidentes |
| **Encargado de tratamiento / proveedores** | Culqi (tokenización, sin datos de tarjeta en nuestro sistema), RENIEC, S3 — con acuerdos de encargado y zonas de datos | Contratos con proveedores |

> El prototipo ya menciona la Ley en `Register.jsx`; la implementación de producción la materializa en datos y controles.

---

## 3. Autenticación y autorización

| Control | Detalle |
|---|---|
| Contraseñas | `bcrypt` (cost 12); política: ≥ 6, mayúscula, número; sin almacenamiento en claro |
| Access token | JWT 15 min, en memoria del navegador (no persistido) |
| Refresh token | 30 días, **hash en BD**, rotación en cada uso, revocación en logout y ante reuso |
| Bloqueo de fuerza bruta | Throttler: 5 intentos/min por email+IP; alarma en auditoría (`warning`) |
| Roles | `@Roles` en backend (nunca confiar en el frontend) + guard de UX en rutas |
| Relación clínica | Un médico solo accede a historiales de pacientes con citas previas; 403 + auditoría `Acceso denegado` (`danger`) |
| TV | Token de solo lectura (`aud: 'tv'`), sin privilegios de panel, expira y se renueva con la clave de consultorio |
| CORS | Solo `FRONTEND_URL`; cookies de refresh con `HttpOnly` + `SameSite=Lax` |

---

## 4. Protección de datos en reposo

| Campo sensible | Control |
|---|---|
| `patients.dni`, `patients.address` | **AES-256-GCM** con `DATA_ENC_KEY` (32 bytes), cifrados en la capa de aplicación antes de persistir |
| Datos clínicos (triage, diagnóstico) | Cifrado opcional según política del centro; al menos cifrado de copias de seguridad |
| Backups | Cifrados (AES-256) y restringidos por IAM/roles |
| Logs | Nunca registrar DNI, tarjeta, claves ni datos clínicos en claro |

**Gestión de claves:** `DATA_ENC_KEY`, `JWT_*`, `CULQI_*` viven en el gestor de secretos del proveedor (GitHub Secrets / env del PaaS), con rotación programada (trimestral para JWT/Culqi) y escaneo de secretos en CI.

---

## 5. Pagos y PCI (con Culqi)

| Principio | Implementación |
|---|---|
| **Alcance PCI reducido** | Culqi tokeniza la tarjeta en el navegador (SAQ A); nuestro backend **nunca recibe PAN/CVV** |
| Claves | `pk_*` pública en frontend; `sk_*` solo en backend (env); rotación y revocación en panel Culqi |
| Webhooks | Verificación de **firma** (`CULQI_WEBHOOK_SECRET`) antes de procesar; procesamiento **idempotente** por `culqi_order_id` |
| Errores de pago | No revelar detalles internos de Culqi al usuario; mensaje genérico + soporte con `request-id` |
| Reembolsos | Solo vía API con `charge_id`; registrado en auditoría (`Pago reembolsado`) |
| Replay de webhooks | Rechazo de eventos duplicados/antiguos (timestamp window) |

---

## 6. OWASP Top 10 aplicado

| # | Riesgo | Mitigación SGCM-CMAS |
|---|---|---|
| A01 | Broken Access Control | Guards por rol + relación clínica + TV de solo lectura; pruebas e2e de 403 |
| A02 | Cryptographic Failures | TLS 1.2+, cifrado en reposo, hashes bcrypt, sin almacenar datos sensibles en claro |
| A03 | Injection | Eloquent con bindings, `FormRequest` con validación, sin SQL concatenado |
| A04 | Insecure Design | Transacciones anti doble reserva, máquina de estados de cita estricta (sin saltos) |
| A05 | Security Misconfiguration | Middleware de headers (CSP/HSTS), CORS restringido, sin `APP_DEBUG=true` en prod, Scribe/docs deshabilitada en prod |
| A06 | Vulnerable Components | `npm audit` en CI bloquea vulnerabilidades altas/críticas; imágenes fijas (no `latest`) |
| A07 | Auth failures | JWT corto + refresh rotativo, bloqueo por intentos, logout revoca |
| A08 | Data Integrity Failures | Firma de webhooks, idempotencia de pagos, auditoría de cambios de estado |
| A09 | Logging failures | Logs estructurados con `request-id`, alertas de 5xx, sin datos sensibles en logs |
| A10 | SSRF | Las integraciones externas (Culqi/RENIEC) apuntan a dominios fijos permitidos |

---

## 7. Headers y config de despliegue

```yaml
# Netlify (_headers / netlify.toml)
/:
  Content-Security-Policy: "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; connect-src 'self' https://api.cmas.pe wss://api.cmas.pe https://js.culqi.com"
  X-Frame-Options: "DENY"
  Referrer-Policy: "strict-origin-when-cross-origin"
  Strict-Transport-Security: "max-age=31536000; includeSubDomains"
/tv:
  X-Frame-Options: "SAMEORIGIN"   # si se embebe en kiosko interno
```

Backend: middleware de headers (CSP base, HSTS, `X-Frame-Options`), CORS `FRONTEND_URL`, `APP_DEBUG=false` en prod, Scribe deshabilitado en prod, puerto y red por defecto seguros.

---

## 8. Respuesta a incidentes (runbook abreviado)

| Severidad | Ejemplo | Acción |
|---|---|---|
| **Crítica** | Sospecha de fuga de datos o `sk_*` comprometida | Revocar claves, rotar `DATA_ENC_KEY` si aplica, pausar cobros, revisar `audit_log` por accesos anómalos, notificar a dirección y a la autoridad (Ley 29733) |
| **Alta** | Cuenta administrador comprometida | Revocar refresh tokens del usuario, resetear clave, revisar auditoría de los últimos 7 días |
| **Media** | Webhooks Culqi fallando en cadena | Verificar firma/claves, reintentar conciliación, alertar al equipo |
| **Baja** | 429 masivos por rate limit | Revisar bots/IPs, ajustar umbrales, comunicar si afecta a pacientes |

**Registro de incidentes:** cada incidente se documenta (fecha, detección, impacto, acciones, lecciones) y se reporta si la ley lo exige.

---

## 9. Checklist pre-producción (gate de lanzamiento)

- [ ] TLS activo en todos los dominios; HSTS configurado.
- [ ] CORS solo a `FRONTEND_URL`; Swagger/API docs **deshabilitadas** en prod.
- [ ] `APP_ENV=production` y `APP_DEBUG=false`; logs sin datos sensibles.
- [ ] Secretos rotados y **no** presentes en el repo (escaneo CI sin hallazgos).
- [ ] `@Roles` verificados en todos los endpoints sensibles (citas, pagos, triaje, cola, historial, admin).
- [ ] Relación clínica probada (403 + auditoría) en historial y ficha del médico.
- [ ] Webhooks Culqi con verificación de firma e idempotencia probados (PRUEBAS.md §3.3).
- [ ] Cifrado en reposo activo para DNI/dirección y backups.
- [ ] Consentimiento 29733 obligatorio y registrado en registro público.
- [ ] Rate limiting activo (login 5/min, registro 3/h).
- [ ] Auditoría persistente con IP y fechas reales; `/audit` consultable por admin.
- [ ] Monitor externo (uptime) + alertas de 5xx y webhooks configurados.
- [ ] Backups cifrados verificados con una restauración de prueba en staging.
- [ ] Política de privacidad accesible desde el registro y el footer.