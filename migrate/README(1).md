# Consultor — Guía de despliegue en producción

Stack: Laravel + Octane (FrankenPHP) + Nginx + Redis + PgBouncer + PostgreSQL

---

## Estructura de archivos

```
consultor-prod/
├── scripts/
│   ├── setup-server.sh          ← Ejecutar UNA sola vez para preparar el servidor
│   └── deploy.sh                ← Ejecutar en cada actualización de código
├── config/
│   ├── pgbouncer/
│   │   ├── pgbouncer.ini        → /etc/pgbouncer/pgbouncer.ini
│   │   └── userlist.txt         → /etc/pgbouncer/userlist.txt
│   ├── nginx/
│   │   └── consultor.conf       → /etc/nginx/sites-available/consultor.conf
│   ├── supervisor/
│   │   └── consultor-horizon.conf → /etc/supervisor/conf.d/consultor-horizon.conf
│   └── systemd/
│       └── consultor-octane.service → /etc/systemd/system/consultor-octane.service
└── .env.production.example      → /var/www/consultor/.env  (completar y renombrar)
```

---

## Orden de instalación (servidor nuevo)

### Paso 1 — Preparar el servidor (una sola vez)
```bash
sudo bash scripts/setup-server.sh
```

### Paso 2 — Clonar el repositorio
```bash
cd /var/www
sudo git clone https://github.com/tu-usuario/consultor.git consultor
sudo chown -R www-data:www-data /var/www/consultor
```

### Paso 3 — Configurar PgBouncer
```bash
# Obtener hash MD5 de la contraseña (ejecutar en psql):
# SELECT concat('md5', md5('TU_PASSWORDconsultor_user'));

sudo cp config/pgbouncer/pgbouncer.ini /etc/pgbouncer/pgbouncer.ini
sudo cp config/pgbouncer/userlist.txt  /etc/pgbouncer/userlist.txt
# Editar userlist.txt con el hash real:
sudo nano /etc/pgbouncer/userlist.txt
sudo chmod 600 /etc/pgbouncer/userlist.txt
sudo chown postgres:postgres /etc/pgbouncer/userlist.txt
sudo systemctl enable pgbouncer
sudo systemctl start pgbouncer
# Verificar:
psql -h 127.0.0.1 -p 6432 -U consultor_user -d consultor_db -c "SELECT 1;"
```

### Paso 4 — Configurar Nginx
```bash
sudo cp config/nginx/consultor.conf /etc/nginx/sites-available/consultor.conf
# Editar server_name con el dominio real:
sudo nano /etc/nginx/sites-available/consultor.conf
sudo ln -s /etc/nginx/sites-available/consultor.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### Paso 5 — Configurar Supervisor (Horizon + Scheduler)
```bash
sudo cp config/supervisor/consultor-horizon.conf /etc/supervisor/conf.d/consultor-horizon.conf
sudo supervisorctl reread
sudo supervisorctl update
# Verificar (aún no inicia porque el código no está listo):
sudo supervisorctl status
```

### Paso 6 — Configurar systemd (Octane)
```bash
sudo cp config/systemd/consultor-octane.service /etc/systemd/system/consultor-octane.service
sudo systemctl daemon-reload
sudo systemctl enable consultor-octane
# No iniciar todavía — el paso 7 lo hace
```

### Paso 7 — Primer deploy
```bash
cd /var/www/consultor
cp /ruta/a/.env.production.example .env
nano .env  # Completar APP_KEY, DB_PASSWORD, etc.
sudo -u www-data bash /path/to/scripts/deploy.sh
sudo systemctl start consultor-octane
sudo supervisorctl start consultor:*
```

---

## Deploys posteriores

```bash
cd /var/www/consultor
sudo -u www-data bash scripts/deploy.sh
# O si querés especificar rama:
sudo -u www-data bash scripts/deploy.sh develop
```

---

## Comandos de operación diaria

```bash
# Estado de todos los servicios
sudo systemctl status consultor-octane
sudo supervisorctl status

# Logs en tiempo real
sudo journalctl -u consultor-octane -f
tail -f /var/www/consultor/storage/logs/laravel.log
tail -f /var/www/consultor/storage/logs/horizon.log

# Reiniciar servicios manualmente
sudo systemctl restart consultor-octane
sudo supervisorctl restart consultor:*

# Estado de PgBouncer
sudo -u postgres psql -p 6432 -c "SHOW POOLS;"
sudo -u postgres psql -p 6432 -c "SHOW STATS;"

# Vaciar caché de Laravel
php8.4 artisan cache:clear
php8.4 artisan config:clear
```

---

## Notas importantes

### PgBouncer y prepared statements
Con `pool_mode=transaction`, los prepared statements con nombre fallan.
Agregar en `config/database.php` bajo la conexión `pgsql`:

```php
'options' => [
    PDO::ATTR_EMULATE_PREPARES => true,
],
```

### DB externa (10.10.254.252)
No pasa por PgBouncer. Configurar en `config/database.php` como segunda conexión:

```php
'pgsql_externa' => [
    'driver'   => 'pgsql',
    'host'     => env('DB_EXTERNA_HOST', '10.10.254.252'),
    'port'     => env('DB_EXTERNA_PORT', '5432'),
    'database' => env('DB_EXTERNA_DATABASE'),
    'username' => env('DB_EXTERNA_USERNAME'),
    'password' => env('DB_EXTERNA_PASSWORD'),
    'charset'  => 'utf8',
    'schema'   => 'public',
],
```

Usar en modelos/queries:
```php
DB::connection('pgsql_externa')->table('vw_alumnos_00')->get();
```

### Horizon dashboard
Proteger el acceso en `app/Providers/HorizonServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user) {
        return in_array($user->email, [
            'servicios@une.edu.py',
        ]);
    });
}
```

---

## Archivos de la carpeta `laravel/` — cambios en el proyecto

Estos no van al servidor sino **al código de la aplicación** (el repo).

| Archivo | Destino en el proyecto | Acción |
|---|---|---|
| `config-octane.php` | `config/octane.php` | Reemplazar tras `octane:install` |
| `config-database.snippet.php` | `config/database.php` | Aplicar los dos cambios indicados |
| `bootstrap-app.snippet.php` | `bootstrap/app.php` | Reemplazar el bloque `withMiddleware` |
| `config-horizon.php` | `config/horizon.php` | Reemplazar tras `horizon:install` |
| `octane-livewire-safety.php` | Referencia / code review | Leer antes de hacer deploy |

### Orden de aplicación en el proyecto

```bash
# 1. Instalar paquetes (si no están)
composer require laravel/octane laravel/horizon
php artisan octane:install --server=frankenphp
php artisan horizon:install

# 2. Reemplazar configs con los de laravel/
cp laravel/config-octane.php config/octane.php
cp laravel/config-horizon.php config/horizon.php

# 3. Editar manualmente (no reemplazar completo):
#    config/database.php   → aplicar snippet de config-database.snippet.php
#    bootstrap/app.php     → aplicar snippet de bootstrap-app.snippet.php

# 4. Commitear todo
git add config/octane.php config/horizon.php config/database.php bootstrap/app.php
git commit -m "chore: production stack config (Octane, Horizon, PgBouncer, TrustProxies)"
git push origin main
```
