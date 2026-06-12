#!/bin/sh
set -e

cd /var/www/html

# ── 1. Inject Railway's PORT into nginx config ────────────────────────────────
PORT=${PORT:-8080}
sed -i "s/RAILWAY_PORT/${PORT}/g" /etc/nginx/http.d/default.conf

# ── 2. Cache Laravel config / routes / views for production ──────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── 3. Run database migrations ────────────────────────────────────────────────
php artisan migrate --force --no-interaction

# ── 4. Fix storage permissions (needed when volume is mounted) ────────────────
chown -R www-data:www-data storage bootstrap/cache

# ── 5. Start nginx + php-fpm via supervisord ──────────────────────────────────
exec supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
