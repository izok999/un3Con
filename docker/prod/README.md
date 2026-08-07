# Espejo de produccion

Stack Docker que reproduce el servidor real descrito en [`migrate/`](../../migrate/),
para poder hacer pruebas de carga con numeros transferibles.

**No reemplaza a Sail.** [`compose.yaml`](../../compose.yaml) sigue siendo el entorno de
desarrollo; este es `compose.prod-mirror.yaml` y vive aparte.

```
Nginx :80 → Octane/FrankenPHP :8000 → PgBouncer :6432 → PostgreSQL :5432
                                    → Redis :6379
                                    → legacy 10.10.254.252:5432 (DIRECTO)
Horizon + Scheduler bajo Supervisor
```

Nginx, Octane y el generador de carga comparten un network namespace (el
container `netns`), asi que conviven en el mismo loopback igual que en el server
real y `consultor.conf` se usa sin editar.

El holder existe por un detalle de Docker: `network_mode: service:app` se
resuelve **una sola vez**, al crear el container. Cuando `app` se reinicia
obtiene un namespace nuevo y Nginx queda huerfano en el viejo — sigue figurando
"Up" pero escucha en una red muerta y todo da connection refused. El holder no
se reinicia nunca, asi que el namespace es estable.

## Arranque

```bash
cp .env.prod-mirror.example .env.prod-mirror
# completar DB_PASSWORD, DB_EXTERNA_PASSWORD, y los recursos (ver abajo)

docker compose -f compose.prod-mirror.yaml --env-file .env.prod-mirror up -d --build

# APP_KEY, una sola vez
docker compose -f compose.prod-mirror.yaml --env-file .env.prod-mirror \
  run --rm app php artisan key:generate --show
```

La app queda en `http://localhost:8080`. **Siempre con `Host: www.une.edu.py`** — el
`trustHosts` de [`bootstrap/app.php`](../../bootstrap/app.php#L37-L41) rechaza cualquier otro:

```bash
curl -H 'Host: www.une.edu.py' http://localhost:8080/up
```

## Calibrar los recursos — hacer esto antes de medir nada

Sacar del server real y volcar en `.env.prod-mirror`:

```bash
nproc                                   # → MIRROR_APP_CPUSET
free -g                                 # → MIRROR_APP_MEM
systemctl status consultor-octane       # → cuantos workers levanta de verdad
psql -c 'SELECT version();'             # → POSTGRES_VERSION
```

**Por que `cpuset` y no `cpus:`**: `cpus: 4.0` es una cuota CFS y no cambia lo que
reporta `nproc`. Con `--workers=auto`, Octane cuenta los cores del *host* — en una
maquina de 16 cores levantarias 16 workers peleandose por una cuota de 4, que no se
parece en nada a produccion. `cpuset` pinea cores reales y si cambia los cores
visibles. Aun asi, lo mas seguro es fijar `OCTANE_WORKERS` al numero del server.

El mismo razonamiento aplica al `default_pool_size = 25` de
[`pgbouncer.ini`](../../migrate/pgbouncer.ini) (formula cpu×4): ese valor es
estatico y se copia tal cual, pero solo tiene sentido si el CPU coincide.

## Que se monta sin editar

Los `.conf` de `migrate/` se montan read-only y se usan textuales. Asi el espejo
prueba los archivos que realmente van al server, y cualquier fix se commitea y
sirve para produccion.

| Archivo | Deltas |
|---|---|
| [`consultor.conf`](../../migrate/consultor.conf) | ninguno — Nginx comparte el network namespace de `app`, asi que `upstream 127.0.0.1:8000` funciona literal |
| [`consultor-horizon.conf`](../../migrate/consultor-horizon.conf) | ninguno — la imagen trae un symlink `php8.4 → php` y usa `/var/www/consultor` |
| [`pgbouncer.ini`](../../migrate/pgbouncer.ini) | solo red: `host=`, `listen_addr`, `logfile`. Se imprimen al arrancar |

`consultor-octane.service` no se monta (systemd no corre en el container) pero sus
flags se replican uno a uno en [`entrypoint.sh`](entrypoint.sh).

Deltas de entorno: todos marcados con `[DELTA]` en `.env.prod-mirror.example`. El
unico que cambia comportamiento de la app es `SESSION_SECURE_COOKIE=false`,
necesario porque el espejo sirve HTTP plano y con `true` el cliente nunca devuelve
la cookie de sesion.

## Bugs de produccion que el espejo expuso

### 1. `GET /` devolvia 404 — ARREGLADO

La portada del sitio no llegaba nunca a Octane. El `location /` tenia:

```nginx
try_files $uri $uri/ @octane;
```

Para `GET /`, el `$uri/` hace que nginx encuentre el directorio raiz, la
directiva `index index.php` lo resuelve a `/index.php`, y eso cae en
`location ~ \.php$ { return 404; }`. Verificado en el espejo: Octane directo en
`127.0.0.1:8000` devolvia `302 → /login` y a traves de Nginx `404`.

Arreglado en [`consultor.conf`](../../migrate/consultor.conf#L109-L115) sacando
`$uri/`. **El fix esta en el repo pero no en el server** — se aplica recopiando
el archivo a `/etc/nginx/sites-available/` y recargando nginx.

### 2. El rate limiting de Nginx es global, no por usuario

En [`consultor.conf:37-38`](../../migrate/consultor.conf#L37-L38) las directivas
`real_ip` estan comentadas. Como `cloudflared` corre en el mismo host que Nginx,
`$remote_addr` es `127.0.0.1` para **todo** el trafico. Entonces:

```nginx
limit_req_zone $binary_remote_addr zone=login_limit:10m rate=10r/m;
```

es un unico balde compartido: **10 logins por minuto para toda la universidad**.
Con `burst=5 nodelay`, el usuario 16 de ese minuto come 503. Lo mismo con
`api_limit` a 60r/m.

Ojo con el matiz: [`bootstrap/app.php`](../../bootstrap/app.php#L24-L34) tiene el
`trustProxies` bien puesto, asi que los throttles a nivel Laravel **si** funcionan
por IP real. El roto es solo la capa Nginx.

El espejo lo reproduce sin querer (todo el trafico entra desde la IP del generador
de carga). Para comparar las dos configuraciones:

```bash
MIRROR_FIX_REAL_IP=true docker compose -f compose.prod-mirror.yaml \
  --env-file .env.prod-mirror up -d nginx
```

Verificado en el espejo. 15 requests seguidos a `/login` desde **un solo**
cliente:

```
200 200 200 200 200 200 503 503 503 503 503 503 503 503 503
```

Pasan 6 (1 + `burst=5`) y el resto rebota. Con `MIRROR_FIX_REAL_IP=true` y un
`X-Forwarded-For` distinto por request, los 15 dan 200.

El fix real en produccion es descomentar esas dos lineas con
`real_ip_header CF-Connecting-IP` y `set_real_ip_from` apuntando al rango del tunnel.

### 3. PgBouncer se queda sin file descriptors antes del limite configurado

Al arrancar avisa:

```
kernel file descriptor limit: 1024 (hard: 524288); max_client_conn: 1000, max expected fd use: 1062
```

Con `max_client_conn=1000` necesita ~1062 descriptores y el default es 1024. El
`.ini` no puede arreglarlo: hace falta `LimitNOFILE` en la unit de systemd de
pgbouncer. **Verificar en el server** con
`systemctl show pgbouncer -p LimitNOFILE`. En el espejo se resuelve con el
`ulimits:` del compose.

### 4. `default_pool_size` lo comparten Octane y Horizon

Los 25 del pool se reparten entre los workers de Octane **y** los de Horizon
(`config-horizon.php` escala hasta 6 + 3). Bajo carga, mirar:

```bash
docker compose -f compose.prod-mirror.yaml --env-file .env.prod-mirror \
  exec -e PGPASSWORD=<tu_password> pgbouncer \
  psql -h 127.0.0.1 -p 6432 -U consultor_user pgbouncer -c 'SHOW POOLS;'
```

Si `cl_waiting` sube, el pool es el cuello de botella y no la app.

## Prueba de carga

> La legacy `10.10.254.252` es **productiva**. La conexion NO pasa por PgBouncer.

Lo que **no** es riesgo: agotar conexiones. Laravel mantiene un PDO por worker de
Octane, asi que la cantidad de conexiones contra la legacy es igual a la cantidad de
workers (4-8), sin importar la concurrencia. Y `usr_alu_web` es de solo lectura.

Lo que **si** es riesgo: saturarle CPU/IO por volumen de queries. Por eso
[`load.js`](loadtest/load.js) se controla por RPS y no por VUs — `LOAD_RPS` es
literalmente cuantas queries por segundo le mandas.

```bash
# 1. Smoke primero. Un usuario, 10 iteraciones. No hace falta avisar a nadie.
LOAD_STAGE=smoke docker compose -f compose.prod-mirror.yaml \
  --env-file .env.prod-mirror --profile loadtest run --rm k6 run /scripts/load.js

# 2. Ramp, para encontrar el codo. Coordinar ventana.
LOAD_STAGE=ramp LOAD_RPS=20 ...

# 3. Sustained. Solo con ventana confirmada.
LOAD_STAGE=sustained LOAD_RPS=20 LOAD_DURATION=5m ...
```

El script tiene thresholds con `abortOnFail`: si el p95 pasa de 3s o el error rate
del 10%, corta solo en vez de seguir golpeando la legacy.

Necesita `LOAD_USER_EMAIL` / `LOAD_USER_PASSWORD` de un alumno con correo verificado:
todo lo que consulta la legacy esta detras de `auth` + `verified` + `role:Alumno`.

**Limitacion conocida**: `/login` es un componente Volt, asi que el script tiene que
hablar el protocolo de Livewire (leer `wire:snapshot` y postear a `/livewire/update`).
Si cambia el componente, se rompe — falla ruidoso, no en silencio. La alternativa
robusta es una ruta de login solo-para-tests detras de un flag de entorno; no la
agregue porque implica tocar codigo de la app.

## Operacion

```bash
# logs
docker compose -f compose.prod-mirror.yaml --env-file .env.prod-mirror logs -f app

# Horizon
docker compose -f compose.prod-mirror.yaml --env-file .env.prod-mirror \
  exec worker supervisorctl status consultor:*

# artisan / tinker
docker compose -f compose.prod-mirror.yaml --env-file .env.prod-mirror \
  run --rm app php artisan tinker

# reset completo (borra los volumenes)
docker compose -f compose.prod-mirror.yaml --env-file .env.prod-mirror down -v
```

Despues de cambiar codigo hay que reconstruir: la imagen hace `COPY` en vez de bind
mount, porque con `opcache.validate_timestamps=0` un bind mount no te aporta nada y
overlayfs se parece mas al disco del server.

```bash
docker compose -f compose.prod-mirror.yaml --env-file .env.prod-mirror up -d --build app worker
```
