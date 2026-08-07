#!/usr/bin/env bash
# =============================================================================
# entrypoint.sh — arranque de los roles `app` y `worker` del espejo.
#
# Reproduce los pasos 5 y 6 de migrate/deploy.sh (migraciones + cachés de
# Laravel) porque en el server real esos comandos corren despues de que el .env
# esta en su lugar, no cuando se construye el artefacto.
#
# Roles:
#   app     → artisan octane:start  (equivale a consultor-octane.service)
#   worker  → supervisord           (equivale a consultor-horizon.conf)
#   <otro>  → se ejecuta tal cual (ej: entrypoint php artisan tinker)
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/consultor}"
ROLE="${1:-app}"
cd "${APP_DIR}"

CYAN='\033[0;36m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
step() { echo -e "${CYAN}[→]${NC} $1"; }
log()  { echo -e "${GREEN}[✔]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }

as_app() { gosu www-data "$@"; }

# -----------------------------------------------------------------------------
# Esperar a las dependencias. En el server real systemd resuelve esto con
# After=/Wants=; aca lo hacemos a mano.
# -----------------------------------------------------------------------------
wait_for() {
    local host="$1" port="$2" label="$3" tries="${4:-60}"
    step "Esperando ${label} (${host}:${port})..."
    for _ in $(seq 1 "${tries}"); do
        if (echo > "/dev/tcp/${host}/${port}") >/dev/null 2>&1; then
            log "${label} responde"
            return 0
        fi
        sleep 1
    done
    warn "${label} no respondio en ${tries}s — se sigue igual"
    return 0
}

# Los comandos sueltos (artisan, tinker, sh) no esperan a nadie ni tocan las
# cachés: se ejecutan y listo.
if [[ "${ROLE}" != "app" && "${ROLE}" != "worker" ]]; then
    exec "$@"
fi

wait_for "${DB_HOST:-pgbouncer}" "${DB_PORT:-6432}" "PgBouncer"
wait_for "${REDIS_HOST:-redis}"  "${REDIS_PORT:-6379}" "Redis"

# La legacy solo se chequea, nunca se bloquea el arranque por ella.
if [[ -n "${DB_EXTERNA_HOST:-}" ]]; then
    if (echo > "/dev/tcp/${DB_EXTERNA_HOST}/${DB_EXTERNA_PORT:-5432}") >/dev/null 2>&1; then
        log "Legacy ${DB_EXTERNA_HOST}:${DB_EXTERNA_PORT:-5432} alcanzable"
    else
        warn "Legacy ${DB_EXTERNA_HOST}:${DB_EXTERNA_PORT:-5432} NO alcanzable desde el container"
        warn "Revisa el routing del host hacia esa red antes de correr las pruebas"
    fi
fi

# -----------------------------------------------------------------------------
# Solo el rol `app` migra y publica los assets. El worker no toca el esquema,
# igual que en el server donde deploy.sh corre una sola vez.
# -----------------------------------------------------------------------------
if [[ "${ROLE}" == "app" ]]; then
    if [[ "${MIRROR_RUN_MIGRATIONS:-true}" == "true" ]]; then
        step "Migraciones..."
        as_app php artisan migrate --force
        log "Migraciones aplicadas"
    fi

    # Nginx sirve /var/www/consultor/public por su cuenta (assets estaticos con
    # Cache-Control immutable). Como corre en otro container, le pasamos el
    # directorio por un volumen compartido.
    if [[ -d "${MIRROR_PUBLIC_EXPORT:-}" ]]; then
        step "Publicando public/ para Nginx en ${MIRROR_PUBLIC_EXPORT}..."
        cp -a "${APP_DIR}/public/." "${MIRROR_PUBLIC_EXPORT}/"
        log "Assets publicados"
    fi
fi

# -----------------------------------------------------------------------------
# Cachés de Laravel — mismo orden que migrate/deploy.sh paso 6
# -----------------------------------------------------------------------------
step "Reconstruyendo cachés de Laravel..."
as_app php artisan config:clear
as_app php artisan route:clear
as_app php artisan view:clear
as_app php artisan event:clear

as_app php artisan config:cache
as_app php artisan route:cache
as_app php artisan view:cache
as_app php artisan event:cache
log "Cachés listas"

# -----------------------------------------------------------------------------
# Arranque segun rol
# -----------------------------------------------------------------------------
case "${ROLE}" in
    app)
        # Flags identicos a migrate/consultor-octane.service.
        #
        # ATENCION con --workers=auto: Octane cuenta los cores que ve el kernel,
        # y `cpus:` de Compose es una cuota CFS que NO cambia nproc. En un host
        # de 16 cores con cpus:4.0 levantarias 16 workers peleandose por 4 —
        # nada parecido a produccion. Por eso el compose usa cpuset (pin real) y
        # OCTANE_WORKERS deberia fijarse al numero que da el server.
        WORKERS="${OCTANE_WORKERS:-auto}"
        TASK_WORKERS="${OCTANE_TASK_WORKERS:-auto}"
        MAX_REQUESTS="${OCTANE_MAX_REQUESTS:-500}"
        OCTANE_HOST="${OCTANE_HOST:-127.0.0.1}"
        OCTANE_PORT="${OCTANE_PORT:-8000}"

        if [[ "${WORKERS}" == "auto" ]]; then
            warn "OCTANE_WORKERS=auto — Octane ve $(nproc) cores. Verificá que"
            warn "coincida con produccion o fijá OCTANE_WORKERS explicitamente."
        fi

        log "Octane: workers=${WORKERS} task-workers=${TASK_WORKERS} max-requests=${MAX_REQUESTS}"
        exec gosu www-data php artisan octane:start \
            --server=frankenphp \
            --host="${OCTANE_HOST}" \
            --port="${OCTANE_PORT}" \
            --workers="${WORKERS}" \
            --task-workers="${TASK_WORKERS}" \
            --max-requests="${MAX_REQUESTS}"
        ;;

    worker)
        # supervisord arranca como root y hace setuid a www-data segun el
        # `user=www-data` de consultor-horizon.conf — igual que en el server.
        log "Supervisor: Horizon + Scheduler"
        exec supervisord -n -c /etc/supervisor/supervisord.conf
        ;;
esac
