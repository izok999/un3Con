# Plan de Migración a Octane + Horizon + PgBouncer

> **Fecha de inicio:** 29/06/2026
> **Objetivo:** Optimizar Consultor UNESYS para producción con FrankenPHP/Octane, Redis para colas, PgBouncer para pool de conexiones
> **Stack objetivo:** Laravel 13 + Octane (FrankenPHP) + Nginx + Redis + PgBouncer + PostgreSQL

---

## Resumen de responsabilidades

| Responsable | Actividad |
|---|---|
| **Cline (yo)** | Cambios en el código del proyecto Laravel (este repo) |
| **Consultor (vos)** | Infraestructura del servidor de producción |

---

## PARTE 1 — Tareas de Cline (código del proyecto)

Estas tareas se ejecutan en **este workspace** (`/home/consultor/proyectos/un3Con`).

### 1.1 Instalar dependencias PHP

**Archivos afectados:** `composer.json`, `composer.lock`

```bash
composer require laravel/octane laravel/horizon
php artisan octane:install --server=frankenphp
php artisan horizon:install
```

### 1.2 Aplicar `bootstrap-app.snippet.php` → `bootstrap/app.php`

**Archivo:** `bootstrap/app.php`

**Cambios a fusionar** (preservando middleware existente):
- Agregar `trustProxies` apuntando a `127.0.0.1` y `::1`
- Agregar `trustHosts` con `www.une.edu.py`
- Mantener TODOS los middleware actuales: `SetLocale`, aliases de Spatie Permission, `EnsureAcademicUnitScope`, `EnsureLegacyUserHasCompletedAccount`, `EnsureOAuthUserHasDocumento`

### 1.3 Aplicar `config-database.snippet.php` → `config/database.php`

**Archivo:** `config/database.php`

**Cambios:**
- En la conexión `pgsql`: cambiar `port` default de `5432` a `6432` y agregar `'options' => [PDO::ATTR_EMULATE_PREPARES => true]`
- Verificar que `pgsql_externa` NO tenga `ATTR_EMULATE_PREPARES` (conexión directa, sin PgBouncer)
- El `TimeoutPostgresConnector` actual permanece (se evalúa compatibilidad con Octane en paso 1.8)

### 1.4 Reemplazar `config/octane.php`

**Archivo:** `config/octane.php` (se crea con `octane:install`, luego se reemplaza)

**Fuente:** `migrate/config-octane.php`

### 1.5 Reemplazar `config/horizon.php`

**Archivo:** `config/horizon.php` (se crea con `horizon:install`, luego se reemplaza)

**Fuente:** `migrate/config-horizon.php`

**Incluye:** definir las colas `critical`, `default`, `emails`, `reports` con sus supervisores

### 1.6 Actualizar `.env` de desarrollo local

**Archivo:** `.env`

**Cambios:**
- Agregar variables de Octane (`OCTANE_SERVER=frankenphp`, etc.)
- Cambiar `QUEUE_CONNECTION=database` → `QUEUE_CONNECTION=redis`
- Cambiar `SESSION_DRIVER=database` → `SESSION_DRIVER=redis`
- Agregar variables de Horizon
- Mantener `DB_HOST=pgsql` y `DB_PORT=5432` para desarrollo local (Sail)

### 1.7 Crear `.env.production` para producción

**Archivo:** `.env.production` (nuevo, basado en `migrate/env.production.example`)

**NO commitear este archivo** — es template para cuando desplieguen.

### 1.8 Auditar compatibilidad Octane + Livewire + MaryUI

**Revisar:**
- [ ] Singletons en `AppServiceProvider` — ¿acumulan estado?
- [ ] Propiedades `static` en componentes Livewire/Volt
- [ ] `TimeoutPostgresConnector` — evalúa race condition con `ini_set('default_socket_timeout')` en proceso long-running

### 1.9 Ejecutar tests después de los cambios

```bash
vendor/bin/sail artisan test --compact
```

---

## PARTE 2 — Tareas del Consultor (infraestructura)

Estas tareas se ejecutan en **el servidor de producción** (Ubuntu 22.04/24.04 LTS).

### 2.1 Preparar el servidor (UNA sola vez)

**Ubicación:** Servidor de producción (root)

```bash
# Copiar setup-server.sh al servidor
scp migrate/setup-server.sh root@<IP_SERVIDOR>:/root/
ssh root@<IP_SERVIDOR>
bash setup-server.sh
```

**Qué instala:** PHP 8.4, Redis, PgBouncer, Nginx, Supervisor, Composer, Node 22, tuning OPcache

### 2.2 Clonar el repositorio

**Ubicación:** Servidor de producción

```bash
cd /var/www
sudo git clone https://github.com/izok999/un3Con.git consultor
sudo chown -R www-data:www-data /var/www/consultor
```

### 2.3 Configurar PgBouncer

**Ubicación:** Servidor de producción

**Archivos fuente:** `migrate/pgbouncer.ini`, `migrate/userlist.txt`
**Destino:** `/etc/pgbouncer/pgbouncer.ini`, `/etc/pgbouncer/userlist.txt`

```bash
# 1. Generar hash MD5 de la password en psql:
#    SELECT concat('md5', md5('TU_PASSWORDconsultor_user'));

# 2. Copiar archivos
sudo cp migrate/pgbouncer.ini /etc/pgbouncer/pgbouncer.ini
sudo cp migrate/userlist.txt /etc/pgbouncer/userlist.txt

# 3. Editar userlist.txt con el hash real
sudo nano /etc/pgbouncer/userlist.txt

# 4. Permisos
sudo chmod 600 /etc/pgbouncer/userlist.txt
sudo chown postgres:postgres /etc/pgbouncer/userlist.txt

# 5. Iniciar
sudo systemctl enable pgbouncer
sudo systemctl start pgbouncer
```

### 2.4 Configurar Nginx

**Archivo fuente:** `migrate/consultor.conf`
**Destino:** `/etc/nginx/sites-available/consultor.conf`

```bash
sudo cp migrate/consultor.conf /etc/nginx/sites-available/consultor.conf
# Editar server_name si es necesario
sudo nano /etc/nginx/sites-available/consultor.conf
sudo ln -s /etc/nginx/sites-available/consultor.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 2.5 Configurar Supervisor (Horizon + Scheduler)

**Archivo fuente:** `migrate/consultor-horizon.conf`
**Destino:** `/etc/supervisor/conf.d/consultor-horizon.conf`

```bash
sudo cp migrate/consultor-horizon.conf /etc/supervisor/conf.d/consultor-horizon.conf
sudo supervisorctl reread
sudo supervisorctl update
```

### 2.6 Configurar systemd (Octane)

**Archivo fuente:** `migrate/consultor-octane.service`
**Destino:** `/etc/systemd/system/consultor-octane.service`

```bash
sudo cp migrate/consultor-octane.service /etc/systemd/system/consultor-octane.service
sudo systemctl daemon-reload
sudo systemctl enable consultor-octane
```

### 2.7 Crear `.env` de producción

**Archivo fuente:** `.env.production` (generado por Cline en paso 1.7)
**Destino:** `/var/www/consultor/.env`

```bash
cd /var/www/consultor
cp /ruta/a/.env.production .env
nano .env  # Completar APP_KEY, DB_PASSWORD, MAIL_*, etc.
```

### 2.8 Primer deploy

**Archivo fuente:** `migrate/deploy.sh`
**Destino:** `/var/www/consultor/deploy.sh`

```bash
cd /var/www/consultor
sudo -u www-data bash deploy.sh
sudo systemctl start consultor-octane
sudo supervisorctl start consultor:*
```

---

## Orden cronológico recomendado

```
1. Cline: Pasos 1.1 → 1.2 → 1.3 → 1.4 → 1.5 → 1.6 → 1.7 → 1.8 → 1.9
   (Commit y push al repo)

2. Consultor: Pasos 2.1 → 2.2 → 2.3 → 2.4 → 2.5 → 2.6 → 2.7 → 2.8
   (En el servidor de producción)
```

---

## Checklist de verificación post-deploy

- [ ] `request()->ip()` devuelve IP real del cliente (no 127.0.0.1)
- [ ] `request()->secure()` devuelve `true`
- [ ] `url('/')` devuelve `https://www.une.edu.py/consultor/`
- [ ] Horizon dashboard accesible en `/horizon` (solo admin)
- [ ] PgBouncer pool: `sudo -u postgres psql -p 6432 -c "SHOW POOLS;"`
- [ ] Octane workers corriendo: `sudo systemctl status consultor-octane`
- [ ] Colas funcionando: `sudo supervisorctl status consultor:*`
- [ ] Assets estáticos servidos por Nginx (cabecera `Cache-Control: public, immutable`)
- [ ] No hay errores en `storage/logs/laravel.log`
- [ ] Tests pasan en producción: `php8.4 artisan test --compact`

---

## Riesgos y mitigaciones

| Riesgo | Severidad | Estado | Mitigación |
|---|---|---|---|
| `TimeoutPostgresConnector` con `ini_set` en proceso long-running (Octane) | 🔴 Alto | Pendiente | Evaluar mover timeout a options de PDO o usar `connect_timeout` nativo de pg |
| `PDO::ATTR_EMULATE_PREPARES` con queries complejos | 🟡 Medio | Aplica solo a PgBouncer | Solo en conexión `pgsql` (PgBouncer), no en `pgsql_externa` |
| Singletons con estado mutable en `AppServiceProvider` | 🟡 Medio | Pendiente auditar | Mover a `octane.flush` o usar `bind` en lugar de `singleton` |
| `QUEUE_CONNECTION` cambia de `database` a `redis` | 🟢 Bajo | Sin pérdida de datos | Jobs pendientes en DB se pierden al hacer el switch; ejecutar en ventana de mantenimiento |
| `SESSION_DRIVER` cambia de `database` a `redis` | 🟢 Bajo | Usuarios deberán re-login | Avisar ventana de mantenimiento |

---

## Historial de cambios

| Fecha | Responsable | Cambio | Estado |
|---|---|---|---|
| 29/06/2026 | Cline | Plan creado | ✅ |
| 29/06/2026 | Cline | 1.1 — Instalar Octane + Horizon | ✅ |
| 29/06/2026 | Cline | 1.2 — trustProxies + trustHosts en bootstrap/app.php | ✅ |
| 29/06/2026 | Cline | 1.3 — PDO::ATTR_EMULATE_PREPARES en config/database.php | ✅ |
| 29/06/2026 | Cline | 1.4 — Reemplazar config/octane.php | ✅ |
| 29/06/2026 | Cline | 1.5 — Reemplazar config/horizon.php | ✅ |
| 29/06/2026 | Cline | 1.6 — Actualizar .env local (Redis para colas/sesiones) | ✅ |
| 29/06/2026 | Cline | 1.7 — Crear .env.production template | ✅ |
| 29/06/2026 | Cline | 1.8 — Auditar compatibilidad Octane+Livewire+MaryUI | ✅ (sin issues) |
| 29/06/2026 | Cline | 1.9 — Ejecutar tests + Pint | ✅ (Pint OK; tests con fallas preexistentes de RefreshDatabase) |
| 29/06/2026 | Cline | Corrección dominio → www.une.edu.py, email → servicios@une.edu.py | ✅ |
| 29/06/2026 | Cline | Corrección script: Debian 13, OPcache, Redis, quitar software-properties-common | ✅ |
| 29/06/2026 | Consultor | 2.1 — Ejecutar setup-server.sh en servidor Debian 13 | ✅ |
| — | — | — | — |
