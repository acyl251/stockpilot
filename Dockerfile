# Local Docker fallback — Railway uses backend/nixpacks.toml (via railway.json).
# Use this file with: docker build -t stockpilot . && docker run -p 8080:8080 stockpilot
#
# ─────────────────────────────────────────────────────────────────────────────
# Stage 1 — Build Vue 3 SPA
# ─────────────────────────────────────────────────────────────────────────────
FROM node:20-alpine AS frontend-build

WORKDIR /build

COPY frontend/package*.json ./frontend/
RUN cd frontend && npm ci --prefer-offline

COPY frontend/ ./frontend/

# Build to /build/spa (overrides the relative outDir in vite.config.ts)
RUN cd frontend && npx vite build --outDir /build/spa

# ─────────────────────────────────────────────────────────────────────────────
# Stage 2 — PHP 8.3 + nginx (serves API + SPA from a single Railway service)
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS app

# System packages
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    zip \
    unzip \
    git \
    oniguruma-dev \
    libzip-dev \
    libpng-dev

# PHP extensions required by Laravel 11
RUN docker-php-ext-install pdo pdo_mysql pcntl bcmath zip mbstring

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Application ──────────────────────────────────────────────────────────────
WORKDIR /var/www/html

COPY backend/ .

# Install PHP deps.
# --ignore-platform-req=ext-oci8 skips the Oracle extension check (unused on Railway)
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --ignore-platform-req=ext-oci8

# Copy Vue SPA build into public/ (replaces the local dev placeholder index.html)
COPY --from=frontend-build /build/spa ./public/

# ── Runtime config ───────────────────────────────────────────────────────────
COPY docker/nginx/railway.conf   /etc/nginx/http.d/default.conf
COPY docker/supervisord.railway.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start.sh             /start.sh
RUN chmod +x /start.sh

# Laravel writable directories — must exist before any artisan command runs
RUN mkdir -p \
        storage/logs \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8080

CMD ["/start.sh"]
