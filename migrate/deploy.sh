#!/bin/bash
# =============================================================================
# deploy.sh — Deploy de Consultor
# Ejecutar desde /var/www/consultor como www-data o con sudo
# =============================================================================
set -euo pipefail

APP_DIR="/var/www/consultor"
BRANCH="${1:-main}"
PHP="php8.4"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
log()    { echo -e "${GREEN}[✔]${NC} $1"; }
step()   { echo -e "${CYAN}[→]${NC} $1"; }
warn()   { echo -e "${YELLOW}[!]${NC} $1"; }
die()    { echo -e "${RED}[✘]${NC} $1"; exit 1; }

cd "${APP_DIR}" || die "No existe ${APP_DIR}"

echo ""
echo -e "${CYAN}=====================================================${NC}"
echo -e "${CYAN}  Deploy → rama: ${BRANCH}  ($(date '+%Y-%m-%d %H:%M:%S'))${NC}"
echo -e "${CYAN}=====================================================${NC}"
echo ""

# =============================================================================
# 1. Modo mantenimiento
# =============================================================================
step "Activando modo mantenimiento..."
${PHP} artisan down --retry=15 --render="errors::503" || warn "No se pudo activar mantenimiento (continúa igual)"

# =============================================================================
# 2. Actualizar código
# =============================================================================
step "Obteniendo cambios (git pull origin ${BRANCH})..."
git pull origin "${BRANCH}"
log "Código actualizado — commit: $(git log -1 --format='%h %s')"

# =============================================================================
# 3. Dependencias PHP
# =============================================================================
step "Instalando dependencias PHP (sin dev)..."
composer install \
  --no-dev \
  --optimize-autoloader \
  --no-interaction \
  --prefer-dist \
  --quiet
log "Composer listo"

# =============================================================================
# 4. Assets (Vite 7 + Tailwind 4)
# =============================================================================
step "Instalando dependencias JS..."
npm ci --silent
step "Compilando assets de producción..."
npm run build
log "Assets compilados → public/build/"

# =============================================================================
# 5. Migraciones
# =============================================================================
step "Ejecutando migraciones..."
${PHP} artisan migrate --force
log "Migraciones aplicadas"

# =============================================================================
# 6. Caché de Laravel (siempre en este orden)
# =============================================================================
step "Limpiando y reconstruyendo caché..."
${PHP} artisan config:clear
${PHP} artisan route:clear
${PHP} artisan view:clear
${PHP} artisan event:clear

${PHP} artisan config:cache
${PHP} artisan route:cache
${PHP} artisan view:cache
${PHP} artisan event:cache
log "Caché de Laravel reconstruida"

# =============================================================================
# 7. Reload graceful de Octane (sin cortar conexiones activas)
# =============================================================================
step "Recargando Octane (graceful)..."
if ${PHP} artisan octane:reload 2>/dev/null; then
  log "Octane recargado vía artisan"
else
  # Fallback: SIGUSR1 directo al proceso
  OCTANE_PID=$(pgrep -f "frankenphp" | head -1 || true)
  if [[ -n "${OCTANE_PID}" ]]; then
    kill -USR1 "${OCTANE_PID}"
    log "Octane recargado vía SIGUSR1 (PID ${OCTANE_PID})"
  else
    warn "Octane no está corriendo — iniciarlo con: sudo systemctl start consultor-octane"
  fi
fi

# =============================================================================
# 8. Horizon (queue workers)
# =============================================================================
step "Reiniciando Horizon..."
if command -v supervisorctl &>/dev/null; then
  supervisorctl restart consultor-horizon:* 2>/dev/null || warn "Supervisor: revisar manualmente"
  log "Horizon reiniciado"
else
  warn "Supervisor no disponible — reiniciar Horizon manualmente"
fi

# =============================================================================
# 9. Fin del mantenimiento
# =============================================================================
step "Desactivando modo mantenimiento..."
${PHP} artisan up
log "Aplicación en línea"

echo ""
echo -e "${GREEN}=====================================================${NC}"
echo -e "${GREEN}  Deploy completado  ✔  $(date '+%H:%M:%S')${NC}"
echo -e "${GREEN}=====================================================${NC}"
echo ""
