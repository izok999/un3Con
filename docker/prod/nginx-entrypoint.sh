#!/bin/sh
# =============================================================================
# nginx-entrypoint.sh — carga migrate/consultor.conf en el Nginx del espejo.
#
# El .conf se monta read-only y se copia sin editar a conf.d/. La imagen
# oficial de Nginx incluye `include /etc/nginx/conf.d/*.conf;` dentro del bloque
# http, que es el contexto que necesitan los limit_req_zone del archivo.
#
# El `upstream octane { server 127.0.0.1:8000; }` funciona tal cual porque este
# container comparte el network namespace del container `app`
# (network_mode: service:app en compose.prod-mirror.yaml), igual que en el
# server real donde Nginx y Octane conviven en el mismo loopback.
# =============================================================================
set -eu

SRC="${MIRROR_NGINX_CONF:-/etc/nginx/consultor.conf.src}"
DST=/etc/nginx/conf.d/consultor.conf

# setup-server.sh hace `rm -f /etc/nginx/sites-enabled/default`; el equivalente
# en la imagen oficial es sacar el default.conf de conf.d.
rm -f /etc/nginx/conf.d/default.conf

cp "${SRC}" "${DST}"

# -----------------------------------------------------------------------------
# Toggle para reproducir/verificar el bug de rate limiting de produccion.
#
# En migrate/consultor.conf las directivas real_ip estan comentadas. Como
# cloudflared corre en el mismo host que Nginx, $remote_addr es 127.0.0.1 para
# TODO el trafico, asi que `limit_req_zone $binary_remote_addr zone=login_limit
# rate=10r/m` es un unico balde global: 10 logins por minuto para toda la
# universidad, no por usuario.
#
# El espejo reproduce eso sin querer (todo el trafico entra desde la IP del
# generador de carga). Con MIRROR_FIX_REAL_IP=true se activa el fix para poder
# comparar las dos configuraciones en la misma corrida.
# -----------------------------------------------------------------------------
if [ "${MIRROR_FIX_REAL_IP:-false}" = "true" ]; then
    sed -i \
        -e 's|^\( *\)# real_ip_header CF-Connecting-IP;|\1real_ip_header X-Forwarded-For;\n\1real_ip_recursive on;|' \
        -e 's|^\( *\)# set_real_ip_from 127.0.0.1;.*|\1set_real_ip_from 0.0.0.0/0;|' \
        "${DST}"
    echo "[!] MIRROR_FIX_REAL_IP=true — rate limiting por X-Forwarded-For (fix aplicado)"
    grep -n 'real_ip' "${DST}" || true
else
    echo "[!] MIRROR_FIX_REAL_IP=false — rate limiting con \$remote_addr, igual que produccion hoy"
    echo "[!] Esperá 503 en /login apenas superes 10 req/min AGREGADAS entre todos los clientes"
fi

nginx -t
exec nginx -g 'daemon off;'
