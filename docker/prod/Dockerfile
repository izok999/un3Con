# syntax=docker/dockerfile:1.7
# =============================================================================
# docker/prod/Dockerfile — imagen espejo del servidor de produccion
#
# Replica lo que arma migrate/setup-server.sh + migrate/deploy.sh:
#   PHP 8.4 (no 8.5 — Sail usa 8.5 y produccion 8.4), FrankenPHP pineado,
#   OPcache de produccion, composer --no-dev --optimize-autoloader,
#   assets de Vite compilados, Supervisor para Horizon/Scheduler.
#
# Una sola imagen cubre los dos roles del server real:
#   rol `app`    → artisan octane:start (equivale a consultor-octane.service)
#   rol `worker` → supervisord con migrate/consultor-horizon.conf montado
#
# Las rutas y el binario se llaman igual que en el server (/var/www/consultor,
# php8.4) para poder montar los .conf de migrate/ sin editarlos.
# =============================================================================

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

# -----------------------------------------------------------------------------
# Etapa 1 — assets de Vite (equivale a `npm ci && npm run build` de deploy.sh)
# -----------------------------------------------------------------------------
FROM node:${NODE_VERSION}-bookworm-slim AS assets
WORKDIR /build

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# -----------------------------------------------------------------------------
# Etapa 2 — base PHP compartida.
#
# Las dependencias se resuelven contra ESTE PHP y no contra la imagen oficial de
# composer: laravel/horizon exige ext-pcntl, que la imagen de composer no trae.
# Resolviendo aca, el chequeo de plataforma de composer valida el runtime real.
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php${PHP_VERSION} AS php-base

ARG APP_DIR=/var/www/consultor
ENV APP_DIR=${APP_DIR}
WORKDIR ${APP_DIR}

# Extensiones: mismo set que instala setup-server.sh via los paquetes php8.4-*
RUN install-php-extensions \
      pdo_pgsql \
      pgsql \
      redis \
      opcache \
      mbstring \
      xml \
      bcmath \
      curl \
      zip \
      intl \
      gd \
      pcntl \
      posix \
      sockets

# Supervisor: el server real lo usa para Horizon + Scheduler.
# gosu para bajar a www-data, git/unzip para composer, curl para healthchecks.
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      supervisor procps curl gosu git unzip \
 && rm -rf /var/lib/apt/lists/*

# migrate/consultor-horizon.conf invoca `php8.4` por nombre. En el server ese
# binario existe porque viene del PPA de Sury; aca lo damos con un symlink
# para poder montar el .conf sin tocarle una coma.
RUN ln -sf /usr/local/bin/php /usr/local/bin/php8.4

COPY docker/prod/php/99-opcache-prod.ini "${PHP_INI_DIR}/conf.d/99-opcache-prod.ini"
COPY docker/prod/php/99-app-prod.ini     "${PHP_INI_DIR}/conf.d/99-app-prod.ini"

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# -----------------------------------------------------------------------------
# Etapa 3 — dependencias PHP (equivale a `composer install --no-dev` de deploy.sh)
# -----------------------------------------------------------------------------
FROM php-base AS vendor
WORKDIR /build

COPY composer.json composer.lock ./
# --no-scripts: los scripts de post-install corren artisan, que todavia no
# tiene el codigo de la app. El package:discover se hace en la etapa final.
RUN composer install \
      --no-dev \
      --optimize-autoloader \
      --no-interaction \
      --prefer-dist \
      --no-scripts \
      --no-progress

# -----------------------------------------------------------------------------
# Etapa 4 — runtime
# -----------------------------------------------------------------------------
FROM php-base AS runtime

# --- codigo de la app ---------------------------------------------------------
# COPY en vez de bind mount a proposito: con validate_timestamps=0 el bind mount
# no te aporta nada, y overlayfs se parece mas al disco del server que un mount
# del host.
COPY --chown=www-data:www-data . ${APP_DIR}
COPY --from=vendor  --chown=www-data:www-data /build/vendor        ${APP_DIR}/vendor
COPY --from=assets  --chown=www-data:www-data /build/public/build  ${APP_DIR}/public/build

RUN composer dump-autoload --no-dev --optimize --no-interaction \
 && php artisan package:discover --ansi \
 && mkdir -p storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Las cachés de Laravel (config/route/view/event) NO se hornean aca: config:cache
# congela los valores del .env, y el .env llega en runtime. deploy.sh las genera
# despues de tener el .env en su lugar — el entrypoint hace lo mismo.
COPY docker/prod/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000
ENTRYPOINT ["entrypoint"]
CMD ["app"]
