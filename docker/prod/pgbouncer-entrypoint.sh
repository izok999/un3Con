#!/bin/sh
# =============================================================================
# pgbouncer-entrypoint.sh — carga migrate/pgbouncer.ini en el espejo.
#
# El .ini se monta read-only y se copia. Los unicos cambios son de red: el
# archivo de produccion asume que PgBouncer, PostgreSQL y Octane comparten el
# loopback de un mismo host, y aca viven en containers distintos.
#
# Los parametros que importan para la prueba de carga (pool_mode=transaction,
# max_client_conn=1000, default_pool_size=25, reserve_pool_size=5, los timeouts)
# quedan EXACTAMENTE como en produccion. Cada delta se imprime al arrancar.
# =============================================================================
set -eu

SRC="${MIRROR_PGBOUNCER_INI:-/etc/pgbouncer/pgbouncer.ini.src}"
DST=/etc/pgbouncer/pgbouncer.ini

PG_HOST="${POSTGRES_HOST:-postgres}"
PG_PORT="${POSTGRES_PORT:-5432}"
DB_NAME="${DB_DATABASE:-consultor_db}"
DB_USER="${DB_USERNAME:?falta DB_USERNAME}"
DB_PASS="${DB_PASSWORD:?falta DB_PASSWORD}"

cp "${SRC}" "${DST}"

echo "=== Deltas aplicados a pgbouncer.ini (solo red) ==="

# Delta 1 — el [databases] apunta a host=127.0.0.1, que aca seria el propio
# container de PgBouncer.
sed -i "s|^\(${DB_NAME} *= *host=\)127\.0\.0\.1\( *port=\)5432|\1${PG_HOST}\2${PG_PORT}|" "${DST}"
echo "  [databases] host=127.0.0.1 -> host=${PG_HOST}:${PG_PORT}"

# Delta 2 — listen_addr=127.0.0.1 solo aceptaria conexiones desde el propio
# container; Octane vive en otro.
sed -i 's|^listen_addr *= *127\.0\.0\.1|listen_addr = 0.0.0.0|' "${DST}"
echo "  listen_addr 127.0.0.1 -> 0.0.0.0"

# Delta 3 — sin logfile, PgBouncer escribe a stderr y lo levanta
# `docker compose logs`. No sirve apuntar logfile a /dev/stdout: el proceso baja
# a usuario postgres y /dev/stdout pertenece a root, asi que abrirlo da
# "Permission denied" y PgBouncer aborta.
# En el server real el logfile va a /var/log/pgbouncer/pgbouncer.log.
if [ "${MIRROR_PGBOUNCER_LOG_STDOUT:-true}" = "true" ]; then
    sed -i 's|^logfile *=.*|logfile =|' "${DST}"
    echo "  logfile -> stderr (vacio)"
fi

# Verbosidad extra: imprescindible para ver saturacion del pool bajo carga.
if [ -n "${MIRROR_PGBOUNCER_VERBOSE:-}" ]; then
    echo "verbose = ${MIRROR_PGBOUNCER_VERBOSE}" >> "${DST}"
    echo "  verbose = ${MIRROR_PGBOUNCER_VERBOSE} (agregado, no existe en produccion)"
fi
echo "==================================================="

# -----------------------------------------------------------------------------
# userlist.txt — migrate/userlist.txt es una plantilla con el hash sin completar.
# Se genera con la password en texto plano, que es la segunda opcion que el
# propio archivo documenta. Ademas de simplificar, permite que PgBouncer negocie
# scram-sha-256 contra PostgreSQL; con solo el hash md5 no podria, y PostgreSQL
# 15+ usa scram por defecto.
#
# Esto es aceptable en el espejo. En produccion usá el hash md5.
# -----------------------------------------------------------------------------
printf '"%s" "%s"\n' "${DB_USER}" "${DB_PASS}" > /etc/pgbouncer/userlist.txt
chown postgres:postgres /etc/pgbouncer/userlist.txt "${DST}"
chmod 600 /etc/pgbouncer/userlist.txt

echo "[→] Esperando PostgreSQL en ${PG_HOST}:${PG_PORT}..."
until pg_isready -h "${PG_HOST}" -p "${PG_PORT}" -U "${DB_USER}" -q; do
    sleep 1
done
echo "[✔] PostgreSQL responde"

exec su -s /bin/sh postgres -c "exec pgbouncer '${DST}'"
