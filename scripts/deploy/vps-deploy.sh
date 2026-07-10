#!/usr/bin/env bash

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/dev-pulse}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NODE_BIN="${NODE_BIN:-node}"
NPM_BIN="${NPM_BIN:-npm}"
SKIP_NPM_BUILD="${SKIP_NPM_BUILD:-false}"
PHP_FPM_RELOAD_COMMAND="${PHP_FPM_RELOAD_COMMAND:-sudo systemctl reload php8.3-fpm}"
QUEUE_RESTART_COMMAND="${QUEUE_RESTART_COMMAND:-}"
WRITABLE_PATHS_COMMAND="${WRITABLE_PATHS_COMMAND:-sudo chown -R www-data:www-data storage bootstrap/cache && sudo chmod -R 775 storage bootstrap/cache}"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-}"

if [[ "$NODE_BIN" == */* ]]; then
    export PATH="$(dirname "$NODE_BIN"):$PATH"
fi

cd "$APP_DIR"

echo "==> DevPulse deploy started"
echo "==> App directory: $APP_DIR"
echo "==> Commit: $(git rev-parse --short HEAD)"
echo "==> PHP: $($PHP_BIN -r 'echo PHP_VERSION;')"
echo "==> Composer: $($COMPOSER_BIN --version --no-ansi)"

echo "==> Installing Composer dependencies"
"$COMPOSER_BIN" install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction \
    --no-progress

if [ "$SKIP_NPM_BUILD" != "true" ]; then
    echo "==> Installing and building frontend assets"
    echo "==> Node: $($NODE_BIN --version)"
    echo "==> NPM: $($NPM_BIN --version)"
    "$NPM_BIN" ci --no-audit --no-fund
    "$PHP_BIN" artisan wayfinder:generate
    "$NPM_BIN" run build
else
    echo "==> Skipping frontend build because SKIP_NPM_BUILD=true"
fi

IN_MAINTENANCE=false

restore_application() {
    if [ "$IN_MAINTENANCE" = "true" ]; then
        echo "==> Deployment interrupted; bringing DevPulse back up"
        "$PHP_BIN" artisan up || true
    fi
}

trap restore_application EXIT

echo "==> Enabling maintenance mode"
"$PHP_BIN" artisan down --retry=60
IN_MAINTENANCE=true

echo "==> Clearing old Laravel caches"
"$PHP_BIN" artisan optimize:clear

echo "==> Running database migrations"
"$PHP_BIN" artisan migrate --force

echo "==> Ensuring the public storage link exists"
"$PHP_BIN" artisan storage:link || true

echo "==> Caching Laravel configuration, events, routes, and views"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan event:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache

echo "==> Restarting queue workers"
"$PHP_BIN" artisan queue:restart || true
if [ -n "$QUEUE_RESTART_COMMAND" ]; then
    bash -lc "$QUEUE_RESTART_COMMAND"
fi

echo "==> Restoring writable directory permissions"
if [ -n "$WRITABLE_PATHS_COMMAND" ]; then
    bash -lc "$WRITABLE_PATHS_COMMAND"
fi

echo "==> Reloading PHP-FPM"
if [ -n "$PHP_FPM_RELOAD_COMMAND" ]; then
    bash -lc "$PHP_FPM_RELOAD_COMMAND"
fi

echo "==> Disabling maintenance mode"
"$PHP_BIN" artisan up
IN_MAINTENANCE=false
trap - EXIT

if [ -n "$HEALTHCHECK_URL" ]; then
    echo "==> Checking application health"
    curl --fail --silent --show-error \
        --retry 5 \
        --retry-delay 3 \
        --retry-connrefused \
        "$HEALTHCHECK_URL" >/dev/null
fi

echo "==> DevPulse deploy completed"
