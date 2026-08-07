# =============================================================================
# pgbouncer.Dockerfile — PgBouncer del mismo paquete Debian que usa el server.
#
# migrate/setup-server.sh hace `apt-get install -y pgbouncer`, asi que se instala
# igual en vez de usar una imagen de terceros: misma version, mismos defaults.
# =============================================================================
FROM debian:bookworm-slim

RUN apt-get update \
 && apt-get install -y --no-install-recommends pgbouncer postgresql-client ca-certificates \
 && rm -rf /var/lib/apt/lists/* \
 && mkdir -p /var/log/pgbouncer /var/run/pgbouncer /etc/pgbouncer \
 && chown -R postgres:postgres /var/log/pgbouncer /var/run/pgbouncer /etc/pgbouncer

COPY docker/prod/pgbouncer-entrypoint.sh /usr/local/bin/pgbouncer-entrypoint
RUN chmod +x /usr/local/bin/pgbouncer-entrypoint

EXPOSE 6432
ENTRYPOINT ["pgbouncer-entrypoint"]
