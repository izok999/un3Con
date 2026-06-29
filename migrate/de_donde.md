Los 9 archivos están listos. Un resumen rápido de lo que tiene cada uno:

**Scripts**
- `setup-server.sh` — Corre una sola vez: instala PHP 8.4, Redis, PgBouncer, Nginx, Supervisor, Composer, Node 22, y tuning de OPcache
- `deploy.sh` — Cada actualización: `git pull` → `composer` → `npm build` → `migrate` → caché → reload graceful de Octane → restart Horizon

**Config**
- `pgbouncer.ini` — Solo la DB local, `transaction mode`, `max_client_conn=1000`, `pool_size=25`
- `userlist.txt` — Template con instrucciones para generar el hash MD5 desde psql
- `consultor.conf` — Nginx con rate limiting en `/login` y `/api`, assets estáticos con `immutable`, proxy a Octane con keepalive
- `consultor-horizon.conf` — Supervisor para Horizon + Scheduler como grupo `consultor:*`
- `consultor-octane.service` — systemd con `workers=auto`, `max-requests=500`, reload graceful vía `SIGUSR1`

**Entorno**
- `.env.production.example` — Todas las variables documentadas, con `DB_PORT=6432` (PgBouncer) para la local y `DB_EXTERNA_PORT=5432` (directo) para la externa

El `README(1).md` tiene el orden exacto de los 7 pasos para el primer deploy y los comandos de operación diaria. El único paso manual antes de correr `deploy.sh` es completar el `.env` y generar el hash para el `userlist.txt`.

Faltan los cambios del lado de Laravel — sin estos tres archivos el servidor funciona pero la app va a tener problemas. Voy con ellos:Los 5 archivos nuevos están listos. Resumen de lo que tienen:

**`config-database.snippet.php`** — Dos cambios críticos a `config/database.php`:
- Conexión `pgsql` apuntando a PgBouncer en `:6432` con `PDO::ATTR_EMULATE_PREPARES => true` (sin esto, Eloquent falla con _"prepared statement already exists"_)
- Conexión `pgsql_externa` apuntando directo a `10.10.254.252:5432` con el patrón para usarla en modelos y queries

**`bootstrap-app.snippet.php`** — `withMiddleware` con `trustProxies` y `trustHosts`. Sin esto `Request::ip()` siempre devuelve `127.0.0.1`, las URLs se generan con `http://` y los rate limiters no funcionan por IP real.

**`config-octane.php`** — Config completa de Octane/FrankenPHP con `warm`, `flush`, y los listeners del ciclo de vida. Incluye el comentario sobre `https=false` porque SSL termina en Cloudflare.

**`config-horizon.php`** — Dos supervisores: uno para colas `critical/default` (timeout 90s, escala hasta 6 workers) y otro para `emails/reports` (timeout 300s, escala hasta 3). Con las colas nombradas y el patrón para despachar a cada una.

**`octane-livewire-safety.php`** — Guía de patrones seguros e inseguros con Octane + Livewire 3. El riesgo principal son las propiedades `static` y los singletons con estado mutable — especialmente relevante al escalar con MaryUI.

El flujo completo quedó así: primero aplicás los cambios de `laravel/` al repo → commiteás → después configurás el servidor con `setup-server.sh` → copiás los configs de `config/` → corrés `deploy.sh`.
los archivos de de este mensaje son del README.md

