# SGCM-CMAS · DevOps — Ambientes, CI/CD, despliegue y operaciones

Plan de operaciones del SGCM-CMAS: ambientes, pipelines, contenedores, monitoreo, backups y recuperación. Complementa `docs/BACKEND.md` (Parte 12) y `docs/FRONTEND.md` (Parte 14) con el detalle operativo.

---

## 1. Ambientes

| Ambiente | URL | Backend | BD/Redis | Culqi | Seed | Propósito |
|---|---|---|---|---|---|---|
| **dev** | `http://localhost:5173` | `http://localhost:3000` | docker-compose local | `sk_test_*` | sí (mock) | Desarrollo diario |
| **test** | CI | CI efímero | contenedores de CI | stub | parcial | PRs y pipelines |
| **staging** | `staging.cmas.pe` | `api-staging.cmas.pe` | instancia staging | `sk_test_*` | sí + datos QA | Validación de integraciones y QA manual |
| **prod** | `cmas.pe` | `api.cmas.pe` | instancia prod (replicada) | `sk_live_*` | **no** | Operación real |

**Regla:** lo que no pasó por staging con QA (checklist de `docs/PRUEBAS.md` §7) no llega a producción.

---

## 2. Repositorio y estructura

Monorepo recomendado dentro de `proCitas/`:

```
proCitas/
├── backend/            # Laravel 12 (docs/BACKEND.md)
├── frontend/           # React/Vite (docs/FRONTEND.md) — o la raíz actual migrada
├── docs/               # documentación (README, MODULOS, BACKEND, FRONTEND, …)
├── .github/workflows/  # pipelines CI/CD
└── docker-compose.yml  # orquestación local y de staging
```

**Ramas** (ver `docs/ESTANDARES.md`): `main` (producción) → `staging` → `develop` → `feature/*`.

---

## 3. CI/CD (GitHub Actions)

### 3.1 Pipeline backend (`backend-ci.yml`)

| Paso | Comando | Gate |
|---|---|---|
| Lint | `vendor/bin/pint --test` | Errores bloquean |
| Unit + E2E | `php artisan test` (PHPUnit, BD+Redis de test, coverage) | Umbrales de `docs/PRUEBAS.md` §6; fallos bloquean |
| Build/optimización | `composer install --no-dev --optimize-autoloader` + `php artisan config:cache` | Error bloquea |
| Migraciones (solo `main`/`staging`) | `php artisan migrate --force` (job con BD objetivo + backup previo) | Lock de migraciones |
| Imagen + deploy | Docker build → registry → deploy (Render/Fly/VPS con watchtower) | Tras pasar todo |

### 3.2 Pipeline frontend (`frontend-ci.yml`)

| Paso | Comando | Gate |
|---|---|---|
| Lint + build | `npm run lint && npm.cmd run build` | Errores bloquean |
| Test | `npm run test` (Vitest + Testing Library) | Cobertura ≥ 60 % |
| Deploy preview | ramas `feature/*` → deploy preview Netlify (env staging) | Automático |
| Deploy staging | merge a `staging` → Netlify staging site | Automático |
| Deploy prod | merge a `main` → Netlify prod + **requiere aprobación manual** | Revisión |

### 3.3 Gate de seguridad en CI

- Escaneo de secretos en el repo (detect-secrets / gitleaks) en cada PR.
- `composer audit` (dependencias PHP) + `npm audit` (frontend): vulnerabilidades altas/críticas bloquean.
- Imágenes base con `alpine` y etiquetas fijas (no `latest`).

---

## 4. Contenedores (`docker-compose.yml`)

```yaml
services:
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: sgcm_cmas
      POSTGRES_USER: cmas
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes: [ pgdata:/var/lib/postgresql/data ]
    healthcheck: { test: ["CMD-SHELL", "pg_isready -U cmas"], interval: 5s, retries: 10 }

  redis:
    image: redis:7-alpine
    command: ["redis-server", "--appendonly", "yes"]
    healthcheck: { test: ["CMD", "redis-cli", "ping"], interval: 5s, retries: 10 }

  api:
    build: { context: ./backend }
    command: php artisan serve --host=0.0.0.0 --port=8000   # dev; prod: php-fpm/Octane + nginx
    ports: [ "8000:8000" ]
    environment: { DB_HOST: postgres, REDIS_HOST: redis, APP_ENV: production }
    depends_on:
      postgres: { condition: service_healthy }
      redis:    { condition: service_healthy }

  queue:
    build: { context: ./backend }
    command: php artisan queue:work redis --tries=3        # colas

  horizon:
    build: { context: ./backend }
    command: php artisan horizon                            # panel + workers (prod)

  scheduler:
    build: { context: ./backend }
    command: php artisan schedule:work                      # tareas programadas

  reverb:
    build: { context: ./backend }
    command: php artisan reverb:start                       # WebSockets (puerto 8080)
    ports: [ "8080:8080" ]

  # staging/prod adicionales:
  #   - reverse proxy (nginx/Caddy): TLS, proxy_api, proxy_ws, límites
  #   - migraciones: job de un solo uso `php artisan migrate --force`
```

**Requisito de escalado horizontal:** API stateless (tokens Sanctum + refresh en BD) y WebSockets con **Laravel Reverb** compartiendo eventos vía Redis para que la cola funcione con N instancias.

---

## 5. Monitoreo, logs y alertas

| Ámbito | Solución mínima |
|---|---|
| Logs estructurados | Interceptor con `request-id` (header `X-Request-Id`), nivel según `NODE_ENV`; los errores 4xx/5xx con detalle sanitizado |
| Métricas | `/metrics` (Prometheus) expuesto a un colector (Grafana opcional): latencia P95 por ruta, tasa de errores, webhooks fallidos, tamaño de colas (Horizon), uso de BD |
| Uptime | Health check público `GET /health` (BD + Redis) consumido por un monitor externo (UptimeRobot/Pingdom) con alerta a email/Slack |
| Alertas clave | ① Tasa de 5xx > 1 % en 5 min · ② Webhooks Culqi fallidos en cadena · ③ Cola de recordatorios con retraso > 15 min · ④ Espacio en disco de la BD > 80 % · ⑤ Certificado TLS a < 14 días de expirar |
| Rastreo de errores | Capturar stack en servicio de errores (Sentry) con `request-id` para correlacionar |

**Regla:** ningún error sensible (stack, SQL, tokens) llega a la respuesta HTTP; solo al log/monitoreo.

---

## 6. Backups y plan de recuperación

### 6.1 Estrategia

| Recurso | Estrategia | Retención |
|---|---|---|
| PostgreSQL | `pg_dump` diario (02:00, hora del servidor) + **WAL continuo** (PITR) | 30 días, backups cifrados (AES-256) |
| Redis | AOF + snapshot; datos regenerables (cache) | 7 días |
| S3 (PDFs) | Versionado de objetos | 90 días |
| Configuración | IaC (docker-compose + env vars en el proveedor) | — |

### 6.2 Plan de recuperación

| Métrica | Objetivo |
|---|---|
| **RPO** (pérdida máxima de datos) | ≤ 5 min (WAL) |
| **RTO** (tiempo de restauración) | ≤ 4 h (desastre total) |

**Procedimiento (probado mensualmente):**

1. Restaurar la BD en instancia limpia: `pg_restore` del último dump + replay de WAL hasta el punto deseado.
2. Levantar API + worker contra la BD restaurada (docker-compose).
3. Verificar con el smoke de `docs/PRUEBAS.md` §4 (flujo paciente feliz + cola TV).
4. Documentar fecha, duración y desviaciones del procedimiento en un log de restauraciones.

**Runbook de desastres menores:** caída de un nodo API (replicación automática), caída de Redis (regeneración de cache sin pérdida de datos — las sesiones se re-emiten), BD sin espacio (alerta + purga de WAL archivados viejos).

---

## 7. Despliegue de una versión (checklist)

1. [ ] PR a `staging` pasa CI completo (backend + frontend) con QA manual (`PRUEBAS.md` §7).
2. [ ] Migraciones revisadas y ejecutadas en staging con backup previo.
3. [ ] Variables de entorno de staging actualizadas (Culqi test, RENIEC test).
4. [ ] PR a `main` con aprobación manual + escaneo de secretos.
5. [ ] Migraciones en prod con **lock** (evitar dos deploys simultáneos).
6. [ ] Deploy de imagen API/worker y sitio frontend (Netlify).
7. [ ] Smoke post-deploy: `GET /health`, login real, una reserva de prueba, un evento de cola en TV.
8. [ ] Monitoreo: revisar errores/latencia 24 h; alertas configuradas.
9. [ ] Backups verificados post-migración.
10. [ ] Documentación actualizada (`README.md`, `docs/MODULOS.md` y docs afectadas — regla de `AGENTS.md`).

---

## 8. Costos e infraestructura de referencia (mínima viable)

| Recurso | Referencia |
|---|---|
| API + worker | 1 instancia 2 vCPU/4 GB (escala horizontal cuando el socket lo exija) |
| PostgreSQL | Instancia gestionada (RDS/Supabase/Neon) o VPS con replica en espera |
| Redis | Instancia gestionada o contenedor en el mismo VPS (AOF) |
| S3 | Bucket privado + CDN opcional para PDFs públicos temporales |
| Frontend | Netlify (gratis/pro) con deploy previews |
| Monitoreo | UptimeRobot + logs del proveedor (o Sentry gratuito) |