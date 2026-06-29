#!/bin/bash
# =============================================================================
# setup-server.sh — Instalación única del servidor para Consultor
# Ubuntu 22.04/24.04 LTS | Debian 12/13
# Ejecutar como root: sudo bash setup-server.sh
# =============================================================================
set -euo pipefail

APP_USER="www-data"
APP_DIR="/var/www/consultor"
PHP_VERSION="8.4"   # Actualizar a 8.5 cuando esté disponible en el PPA
NODE_VERSION="22"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
log()  { echo -e "${GREEN}[✔]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
die()  { echo -e "${RED}[✘]${NC} $1"; exit 1; }

[[ $EUID -ne 0 ]] && die "Ejecutar como root (sudo bash setup-server.sh)"

# =============================================================================
# 1. Sistema base
# =============================================================================
log "Actualizando sistema..."
apt-get update -qq
apt-get upgrade -y -qq
apt-get install -y -qq \
  curl wget git unzip zip gnupg2 ca-certificates lsb-release \
  apt-transport-https build-essential libpq-dev acl

# =============================================================================
# 2. PHP 8.4 + extensiones
# =============================================================================
log "Instalando PHP ${PHP_VERSION}..."

# En Ubuntu usamos el PPA de Ondřej, en Debian el repo de Sury
if command -v add-apt-repository &>/dev/null && grep -qi ubuntu /etc/os-release 2>/dev/null; then
    # Ubuntu: PPA de Ondřej
    add-apt-repository -y ppa:ondrej/php
else
    # Debian: repo de Sury (mismo mantenedor, distinto mecanismo)
    apt-get install -y -qq apt-transport-https lsb-release ca-certificates curl
    curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
    echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
        > /etc/apt/sources.list.d/php.list
fi
apt-get update -qq
apt-get install -y -qq \
  php${PHP_VERSION}-fpm \
  php${PHP_VERSION}-cli \
  php${PHP_VERSION}-pgsql \
  php${PHP_VERSION}-redis \
  php${PHP_VERSION}-opcache \
  php${PHP_VERSION}-mbstring \
  php${PHP_VERSION}-xml \
  php${PHP_VERSION}-bcmath \
  php${PHP_VERSION}-curl \
  php${PHP_VERSION}-zip \
  php${PHP_VERSION}-intl \
  php${PHP_VERSION}-gd \
  php${PHP_VERSION}-pcov

# pcntl y posix vienen incluidos en php-cli, verificar
php${PHP_VERSION} -r "if (!extension_loaded('pcntl')) die('WARN: pcntl no disponible\n');" || warn "pcntl no cargado — necesario para Octane"

# Tuning OPcache para CLI (Octane usa PHP CLI, no FPM)
cat > /etc/php/${PHP_VERSION}/cli/conf.d/99-opcache-prod.ini <<'EOF'
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
opcache.save_comments=1
EOF
# Octane usa PHP CLI — la config de FPM no es necesaria, pero por si acaso:
cat > /etc/php/${PHP_VERSION}/fpm/conf.d/99-opcache-prod.ini <<'EOF'
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=0
opcache.validate_timestamps=0
opcache.save_comments=1
EOF

log "PHP ${PHP_VERSION} instalado: $(php${PHP_VERSION} -r 'echo PHP_VERSION;')"

# =============================================================================
# 3. Composer
# =============================================================================
log "Instalando Composer..."
EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
[[ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]] && die "Checksum de Composer inválido"
php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
log "Composer $(composer --version --no-ansi | awk '{print $2}')"

# =============================================================================
# 4. Node.js LTS
# =============================================================================
log "Instalando Node.js ${NODE_VERSION}..."
curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash -
apt-get install -y -qq nodejs
log "Node $(node --version) / npm $(npm --version)"

# =============================================================================
# 5. Redis
# =============================================================================
log "Instalando Redis..."
apt-get install -y -qq redis-server
# Persistencia y límite de memoria básico
sed -i 's/^# *maxmemory .*/maxmemory 512mb/' /etc/redis/redis.conf
sed -i 's/^# *maxmemory-policy .*/maxmemory-policy volatile-lru/' /etc/redis/redis.conf
systemctl enable redis-server
systemctl restart redis-server
log "Redis $(redis-server --version | awk '{print $3}' | tr -d 'v=')"

# =============================================================================
# 6. PgBouncer
# =============================================================================
log "Instalando PgBouncer..."
apt-get install -y -qq pgbouncer

# La config real se aplica en el paso 8 con los archivos del repo
# Por ahora dejamos el servicio deshabilitado hasta configurar
systemctl disable pgbouncer 2>/dev/null || true
log "PgBouncer instalado (configurar con config/pgbouncer/pgbouncer.ini)"

# =============================================================================
# 7. Nginx
# =============================================================================
log "Instalando Nginx..."
apt-get install -y -qq nginx
rm -f /etc/nginx/sites-enabled/default
systemctl enable nginx
log "Nginx $(nginx -v 2>&1 | awk -F/ '{print $2}')"

# =============================================================================
# 8. Supervisor
# =============================================================================
log "Instalando Supervisor..."
apt-get install -y -qq supervisor
systemctl enable supervisor
systemctl start supervisor

# =============================================================================
# 9. Directorio de la aplicación
# =============================================================================
log "Preparando directorio de la aplicación..."
mkdir -p ${APP_DIR}
chown -R ${APP_USER}:${APP_USER} ${APP_DIR}
chmod -R 755 ${APP_DIR}

# Dar permisos de escritura a www-data en las carpetas de la app
setfacl -R -m u:${APP_USER}:rwx ${APP_DIR} 2>/dev/null || chmod -R 775 ${APP_DIR}

# =============================================================================
# 10. Recordatorios finales
# =============================================================================
echo ""
echo -e "${GREEN}=================================================${NC}"
echo -e "${GREEN} Instalación base completa${NC}"
echo -e "${GREEN}=================================================${NC}"
echo ""
warn "Pasos siguientes:"
echo "  1. Clonar el repo: cd /var/www && git clone <url> consultor"
echo "  2. Copiar config/pgbouncer/pgbouncer.ini → /etc/pgbouncer/"
echo "     Crear /etc/pgbouncer/userlist.txt con credenciales de PostgreSQL"
echo "     sudo systemctl enable pgbouncer && sudo systemctl start pgbouncer"
echo "  3. Copiar config/nginx/consultor.conf → /etc/nginx/sites-available/"
echo "     sudo ln -s /etc/nginx/sites-available/consultor.conf /etc/nginx/sites-enabled/"
echo "     sudo nginx -t && sudo systemctl reload nginx"
echo "  4. Copiar config/supervisor/consultor-horizon.conf → /etc/supervisor/conf.d/"
echo "     sudo supervisorctl reread && sudo supervisorctl update"
echo "  5. Copiar config/systemd/consultor-octane.service → /etc/systemd/system/"
echo "     sudo systemctl daemon-reload && sudo systemctl enable consultor-octane"
echo "  6. Configurar .env (usar .env.production.example como base)"
echo "  7. bash scripts/deploy.sh (primer deploy)"
echo "  8. sudo systemctl start consultor-octane"
echo ""
